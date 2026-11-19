<?php

namespace App\Services\Tournament;

use App\Helpers\CompanyAuth;
use App\Models\GroupTeam;
use App\Models\Team;
use App\Models\TournamentGroup;
use App\Models\TournamentMatch;
use App\Models\User;
use App\Support\ScorerWriteThrottle;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ScorerStandingsService
{
    public function __construct(
        private TournamentWriteService $writes,
        private GroupStandingsCalculator $calculator,
        private GroupFixtureCoverage $coverage,
        private StandingsRanker $ranker,
    ) {}

    public function listing(int $year): array
    {
        $this->authorize();
        Validator::make(compact('year'), ['year' => ['integer', 'between:1,9999']])->validate();

        return TournamentGroup::where('tournament_year', $year)->orderBy('name')->orderBy('id')
            ->with(['assignments' => fn ($query) => $query->where('tournament_year', $year)
                ->orderByDesc('points')->orderByRaw('CASE WHEN nrr IS NULL THEN 1 ELSE 0 END')->orderByDesc('nrr')->orderBy('team_id'),
                'assignments.team:id,team_name',
                'matches' => fn ($query) => $query->where('tournament_year', $year)
                    ->select(['id', 'group_id', 'tournament_year', 'stage', 'team1_id', 'team2_id', 'status', 'result_published_at'])])
            ->get()->map(fn ($group) => [
                'id' => $group->id, 'name' => $group->name, 'version' => $group->version,
                'coverage' => $this->coverage->calculate($group, $group->assignments, $group->matches),
                'teams' => $this->ranker->rank($group->assignments->map(fn ($row) => [
                    'id' => $row->team_id, 'name' => $row->team->team_name,
                    ...$row->only(['played', 'wins', 'losses', 'ties', 'no_results', 'base_points', 'points', 'nrr']),
                    'adjustments' => $row->point_adjustments ?? [],
                ])->all()),
            ])->all();
    }

    /** Append an official correction; never edit or delete previous history. */
    public function adjust(int $year, int $groupId, int $version, int $teamId, array $input): void
    {
        ScorerWriteThrottle::hit();
        if (array_diff(array_keys($input), ['points', 'reason'])) {
            $this->reject('The adjustment contains unsupported fields.');
        }
        Validator::make(compact('year', 'groupId', 'version', 'teamId'), [
            'year' => ['integer', 'between:1,9999'], 'groupId' => ['integer', 'min:1'],
            'version' => ['integer', 'min:1'], 'teamId' => ['integer', 'min:1'],
        ])->validate();
        if (isset($input['reason']) && is_string($input['reason'])) {
            $input['reason'] = trim($input['reason']);
        }
        $data = Validator::make($input, [
            'points' => ['required', 'integer', 'between:-2147483647,2147483647', 'not_in:0'],
            'reason' => ['required', 'string', 'max:1000'],
        ])->validate();

        $this->writes->run($year, function () use ($year, $groupId, $version, $teamId, $data) {
            $actor = $this->authorize(true);
            $group = TournamentGroup::whereKey($groupId)->where('tournament_year', $year)->lockForUpdate()->first();
            if (! $group || $group->version !== $version) {
                $this->reject('This group changed. Reload the standings before adding an adjustment.');
            }
            $matches = TournamentMatch::where('group_id', $groupId)->where('tournament_year', $year)->orderBy('id')->lockForUpdate()->get();
            $teamIds = GroupTeam::where('group_id', $groupId)->where('tournament_year', $year)->pluck('team_id');
            Team::whereIn('id', $teamIds)->orderBy('id')->lockForUpdate()->get();
            $rows = GroupTeam::where('group_id', $groupId)->where('tournament_year', $year)->orderBy('id')->lockForUpdate()->get();
            $row = $rows->firstWhere('team_id', $teamId);
            if (! $row) {
                $this->reject('Choose a team assigned to this group.');
            }
            $history = $row->point_adjustments ?? [];
            if (count($history) >= 100) {
                $this->reject('This team has reached the adjustment history limit. Contact the administrator.');
            }
            $history[] = ['points' => (int) $data['points'], 'reason' => $data['reason'],
                'actor_id' => $actor->id, 'recorded_at' => now()->utc()->toIso8601String()];
            $row->point_adjustments = $history;
            $calculated = $this->calculator->calculate($group, $rows, $matches);
            foreach ($rows as $assignment) {
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
        });
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
        throw ValidationException::withMessages(['tournament' => $message]);
    }
}
