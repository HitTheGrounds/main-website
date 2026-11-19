<?php

namespace App\Services\Tournament;

use App\Helpers\CompanyAuth;
use App\Models\GroupTeam;
use App\Models\PlayerMatchStat;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TournamentGroup;
use App\Models\TournamentMatch;
use App\Models\User;
use App\Support\ScorerWriteThrottle;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlayerPublicationService
{
    public function __construct(
        private TournamentWriteService $writes,
        private RosterProtectionService $rosters,
        private PlayerStatsValidator $validator,
        private PlayerTotalsService $totals,
        private MatchTransitionGuard $transitions,
        private PlayerMatchConsistency $consistency,
    ) {}

    public function review(int $year, int $matchId): array
    {
        return $this->locked($year, $matchId, fn ($match, $rows) => $this->proposal($year, $match, $rows));
    }

    public function publish(int $year, int $matchId, int $version, int $revision, string $receipt): TournamentMatch
    {
        ScorerWriteThrottle::hit();
        Validator::make(compact('version', 'revision', 'receipt'), [
            'version' => ['integer', 'min:1'], 'revision' => ['integer', 'min:0'],
            'receipt' => ['regex:/\A[a-f0-9]{64}\z/'],
        ])->validate();

        return $this->locked($year, $matchId, function ($match, $rows, $actor) use ($year, $version, $revision, $receipt) {
            if ($match->version !== $version || $match->player_stats_revision !== $revision) {
                $this->reject('This match or its player drafts changed. Review the latest player updates before publishing.');
            }
            $proposal = $this->proposal($year, $match, $rows);
            if (! hash_equals($proposal['receipt'], $receipt)) {
                $this->reject('The player updates differ from your review. Review again before publishing.');
            }
            $affected = [];
            foreach ($proposal['changes'] as $change) {
                $draft = $rows->firstWhere('id', $change['draft']['id']);
                $official = $change['published'] === null ? null : $rows->firstWhere('id', $change['published']['id']);
                $affected[] = $draft->team_member_id;
                if ($draft->pending_action === 'withdraw') {
                    $official->delete();
                } else {
                    $official ??= new PlayerMatchStat;
                    foreach ($draft->only($this->figureFields()) as $field => $value) {
                        $official->$field = $value;
                    }
                    $official->match_id = $match->id;
                    $official->team_id = $draft->team_id;
                    $official->team_member_id = $draft->team_member_id;
                    $official->record_state = 'published';
                    $official->pending_action = null;
                    $official->published_at = now();
                    $official->published_by = $actor->id;
                    $official->updated_by = $actor->id;
                    $official->version = $official->exists ? $official->version + 1 : 1;
                    $official->save();
                }
                $draft->delete();
            }
            $this->totals->recalculate($year, array_values(array_unique($affected)));
            $match->player_stats_revision++;
            $match->version++;
            $match->updated_by = $actor->id;
            $match->player_stats_published_at = now();
            $match->structure_locked_at ??= now();
            $match->save();

            return $match->refresh();
        });
    }

    /** A coherent review under the same locks used to publish; no writes/budget. */
    private function locked(int $year, int $matchId, callable $callback): mixed
    {
        $this->authorize();
        Validator::make(compact('year', 'matchId'), ['year' => ['integer', 'between:1,9999'], 'matchId' => ['integer', 'min:1']])->validate();

        return $this->writes->run($year, function () use ($year, $matchId, $callback) {
            $actor = $this->authorize(true);
            $reference = TournamentMatch::whereKey($matchId)->where('tournament_year', $year)->first();
            if (! $reference || ! in_array($reference->stage, ['G', 'QF', 'SF', 'F'], true)) {
                $this->reject('Select a match in this tournament year.');
            }
            $group = $reference->group_id === null ? null : TournamentGroup::whereKey($reference->group_id)->where('tournament_year', $year)->lockForUpdate()->first();
            $query = TournamentMatch::where('tournament_year', $year);
            if ($reference->stage === 'G') {
                $query->whereKey($matchId);
            }
            $matches = $query->orderBy('id')->lockForUpdate()->get();
            $match = $matches->firstWhere('id', $matchId);
            if (! $match || ($match->stage === 'G' && ! $group)
                || $match->status !== 'finished' || $match->result_published_at === null || ! in_array($match->result_type, ['win', 'tie'], true)) {
                $this->reject('Player publication requires an eligible published finished result.');
            }
            $teamIds = [$match->team1_id, $match->team2_id];
            $teams = Team::whereIn('id', $teamIds)->orderBy('id')->lockForUpdate()->get();
            $query = GroupTeam::where('tournament_year', $year)->whereIn('team_id', $teamIds);
            if ($group) {
                $query->where('group_id', $group->id);
            }
            $assignments = $query->orderBy('id')->lockForUpdate()->get();
            if ($teams->count() !== 2 || $teams->contains(fn ($team) => ! $team->approved) || $assignments->count() !== 2) {
                $this->reject('Both participating teams must still be approved and assigned to this match edition.');
            }
            $this->transitions->assertParticipants($match, $teams, $assignments, $matches);
            $pending = PlayerMatchStat::where('match_id', $match->id)->where('record_state', 'draft')->get();
            foreach ($pending as $draft) {
                if (! in_array($draft->team_id, $teamIds, true)) {
                    $this->reject('A pending player references a team outside this match.');
                }
            }
            $members = TeamMember::whereIn('team_id', $teamIds)->whereIn('id', $pending->pluck('team_member_id'))->orderBy('id')->lockForUpdate()->get();
            // All teams and members are locked first. Reentrant roster-helper
            // locks cannot introduce a later team lock after an earlier member.
            foreach ($pending as $draft) {
                $member = $members->firstWhere('id', $draft->team_member_id);
                if (! in_array($draft->team_id, $teamIds, true) || ! $member || (int) $member->team_id !== $draft->team_id) {
                    $this->reject('A pending player no longer belongs to the selected participating team.');
                }
                $this->rosters->withLockedMember($draft->team_id, $draft->team_member_id, fn () => null);
            }
            $rows = PlayerMatchStat::where('match_id', $match->id)->orderBy('id')->lockForUpdate()->get();

            return $callback($match, $rows, $actor);
        });
    }

    private function proposal(int $year, TournamentMatch $match, $rows): array
    {
        $drafts = $rows->where('record_state', 'draft');
        if ($drafts->isEmpty()) {
            $this->reject('There are no pending player updates to review or publish.');
        }
        $history = PlayerMatchStat::where('record_state', 'published')->whereIn('team_member_id', $drafts->pluck('team_member_id'))
            ->whereHas('tournamentMatch', fn ($query) => $query->where('tournament_year', $year))->get();
        $changes = [];
        foreach ($drafts as $draft) {
            $official = $rows->where('record_state', 'published')->firstWhere('team_member_id', $draft->team_member_id);
            Validator::make($draft->only(['player_name', 'player_gender', 'pending_action']), [
                'player_name' => ['required', 'string', 'max:255'], 'player_gender' => ['required', Rule::in(['Male', 'Female', 'Other'])],
                'pending_action' => ['required', Rule::in(['replace', 'withdraw'])],
            ])->validate();
            if ($draft->pending_action !== 'withdraw') {
                $this->validator->validate($draft->only([...PlayerStatsValidator::METRICS, 'batting_status']));
            }
            if ($official && ($official->team_id !== $draft->team_id || $official->player_name !== $draft->player_name || $official->player_gender !== $draft->player_gender)) {
                $this->reject('A correction must preserve the published player identity. Reload and resolve the historical record.');
            }
            if ($draft->pending_action === 'withdraw') {
                if (! $official || $draft->only($this->figureFields()) !== $official->only($this->figureFields())) {
                    $this->reject('The performance selected for withdrawal changed or is missing. Reload and stage it again.');
                }
            } else {
                foreach ($history->where('team_member_id', $draft->team_member_id) as $previous) {
                    if ($previous->team_id !== $draft->team_id || $previous->player_gender !== $draft->player_gender) {
                        $this->reject('This player has a conflicting published team or gender in this edition. Resolve the historical classification before publishing.');
                    }
                }
            }
            $fields = ['id', 'version', 'team_id', 'team_member_id', 'pending_action', ...$this->figureFields()];
            $changes[] = ['draft' => $draft->only($fields), 'published' => $official?->only($fields),
                'missing_metrics' => array_values(array_filter(PlayerStatsValidator::METRICS, fn ($field) => $draft->$field === null))];
        }
        $effective = $rows->where('record_state', 'published')->keyBy('team_member_id');
        foreach ($drafts as $draft) {
            if ($draft->pending_action === 'withdraw') {
                $effective->forget($draft->team_member_id);
            } else {
                $effective->put($draft->team_member_id, $draft);
            }
        }
        $this->consistency->assertConsistent($match, $effective);
        $review = ['match_id' => $match->id, 'version' => $match->version, 'player_stats_revision' => $match->player_stats_revision, 'changes' => $changes];
        $review['receipt'] = hash('sha256', json_encode($review, JSON_THROW_ON_ERROR));

        return $review;
    }

    private function figureFields(): array
    {
        return [...PlayerStatsValidator::METRICS, 'batting_status', 'player_name', 'player_gender'];
    }

    private function authorize(bool $lock = false): User
    {
        abort_unless(config('tournament.enabled') === true, 404);
        $query = User::whereKey(CompanyAuth::user()?->id);
        if ($lock) {
            $query->sharedLock();
        }
        $actor = $query->first();
        abort_unless($actor && $actor->is_scorer, 403);

        return $actor;
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['player' => $message]);
    }
}
