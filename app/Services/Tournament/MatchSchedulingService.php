<?php

namespace App\Services\Tournament;

use App\Helpers\CompanyAuth;
use App\Models\GroupTeam;
use App\Models\Team;
use App\Models\TournamentGroup;
use App\Models\TournamentMatch;
use App\Models\User;
use App\Support\ScorerWriteThrottle;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MatchSchedulingService
{
    public function __construct(
        private TournamentWriteService $writes,
        private MatchStructureValidator $structure,
        private MatchTransitionGuard $transitions,
        private MatchSummaryService $summaries,
        private KnockoutEligibility $eligibility,
    ) {}

    public function listing(int $year, array $filters = []): LengthAwarePaginator
    {
        $query = $this->readQuery($year);
        if (array_diff(array_keys($filters), ['stage', 'status', 'group_id'])) {
            $this->reject('tournament', 'Select valid match filters.');
        }
        $filters = Validator::make($filters, [
            'stage' => ['nullable', Rule::in(app(PlayoffFeature::class)->stages($year))],
            'status' => ['nullable', Rule::in(['upcoming', 'live', 'finished'])],
            // A removed/other-year group simply has no matching fixtures. Do not
            // break an open dashboard if an unused group is deleted elsewhere.
            'group_id' => ['nullable', 'integer', 'min:1'],
        ])->validate();
        foreach ($filters as $field => $value) {
            if ($value !== null && $value !== '') {
                $query->where($field, $value);
            }
        }

        return $query->orderByDesc('id')->paginate(10);
    }

    public function findForScheduling(int $year, int $matchId): TournamentMatch
    {
        $match = $this->readQuery($year)->whereKey($matchId)->first();
        if (! $match) {
            $this->reject('tournament', 'Select an existing match in this tournament year.');
        }

        return $match;
    }

    private function readQuery(int $year): Builder
    {
        abort_unless(config('tournament.enabled') === true, 404);
        $id = CompanyAuth::user()?->id;
        abort_unless($id && User::whereKey($id)->where('is_scorer', true)->exists(), 403);
        Validator::make(['year' => $year], ['year' => ['required', 'integer', 'between:1,9999']])->validate();

        return TournamentMatch::where('tournament_year', $year)->whereIn('stage', app(PlayoffFeature::class)->stages($year))
            ->select(['id', 'tournament_year', 'group_id', 'stage', 'bracket_position', 'team1_id', 'team2_id',
                'balls_per_over', 'overs_per_innings', 'max_wickets', 'status', 'scheduled_at', 'venue',
                'display_number', 'batting_first_id', 'version', 'structure_locked_at', 'result_published_at',
                'player_stats_published_at', 'result_type', 'result_method', 'winner_id',
                // Official innings only (never draft_result), shown on finished cards once published.
                'team1_runs', 'team1_wickets', 'team1_deliveries', 'team2_runs', 'team2_wickets', 'team2_deliveries'])
            ->selectRaw('CASE WHEN draft_result IS NOT NULL OR nrr_inputs IS NOT NULL OR result_note IS NOT NULL OR revised_target IS NOT NULL THEN 1 ELSE 0 END AS has_saved_result_data')
            ->withExists('playerMatchStats')
            ->with(['team1:id,team_name', 'team2:id,team_name', 'group:id,name']);
    }

    /** No saved score sheet or published history may be silently reassigned/deleted. */
    public function canEditFixture(TournamentMatch $match): bool
    {
        if ($match->status !== 'upcoming' || $match->structure_locked_at !== null
            || $match->result_published_at !== null || $match->player_stats_published_at !== null) {
            return false;
        }
        foreach (['result_type', 'result_method', 'winner_id', 'team1_runs', 'team1_wickets', 'team1_deliveries',
            'team2_runs', 'team2_wickets', 'team2_deliveries'] as $field) {
            if ($match->$field !== null) {
                return false;
            }
        }
        $saved = $match->getAttributes()['has_saved_result_data'] ?? ($match->draft_result !== null
            || $match->nrr_inputs !== null || $match->result_note !== null || $match->revised_target !== null);
        $players = $match->getAttributes()['player_match_stats_exists'] ?? $match->playerMatchStats()->exists();

        return ! $saved && ! $players;
    }

    public function updateFixture(int $year, int $matchId, int $version, int $groupVersion, array $structure, array $schedule, ?string $knockoutRevision = null): TournamentMatch
    {
        return $this->mutate($year, function (User $actor) use ($year, $matchId, $version, $groupVersion, $structure, $schedule, $knockoutRevision) {
            $groups = TournamentGroup::where('tournament_year', $year)->orderBy('id')->lockForUpdate()->get();
            $matches = TournamentMatch::where('tournament_year', $year)->orderBy('id')->lockForUpdate()->get();
            $match = $this->unusedFixture($matches, $matchId, $version);
            $oldGroupId = $match->group_id;
            $data = $this->structure->validate($year, $structure, $match->id);
            if ($data['stage'] === 'G') {
                if ($groups->firstWhere('id', $data['group_id'])?->version !== $groupVersion) {
                    $this->reject('tournament', 'The selected group changed. Reload the fixture before saving.');
                }
            } elseif ($knockoutRevision === null || ! hash_equals($this->knockoutSnapshot($year, $data['stage'])['revision'], $knockoutRevision)) {
                $this->reject('tournament', 'Knockout selection changed. Reload the fixture before saving.');
            }
            Team::whereIn('id', [$match->team1_id, $match->team2_id, $data['team1_id'], $data['team2_id']])
                ->orderBy('id')->lockForUpdate()->get();
            // Recheck approval/membership after waiting for registration-side locks.
            $data = $this->structure->validate($year, $structure, $match->id);
            $details = $this->schedule($schedule, [$data['team1_id'], $data['team2_id']]);
            foreach ($data + $details as $field => $value) {
                $match->$field = $value;
            }
            if ($match->batting_first_id !== null && ! in_array($match->batting_first_id, [$match->team1_id, $match->team2_id], true)) {
                $this->reject('batting_first_id', 'Choose a batting-first team from the updated participants.');
            }
            $match->version++;
            $match->updated_by = $actor->id;
            $match->save();
            foreach ($groups->whereIn('id', array_unique(array_filter([$oldGroupId, $data['group_id']]))) as $group) {
                $group->version++;
                $group->save();
            }

            return $match->refresh();
        });
    }

    public function deleteFixture(int $year, int $matchId, int $version): void
    {
        $this->mutate($year, function () use ($year, $matchId, $version) {
            $groups = TournamentGroup::where('tournament_year', $year)->orderBy('id')->lockForUpdate()->get();
            $matches = TournamentMatch::where('tournament_year', $year)->orderBy('id')->lockForUpdate()->get();
            $match = $this->unusedFixture($matches, $matchId, $version);
            $group = $groups->firstWhere('id', $match->group_id);
            $match->delete();
            if ($group) {
                $group->version++;
                $group->save();
            }

            return $match;
        });
    }

    private function unusedFixture($matches, int $matchId, int $version): TournamentMatch
    {
        $match = $matches->firstWhere('id', $matchId);
        if (! $match || $match->version !== $version) {
            $this->reject('tournament', 'This match changed. Reload it before editing or deleting.');
        }
        if (! in_array($match->stage, app(PlayoffFeature::class)->stages($match->tournament_year), true)) {
            $this->reject('tournament', 'This match stage is disabled. Reload the match list.');
        }
        if (! $this->canEditFixture($match)) {
            $this->reject('tournament', 'Only upcoming fixtures without saved result or player figures can be changed or deleted. Discard drafts first; published history is protected.');
        }

        return $match;
    }

    public function create(int $year, int $groupVersion, array $structure, array $schedule = [], ?string $knockoutRevision = null): TournamentMatch
    {
        return $this->mutate($year, function (User $actor) use ($year, $groupVersion, $structure, $schedule, $knockoutRevision) {
            $data = $this->structure->validate($year, $structure);
            if ($data['stage'] === 'G') {
                $group = TournamentGroup::whereKey($data['group_id'])->where('tournament_year', $year)->lockForUpdate()->first();
                if (! $group || $group->version !== $groupVersion) {
                    $this->reject('tournament', 'This group changed while you were choosing teams. Reload before saving.');
                }
            } else {
                TournamentMatch::where('tournament_year', $year)->orderBy('id')->lockForUpdate()->get();
            }
            // Roster deletion locks teams without the year lock. Lock both in ID
            // order and revalidate membership/approval after any wait.
            Team::whereIn('id', [$data['team1_id'], $data['team2_id']])->orderBy('id')->lockForUpdate()->get();
            $data = $this->structure->validate($year, $structure);
            if ($data['stage'] !== 'G') {
                GroupTeam::where('tournament_year', $year)->whereIn('team_id', [$data['team1_id'], $data['team2_id']])
                    ->orderBy('id')->lockForUpdate()->get();
                $current = $this->knockoutSnapshot($year, $data['stage']);
                if ($knockoutRevision === null || ! hash_equals($current['revision'], $knockoutRevision)) {
                    $this->reject('tournament', 'Knockout selection changed. Reload the round before saving.');
                }
            }
            $details = $this->schedule($schedule, [$data['team1_id'], $data['team2_id']]);

            $match = new TournamentMatch;
            foreach ($data + $details as $field => $value) {
                $match->$field = $value;
            }
            $match->tournament_year = $year;
            $match->status = $details['status'] ?? 'upcoming';
            $match->created_by = $actor->id;
            $match->updated_by = $actor->id;
            $match->save();

            return $match->refresh();
        });
    }

    public function updateSchedule(int $year, int $matchId, int $version, array $schedule): TournamentMatch
    {
        return $this->mutate($year, function (User $actor) use ($year, $matchId, $version, $schedule) {
            // The common year lock serializes feature writes. Lock the current
            // group before the match, following the shared table lock order.
            $reference = TournamentMatch::whereKey($matchId)->where('tournament_year', $year)->first();
            if (! $reference || ! in_array($reference->stage, app(PlayoffFeature::class)->stages($year), true)) {
                $this->reject('tournament', 'Select an existing match in this tournament year.');
            }
            $group = $reference->group_id === null ? null : TournamentGroup::whereKey($reference->group_id)
                ->where('tournament_year', $year)->lockForUpdate()->first();
            if ($reference->stage === 'G' && ! $group) {
                $this->reject('tournament', 'The match group is no longer available.');
            }
            $matches = TournamentMatch::where('tournament_year', $year)->orderBy('id')->lockForUpdate()->get();
            $match = $matches->firstWhere('id', $matchId);
            if (! $match || $match->version !== $version) {
                $this->reject('tournament', 'This match changed while you were editing. Reload before saving.');
            }
            $details = $this->schedule($schedule, [$match->team1_id, $match->team2_id], true);
            $statusChanged = isset($details['status']) && $details['status'] !== $match->status;
            $assignments = collect();
            if ($statusChanged) {
                $query = GroupTeam::where('tournament_year', $year);
                $group ? $query->where('group_id', $group->id) : $query->whereIn('team_id', [$match->team1_id, $match->team2_id]);
                $teams = Team::whereIn('id', (clone $query)->pluck('team_id'))->orderBy('id')->lockForUpdate()->get();
                $assignments = $query->orderBy('id')->lockForUpdate()->get();
                foreach ([$match->team1_id, $match->team2_id] as $teamId) {
                    if (! $assignments->contains('team_id', $teamId) || ! $teams->firstWhere('id', $teamId)?->approved) {
                        $this->reject('tournament', 'Both participants must still be approved and assigned to this edition.');
                    }
                }
                $this->transitions->assertParticipants($match, $teams, $assignments, $matches);
            }
            if (array_key_exists('batting_first_id', $details)
                && $details['batting_first_id'] !== $match->batting_first_id
                && ($match->structure_locked_at !== null || $match->result_published_at !== null)) {
                $this->reject('batting_first_id', 'The batting-first team is locked after result publication.');
            }
            if (isset($details['status'])) {
                $this->transitions->assertScheduleStatusChange($match, $details['status'], $matches, $assignments);
            }
            foreach ($details as $field => $value) {
                $match->$field = $value;
            }
            if ($match->isDirty()) {
                $match->version++;
                $match->updated_by = $actor->id;
                $match->save();
            }

            if ($statusChanged && $match->result_published_at !== null) {
                $this->summaries->recalculate($match, $group, $matches, $assignments);
            }

            return $match->refresh();
        });
    }

    public function knockoutOptions(int $year, string $stage): array
    {
        $this->readQuery($year); // Existing feature and scorer checks.

        return $this->writes->run($year, function () use ($year, $stage) {
            abort_unless(User::whereKey(CompanyAuth::user()?->id)->sharedLock()->first()?->is_scorer, 403);

            return $this->knockoutSnapshot($year, $stage);
        });
    }

    private function knockoutSnapshot(int $year, string $stage): array
    {
        $feature = app(PlayoffFeature::class);
        Validator::make(compact('stage'), ['stage' => ['required', Rule::in(array_values(array_diff($feature->stages($year), ['G'])))]])->validate();
        $playoffsEnabled = $feature->enabled($year);
        $assignments = GroupTeam::where('tournament_year', $year)->orderBy('id')->get();
        $teams = Team::whereIn('id', $assignments->pluck('team_id'))->orderBy('id')->get();
        $matches = TournamentMatch::where('tournament_year', $year)->orderBy('id')->get();
        $candidates = $this->eligibility->candidates($year, $stage, $teams, $assignments, $matches, $playoffsEnabled);
        $positions = $this->eligibility->round($year, $stage, $teams, $assignments, $matches, $playoffsEnabled);
        $revision = hash('sha256', json_encode([$year, $stage, $playoffsEnabled,
            $assignments->map->only(['id', 'version', 'team_id', 'group_id', 'qualified'])->all(),
            $teams->map->only(['id', 'approved'])->all(),
            $matches->map->only(['id', 'version', 'stage', 'status', 'bracket_position', 'team1_id', 'team2_id', 'winner_id', 'result_type', 'result_published_at'])->all(),
        ], JSON_THROW_ON_ERROR));

        $fixtures = $matches->where('stage', $stage)->map(fn ($match) => [
            ...$match->only(['id', 'version', 'bracket_position', 'status', 'winner_id']),
            'published' => $match->result_published_at !== null,
        ])->values()->all();

        return compact('candidates', 'positions', 'revision', 'fixtures');
    }

    private function mutate(int $year, callable $write): TournamentMatch
    {
        ScorerWriteThrottle::hit();
        Validator::make(['year' => $year], ['year' => ['required', 'integer', 'between:1,9999']])->validate();

        return $this->writes->run($year, function () use ($write) {
            abort_unless(config('tournament.enabled') === true, 404);
            $actor = User::whereKey(CompanyAuth::user()?->id)->sharedLock()->first();
            abort_unless($actor && $actor->is_scorer, 403);

            return $write($actor);
        });
    }

    /** Partial schedule edits never accept result, structural or actor fields. */
    private function schedule(array $input, array $teamIds, bool $allowFinished = false): array
    {
        if (array_diff(array_keys($input), ['status', 'scheduled_at', 'venue', 'display_number', 'batting_first_id'])) {
            $this->reject('tournament', 'Scheduling contains unsupported fields. Reload and try again.');
        }
        if (array_key_exists('venue', $input) && is_string($input['venue'])) {
            $input['venue'] = trim($input['venue']);
            if ($input['venue'] === '') {
                $input['venue'] = null;
            }
        }
        $data = Validator::make($input, [
            'status' => ['sometimes', 'required', Rule::in($allowFinished ? ['upcoming', 'live', 'finished'] : ['upcoming', 'live'])],
            // datetime-local input is explicitly interpreted in tournament time.
            'scheduled_at' => ['sometimes', 'nullable', 'date_format:Y-m-d\TH:i'],
            'venue' => ['sometimes', 'nullable', 'string', 'max:255'],
            'display_number' => ['sometimes', 'nullable', 'integer', 'between:1,2147483647'],
            'batting_first_id' => ['sometimes', 'nullable', 'integer', Rule::in($teamIds)],
        ])->validate();
        foreach (['display_number', 'batting_first_id'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = (int) $data[$field];
            }
        }
        if (isset($data['scheduled_at'])) {
            $data['scheduled_at'] = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['scheduled_at'], config('tournament.timezone'))->utc();
        }

        return $data;
    }

    private function reject(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
