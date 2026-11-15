<?php

namespace App\Services\Tournament;

use App\Helpers\CompanyAuth;
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
    public function __construct(private TournamentWriteService $writes, private MatchStructureValidator $structure) {}

    public function listing(int $year, array $filters = []): LengthAwarePaginator
    {
        $query = $this->readQuery($year);
        if (array_diff(array_keys($filters), ['stage', 'status', 'group_id'])) {
            $this->reject('tournament', 'Select valid match filters.');
        }
        $filters = Validator::make($filters, [
            'stage' => ['nullable', Rule::in(['G', 'QF', 'SF', 'F'])],
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

        return TournamentMatch::where('tournament_year', $year)
            ->select(['id', 'tournament_year', 'group_id', 'stage', 'bracket_position', 'team1_id', 'team2_id',
                'balls_per_over', 'overs_per_innings', 'max_wickets', 'status', 'scheduled_at', 'venue',
                'display_number', 'batting_first_id', 'version', 'structure_locked_at', 'result_published_at',
                'player_stats_published_at', 'result_type', 'winner_id'])
            ->with(['team1:id,team_name', 'team2:id,team_name', 'group:id,name']);
    }

    public function create(int $year, int $groupVersion, array $structure, array $schedule = []): TournamentMatch
    {
        return $this->mutate($year, function (User $actor) use ($year, $groupVersion, $structure, $schedule) {
            $data = $this->structure->validate($year, $structure);
            $group = TournamentGroup::whereKey($data['group_id'])->where('tournament_year', $year)->lockForUpdate()->first();
            if (! $group || $group->version !== $groupVersion) {
                $this->reject('tournament', 'This group changed while you were choosing teams. Reload before saving.');
            }
            // Roster deletion locks teams without the year lock. Lock both in ID
            // order and revalidate membership/approval after any wait.
            Team::whereIn('id', [$data['team1_id'], $data['team2_id']])->orderBy('id')->lockForUpdate()->get();
            $data = $this->structure->validate($year, $structure);
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
            if (! $reference) {
                $this->reject('tournament', 'Select an existing match in this tournament year.');
            }
            if ($reference->group_id !== null) {
                TournamentGroup::whereKey($reference->group_id)->lockForUpdate()->first();
            }
            $match = TournamentMatch::whereKey($matchId)->where('tournament_year', $year)->lockForUpdate()->first();
            if (! $match || $match->version !== $version) {
                $this->reject('tournament', 'This match changed while you were editing. Reload before saving.');
            }
            if ($match->stage !== 'G') {
                $this->reject('stage', 'Knockout scheduling is not available until eligibility checks are ready.');
            }
            $details = $this->schedule($schedule, [$match->team1_id, $match->team2_id]);
            if (array_key_exists('batting_first_id', $details)
                && $details['batting_first_id'] !== $match->batting_first_id
                && ($match->structure_locked_at !== null || $match->result_published_at !== null)) {
                $this->reject('batting_first_id', 'The batting-first team is locked after result publication.');
            }
            if (isset($details['status']) && $details['status'] !== $match->status
                && ($match->status === 'finished' || $match->structure_locked_at !== null
                    || $match->result_published_at !== null || $match->player_stats_published_at !== null
                    || $match->result_type !== null || $match->winner_id !== null)) {
                // Later publication services must update standings/player totals
                // and check downstream fixtures atomically before allowing this.
                $this->reject('status', 'Published or finished results cannot change status through scheduling.');
            }
            foreach ($details as $field => $value) {
                $match->$field = $value;
            }
            if ($match->isDirty()) {
                $match->version++;
                $match->updated_by = $actor->id;
                $match->save();
            }

            return $match->refresh();
        });
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
    private function schedule(array $input, array $teamIds): array
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
            'status' => ['sometimes', 'required', Rule::in(['upcoming', 'live'])],
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
