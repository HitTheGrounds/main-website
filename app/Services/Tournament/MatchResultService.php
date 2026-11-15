<?php

namespace App\Services\Tournament;

use App\Helpers\CompanyAuth;
use App\Models\GroupTeam;
use App\Models\PlayerMatchStat;
use App\Models\Team;
use App\Models\TournamentGroup;
use App\Models\TournamentMatch;
use App\Models\User;
use App\Support\ScorerWriteThrottle;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class MatchResultService
{
    public function __construct(
        private TournamentWriteService $writes,
        private MatchResultValidator $validator,
        private GroupStandingsCalculator $standings,
        private MatchResultDescription $descriptions,
        private MatchTransitionGuard $transitions,
        private PlayerTotalsService $playerTotals,
    ) {}

    public function editor(int $year, int $matchId): array
    {
        $match = $this->read($year, $matchId);

        return ['match_id' => $match->id, 'version' => $match->version,
            'form' => $match->draft_result ?? $this->officialForm($match),
            'has_draft' => $match->draft_result !== null,
            'official' => $match->result_published_at === null ? null : $this->officialForm($match)];
    }

    /** A versioned full replacement draft, never a merge into official columns. */
    public function saveDraft(int $year, int $matchId, int $version, array $input): TournamentMatch
    {
        return $this->mutate($year, $matchId, $version, function ($match, $group, $matches, $actor) use ($input) {
            $draft = $this->validator->validateDraft($match, $input);
            if ($this->digest($match->draft_result ?? []) !== $this->digest($draft)) {
                $match->draft_result = $draft;
                $this->save($match, $actor);
            }

            return $match->refresh();
        });
    }

    public function discardDraft(int $year, int $matchId, int $version): TournamentMatch
    {
        return $this->mutate($year, $matchId, $version, function ($match, $group, $matches, $actor) {
            if ($match->draft_result !== null) {
                $match->draft_result = null;
                $this->save($match, $actor);
            }

            return $match->refresh();
        });
    }

    public function review(int $year, int $matchId): array
    {
        $match = $this->read($year, $matchId);
        if (! is_array($match->draft_result)) {
            $this->reject('Save a result draft before reviewing it.');
        }
        $result = $this->validator->validate($match, $match->draft_result);

        return ['match_id' => $match->id, 'version' => $match->version,
            'draft_hash' => $this->digest($match->draft_result), 'result' => $result,
            'description' => $this->description($match, $result)];
    }

    public function publish(int $year, int $matchId, int $version, string $draftHash): TournamentMatch
    {
        return $this->mutate($year, $matchId, $version, function ($match, $group, $matches, $actor) use ($year, $draftHash) {
            if (! is_array($match->draft_result) || ! hash_equals($this->digest($match->draft_result), $draftHash)) {
                $this->reject('The saved result differs from your review. Review the latest draft before publishing.');
            }
            $this->transitions->assertResultPublication($match);
            $result = $this->validator->validate($match, $match->draft_result);
            $this->description($match, $result); // Reject inconsistent descriptions before changing official data.

            $teamIds = GroupTeam::where('group_id', $group->id)->where('tournament_year', $year)->pluck('team_id');
            $teams = Team::whereIn('id', $teamIds)->orderBy('id')->lockForUpdate()->get();
            $assignments = GroupTeam::where('group_id', $group->id)->where('tournament_year', $year)
                ->orderBy('id')->lockForUpdate()->get();
            foreach ([$match->team1_id, $match->team2_id] as $teamId) {
                if (! $assignments->contains('team_id', $teamId) || ! $teams->firstWhere('id', $teamId)?->approved) {
                    $this->reject('Both participants must still be approved teams in this group.');
                }
            }
            foreach ($result as $field => $value) {
                $match->$field = $value;
            }
            $match->status = 'finished';
            $match->structure_locked_at ??= now();
            $match->result_published_at = now();
            $match->published_by = $actor->id;
            $match->draft_result = null;
            $this->save($match, $actor);

            // $match is the same locked model instance inside $matches. Its new
            // official values are included; all other drafts remain ignored.
            $calculated = $this->standings->calculate($group, $assignments, $matches);
            foreach ($assignments as $assignment) {
                foreach ($calculated['rows'][$assignment->team_id] as $field => $value) {
                    $assignment->$field = $value;
                }
                if ($assignment->isDirty()) {
                    $assignment->version++;
                    $assignment->save();
                }
            }
            $group->version++;
            $group->save();

            // Official performances survive eligibility changes. Never promote
            // corrections/withdrawals from the separate player draft workflow.
            // Source rows precede summary rows in the shared lock order.
            $playerIds = PlayerMatchStat::where('match_id', $match->id)
                ->where('record_state', 'published')->orderBy('id')->lockForUpdate()
                ->get(['id', 'team_member_id'])->pluck('team_member_id')->unique()->values()->all();
            $this->playerTotals->recalculate($year, $playerIds);

            return $match->refresh();
        }, true);
    }

    private function mutate(int $year, int $matchId, int $version, callable $write, bool $publication = false): TournamentMatch
    {
        ScorerWriteThrottle::hit();
        Validator::make(compact('year', 'matchId', 'version'), [
            'year' => ['required', 'integer', 'between:1,9999'], 'matchId' => ['integer', 'min:1'], 'version' => ['integer', 'min:1'],
        ])->validate();

        return $this->writes->run($year, function () use ($year, $matchId, $version, $write, $publication) {
            $actor = $this->authorize(true);
            $reference = $this->find($year, $matchId);
            $group = TournamentGroup::whereKey($reference->group_id)->where('tournament_year', $year)->lockForUpdate()->first();
            if (! $group) {
                $this->reject('The match group is no longer available in this tournament year.');
            }
            $query = TournamentMatch::where('tournament_year', $year)->where('group_id', $group->id);
            if (! $publication) {
                $query->whereKey($matchId);
            }
            $matches = $query->orderBy('id')->lockForUpdate()->get();
            $match = $matches->firstWhere('id', $matchId);
            if (! $match || $match->version !== $version) {
                $this->reject('This match changed while you were editing or reviewing. Reload before saving.');
            }

            return $write($match, $group, $matches, $actor);
        });
    }

    private function read(int $year, int $matchId): TournamentMatch
    {
        $this->authorize();
        Validator::make(['year' => $year], ['year' => ['integer', 'between:1,9999']])->validate();

        return $this->find($year, $matchId);
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

    private function find(int $year, int $matchId): TournamentMatch
    {
        $match = TournamentMatch::whereKey($matchId)->where('tournament_year', $year)->first();
        if (! $match || $match->stage !== 'G') {
            $this->reject('Select an existing group match in this tournament year. Knockout scoring is not available yet.');
        }

        return $match;
    }

    private function save(TournamentMatch $match, User $actor): void
    {
        $match->version++;
        $match->updated_by = $actor->id;
        $match->save();
    }

    private function description(TournamentMatch $match, array $result): string
    {
        $match->load(['team1:id,team_name', 'team2:id,team_name']);

        return $this->descriptions->describe($match, $result, $match->team1->team_name, $match->team2->team_name);
    }

    private function digest(array $draft): string
    {
        $sort = function (array $value) use (&$sort): array {
            foreach ($value as &$item) {
                if (is_array($item)) {
                    $item = $sort($item);
                }
            }
            unset($item);
            if (! array_is_list($value)) {
                ksort($value);
            }

            return $value;
        };

        return hash('sha256', json_encode($sort($draft), JSON_THROW_ON_ERROR));
    }

    private function officialForm(TournamentMatch $match): array
    {
        $form = $match->only(['result_type', 'result_method', 'winner_id', 'batting_first_id', 'result_note', 'revised_target', 'nrr_inputs']);
        foreach (['team1', 'team2'] as $team) {
            $count = $match->{$team.'_deliveries'};
            $form[$team.'_runs'] = $match->{$team.'_runs'};
            $form[$team.'_wickets'] = $match->{$team.'_wickets'};
            $form[$team.'_overs'] = $count === null ? null : intdiv($count, $match->balls_per_over);
            $form[$team.'_balls'] = $count === null ? null : $count % $match->balls_per_over;
        }
        if (is_array($form['nrr_inputs'])) {
            foreach (['team1', 'team2'] as $team) {
                if (isset($form['nrr_inputs'][$team]['deliveries'])) {
                    $count = $form['nrr_inputs'][$team]['deliveries'];
                    $form['nrr_inputs'][$team]['overs'] = intdiv($count, $match->balls_per_over);
                    $form['nrr_inputs'][$team]['balls'] = $count % $match->balls_per_over;
                    unset($form['nrr_inputs'][$team]['deliveries']);
                }
            }
        }

        return $form;
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['tournament' => $message]);
    }
}
