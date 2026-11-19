<?php

namespace App\Services\Tournament;

use App\Helpers\CompanyAuth;
use App\Models\GroupTeam;
use App\Models\Team;
use App\Models\TournamentGroup;
use App\Models\TournamentMatch;
use App\Models\User;
use App\Support\ScorerWriteThrottle;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class QualificationService
{
    public function __construct(private TournamentWriteService $writes, private GroupFixtureCoverage $coverage) {}

    /** Private guidance and an optimistic revision, not an approval stage. */
    public function selection(int $year): array
    {
        return $this->context($year, fn ($groups, $matches, $teams, $rows) => $this->snapshot($year, $groups, $matches, $teams, $rows));
    }

    /** Replace the selected set, allowing zero/partial selections and any rank. */
    public function save(int $year, array $teamIds, string $revision): array
    {
        ScorerWriteThrottle::hit();
        if (! array_is_list($teamIds)) {
            $this->reject('Supply a list of selected team IDs.');
        }
        Validator::make(compact('teamIds', 'revision'), [
            'teamIds' => ['array', 'max:'.$this->capacity()], 'teamIds.*' => ['integer', 'min:1', 'distinct'],
            'revision' => ['regex:/\A[a-f0-9]{64}\z/'],
        ])->validate();
        $teamIds = array_map('intval', $teamIds);
        sort($teamIds);

        return $this->context($year, function ($groups, $matches, $teams, $rows) use ($year, $teamIds, $revision) {
            $current = $this->snapshot($year, $groups, $matches, $teams, $rows);
            if (! hash_equals($current['revision'], $revision)) {
                $this->reject('Qualification guidance or selections changed. Reload the latest choices before saving.');
            }
            foreach ($teamIds as $id) {
                if (! $rows->contains('team_id', $id) || ! $teams->firstWhere('id', $id)?->approved) {
                    $this->reject('Select approved teams assigned to this tournament year.');
                }
            }
            $removed = array_diff($current['selected'], $teamIds);
            foreach ($matches->whereIn('stage', ['QF', 'SF', 'F']) as $match) {
                if (in_array($match->team1_id, $removed, true) || in_array($match->team2_id, $removed, true)) {
                    $this->reject('A selected team already appears in a knockout fixture. Resolve that fixture before removing its qualification.');
                }
            }
            $changedGroups = [];
            foreach ($rows as $row) {
                $qualified = in_array($row->team_id, $teamIds, true);
                if ($row->qualified !== $qualified) {
                    $row->qualified = $qualified;
                    $row->version++;
                    $row->save();
                    $changedGroups[$row->group_id] = true;
                }
            }
            foreach ($groups as $group) {
                if (isset($changedGroups[$group->id])) {
                    $group->version++;
                    $group->save();
                }
            }

            return $this->snapshot($year, $groups, $matches, $teams, $rows);
        });
    }

    private function context(int $year, callable $callback): array
    {
        $this->authorize();
        Validator::make(compact('year'), ['year' => ['integer', 'between:1,9999']])->validate();

        return $this->writes->run($year, function () use ($year, $callback) {
            $this->authorize(true);
            $groups = TournamentGroup::where('tournament_year', $year)->orderBy('id')->lockForUpdate()->get();
            $matches = TournamentMatch::where('tournament_year', $year)->orderBy('id')->lockForUpdate()
                ->get(['id', 'group_id', 'tournament_year', 'stage', 'team1_id', 'team2_id', 'status', 'result_published_at', 'version']);
            $ids = GroupTeam::where('tournament_year', $year)->pluck('team_id');
            $teams = Team::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get(['id', 'team_name', 'approved']);
            $rows = GroupTeam::where('tournament_year', $year)->orderBy('id')->lockForUpdate()
                ->get(['id', 'group_id', 'tournament_year', 'team_id', 'played', 'points', 'nrr', 'qualified', 'version']);

            return $callback($groups, $matches, $teams, $rows);
        });
    }

    private function snapshot(int $year, $groups, $matches, $teams, $rows): array
    {
        $result = $runners = [];
        $sizes = $groups->map(fn ($group) => $rows->where('group_id', $group->id)->count())->unique();
        foreach ($groups as $group) {
            $assignments = $rows->where('group_id', $group->id);
            $entries = $assignments->map(fn ($row) => ['id' => $row->team_id,
                'name' => $teams->firstWhere('id', $row->team_id)?->team_name,
                'approved' => (bool) $teams->firstWhere('id', $row->team_id)?->approved,
                ...$row->only(['played', 'points', 'nrr', 'qualified', 'version'])])->all();
            $entries = $this->rank($entries);
            $coverage = $this->coverage->calculate($group, $assignments, $matches);
            $undefined = collect($entries)->contains(fn ($team) => $team['nrr'] === null);
            $tied = count(array_unique(array_column($entries, 'rank'))) !== count($entries);
            $result[] = ['id' => $group->id, 'name' => $group->name, 'version' => $group->version,
                'coverage' => $coverage, 'teams' => $entries, 'undefined_nrr' => $undefined, 'tied' => $tied];
            // Include everyone occupying the second position, including tied
            // first-place teams; never break a sporting tie by team ID/name.
            if (count($entries) >= 2) {
                $secondRank = $entries[1]['rank'];
                foreach ($entries as $entry) {
                    if ($entry['rank'] === $secondRank) {
                        $runners[] = $entry + ['group_rank' => $entry['rank'], 'group_id' => $group->id, 'group_name' => $group->name,
                            'provisional' => ! $coverage['complete'] || $undefined || $tied || $sizes->count() > 1];
                    }
                }
            }
        }
        $selected = $rows->where('qualified', true)->pluck('team_id')->sort()->values()->all();
        $snapshot = ['year' => $year, 'groups' => $result, 'runner_up_guidance' => $this->rank($runners),
            'unequal_groups' => $sizes->count() > 1, 'capacity' => $this->capacity(), 'selected' => $selected];
        $snapshot['revision'] = hash('sha256', json_encode([$snapshot, $matches->map->only(['id', 'version'])->all()], JSON_THROW_ON_ERROR));

        return $snapshot;
    }

    private function rank(array $entries): array
    {
        $compare = function (array $a, array $b): int {
            if ($a['points'] !== $b['points']) {
                return $b['points'] <=> $a['points'];
            }
            if ($a['nrr'] === null || $b['nrr'] === null) {
                return ($a['nrr'] === null ? 1 : 0) <=> ($b['nrr'] === null ? 1 : 0);
            }

            return BigDecimal::of($b['nrr'])->compareTo(BigDecimal::of($a['nrr']));
        };
        usort($entries, fn ($a, $b) => $compare($a, $b) ?: $a['id'] <=> $b['id']);
        foreach ($entries as $index => &$entry) {
            $entry['rank'] = $index > 0 && $compare($entries[$index - 1], $entry) === 0 ? $entries[$index - 1]['rank'] : $index + 1;
        }
        unset($entry);

        return $entries;
    }

    private function capacity(): int
    {
        return 2 * (int) config('tournament.knockout_slots.QF');
    }

    private function authorize(bool $lock = false): void
    {
        abort_unless(config('tournament.enabled') === true, 404);
        $query = User::whereKey(CompanyAuth::user()?->id);
        if ($lock) {
            $query->sharedLock();
        }
        abort_unless($query->first()?->is_scorer, 403);
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['tournament' => $message]);
    }
}
