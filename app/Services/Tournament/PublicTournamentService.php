<?php

namespace App\Services\Tournament;

use App\Models\GroupTeam;
use App\Models\Team;
use App\Models\TournamentGroup;
use App\Models\TournamentMatch;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Anonymous, feature-gated reads. Return explicit arrays, never Eloquent models. */
class PublicTournamentService
{
    private const PAGE_SIZE = 12;

    private const MAX_GROUPS = 128;

    private const MAX_TEAMS = 2048;

    private const MAX_FIXTURES = 8192;

    private const MATCH_FIELDS = ['id', 'tournament_year', 'group_id', 'stage', 'bracket_position',
        'team1_id', 'team2_id', 'balls_per_over', 'overs_per_innings', 'max_wickets',
        'scheduled_at', 'venue', 'display_number', 'status', 'result_published_at',
        'result_type', 'result_method', 'winner_id', 'batting_first_id',
        'team1_runs', 'team1_wickets', 'team1_deliveries', 'team2_runs', 'team2_wickets', 'team2_deliveries'];

    public function __construct(
        private PublicReadSnapshot $snapshots,
        private MatchResultDescription $descriptions,
        private GroupFixtureCoverage $coverage,
        private KnockoutEligibility $eligibility,
    ) {}

    public function matches(int $year, array $filters = []): array
    {
        $this->validateYear($year);
        if (array_diff(array_keys($filters), ['stage', 'status', 'group_id', 'sort', 'page'])) {
            throw ValidationException::withMessages(['tournament' => 'Select valid match filters.']);
        }
        $filters = array_map(fn ($value) => $value === '' ? null : $value, $filters);
        $filters = Validator::make($filters, [
            'stage' => ['nullable', 'string', Rule::in(['G', 'QF', 'SF', 'F'])],
            'status' => ['nullable', 'string', Rule::in(['upcoming', 'live', 'finished'])],
            'group_id' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable', 'string', Rule::in(['schedule', 'newest'])],
            'page' => ['nullable', 'integer', 'between:1,10000'],
        ])->validate();
        $filters = ['stage' => $filters['stage'] ?? null, 'status' => $filters['status'] ?? null,
            'group_id' => isset($filters['group_id']) ? (int) $filters['group_id'] : null,
            'sort' => $filters['sort'] ?? 'schedule', 'page' => (int) ($filters['page'] ?? 1)];

        return $this->snapshots->run(function () use ($year, $filters) {
            $groups = $this->groups($year);
            $query = $this->visibleMatches($year);
            foreach (['stage', 'status', 'group_id'] as $field) {
                if ($filters[$field] !== null) {
                    $query->where($field, $filters[$field]);
                }
            }
            $total = (clone $query)->count();
            if ($filters['sort'] === 'schedule') {
                $query->orderByRaw('CASE WHEN scheduled_at IS NULL THEN 1 ELSE 0 END')->orderBy('scheduled_at')->orderBy('id');
            } else {
                $query->orderByDesc('id');
            }
            $matches = $query->select(self::MATCH_FIELDS)->offset(($filters['page'] - 1) * self::PAGE_SIZE)->limit(self::PAGE_SIZE)->get();
            $teams = $this->teams($matches->flatMap(fn ($match) => [$match->team1_id, $match->team2_id])->all());

            return ['year' => $year, 'groups' => $groups->map->only(['id', 'name'])->all(), 'filters' => $filters,
                'matches' => $matches->map(fn ($match) => $this->presentMatch($match, $teams, $groups))->all(),
                'pagination' => ['page' => $filters['page'], 'per_page' => self::PAGE_SIZE, 'total' => $total,
                    'last_page' => max(1, (int) ceil($total / self::PAGE_SIZE))]];
        });
    }

    public function standings(int $year): array
    {
        $this->validateYear($year);

        return $this->snapshots->run(function () use ($year) {
            $groups = $this->groups($year);
            $rows = $this->assignments($year);
            $teams = $this->teams($rows->pluck('team_id')->all());
            // Coverage reads identities/status only, not private or draft figures.
            $matches = $this->bounded(TournamentMatch::where('tournament_year', $year)->where('stage', 'G')->orderBy('id')
                ->select(['id', 'tournament_year', 'group_id', 'stage', 'team1_id', 'team2_id', 'status', 'result_published_at']), self::MAX_FIXTURES);
            $unequal = $groups->map(fn ($group) => $rows->where('group_id', $group->id)->count())->unique()->count() > 1;
            $result = $runners = [];
            foreach ($groups as $group) {
                $assigned = $rows->where('group_id', $group->id);
                $entries = $this->rank($assigned->map(fn ($row) => [
                    'id' => $row->team_id, 'name' => $teams->get($row->team_id)?->team_name,
                    ...$row->only(['played', 'wins', 'losses', 'ties', 'no_results', 'points', 'nrr', 'qualified']),
                ])->all());
                $coverage = $this->coverage->calculate($group, $assigned, $matches);
                $unknown = collect($entries)->contains(fn ($row) => $row['nrr'] === null);
                $tied = count(array_unique(array_column($entries, 'rank'))) !== count($entries);
                $result[] = ['id' => $group->id, 'name' => $group->name, 'teams' => $entries,
                    'coverage' => $coverage, 'undefined_nrr' => $unknown, 'tied' => $tied];
                if (count($entries) >= 2) {
                    foreach ($entries as $entry) {
                        if ($entry['rank'] === $entries[1]['rank']) {
                            $runners[] = ['id' => $entry['id'], 'name' => $entry['name'], 'group_id' => $group->id,
                                'group_name' => $group->name, 'group_rank' => $entry['rank'], 'played' => $entry['played'],
                                'points' => $entry['points'], 'nrr' => $entry['nrr'],
                                'provisional' => ! $coverage['complete'] || $unknown || $tied || $unequal];
                        }
                    }
                }
            }

            return ['year' => $year, 'groups' => $result, 'unequal_groups' => $unequal, 'runner_up_guidance' => $this->rank($runners)];
        });
    }

    public function bracket(int $year): array
    {
        $this->validateYear($year);

        return $this->snapshots->run(function () use ($year) {
            $matches = $this->bounded($this->visibleMatches($year)->whereIn('stage', ['QF', 'SF', 'F'])
                ->orderBy('id')->select(self::MATCH_FIELDS), self::MAX_FIXTURES);
            $rows = $this->assignments($year);
            $teams = $this->teams(array_merge($rows->pluck('team_id')->all(),
                $matches->flatMap(fn ($match) => [$match->team1_id, $match->team2_id])->all()));
            $rounds = [];
            foreach (['QF', 'SF', 'F'] as $stage) {
                $rounds[$stage] = array_map(function ($position) use ($matches, $teams) {
                    $match = $matches->firstWhere('id', $position['match_id']);
                    $sides = [];
                    foreach (['team1', 'team2'] as $side) {
                        $team = $position[$side];
                        // Current eligibility/approval is private scoring guidance.
                        $sides[$side] = $team === null ? null : array_intersect_key($team, array_flip(['id', 'name', 'source_match_id']));
                    }

                    return ['position' => $position['position'], 'label' => $position['label'], ...$sides,
                        'match' => $match ? $this->presentMatch($match, $teams, collect()) : null];
                }, $this->eligibility->round($year, $stage, $teams, $rows, $matches));
            }

            return ['year' => $year, 'rounds' => $rounds];
        });
    }

    private function presentMatch(TournamentMatch $match, $teams, $groups): array
    {
        $a = $teams->get($match->team1_id)?->team_name ?? 'Team unavailable';
        $b = $teams->get($match->team2_id)?->team_name ?? 'Team unavailable';
        $result = null;
        if ($match->status === 'finished' && $match->result_published_at !== null) {
            $official = $match->only(['result_type', 'result_method', 'winner_id', 'batting_first_id',
                'team1_runs', 'team1_wickets', 'team2_runs', 'team2_wickets']);
            $result = ['type' => $match->result_type, 'method' => $match->result_method, 'winner_id' => $match->winner_id,
                'description' => $this->descriptions->describe($match, $official, $a, $b),
                'published_at' => $match->result_published_at->toIso8601String()];
            foreach (['team1', 'team2'] as $side) {
                $balls = $match->{$side.'_deliveries'};
                $result[$side] = ['runs' => $match->{$side.'_runs'}, 'wickets' => $match->{$side.'_wickets'},
                    'overs' => $balls === null ? null : intdiv($balls, $match->balls_per_over),
                    'balls' => $balls === null ? null : $balls % $match->balls_per_over];
            }
        }

        return ['id' => $match->id, 'stage' => $match->stage, 'bracket_position' => $match->bracket_position,
            'group' => $match->group_id === null ? null : ['id' => $match->group_id, 'name' => $groups->firstWhere('id', $match->group_id)?->name],
            'team1' => ['id' => $match->team1_id, 'name' => $a], 'team2' => ['id' => $match->team2_id, 'name' => $b],
            'status' => $match->status, 'balls_per_over' => $match->balls_per_over,
            'overs_per_innings' => $match->overs_per_innings, 'max_wickets' => $match->max_wickets,
            'scheduled_at' => $match->scheduled_at?->toIso8601String(), 'venue' => $match->venue,
            'display_number' => $match->display_number, 'result' => $result];
    }

    private function visibleMatches(int $year): Builder
    {
        return TournamentMatch::where('tournament_year', $year)->whereIn('stage', ['G', 'QF', 'SF', 'F'])
            ->where(fn ($query) => $query->whereIn('status', ['upcoming', 'live'])
                ->orWhere(fn ($finished) => $finished->where('status', 'finished')->whereNotNull('result_published_at')));
    }

    private function groups(int $year)
    {
        return $this->bounded(TournamentGroup::where('tournament_year', $year)->orderBy('name')->orderBy('id')
            ->select(['id', 'name', 'tournament_year']), self::MAX_GROUPS);
    }

    private function assignments(int $year)
    {
        return $this->bounded(GroupTeam::where('tournament_year', $year)->orderBy('id')->select([
            'id', 'group_id', 'tournament_year', 'team_id', 'played', 'wins', 'losses', 'ties', 'no_results', 'points', 'nrr', 'qualified',
        ]), self::MAX_TEAMS);
    }

    private function teams(array $ids)
    {
        return $this->bounded(Team::whereIn('id', array_values(array_unique($ids)))->orderBy('id')
            ->select(['id', 'team_name', 'approved']), self::MAX_TEAMS)->keyBy('id');
    }

    private function bounded(Builder $query, int $limit)
    {
        $rows = $query->limit($limit + 1)->get();
        abort_if($rows->count() > $limit, 503, 'Tournament data is temporarily unavailable.');

        return $rows;
    }

    private function rank(array $entries): array
    {
        $compare = function ($a, $b): int {
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

    private function validateYear(int $year): void
    {
        abort_unless(config('tournament.enabled') === true, 404);
        Validator::make(compact('year'), ['year' => ['integer', 'between:1,9999']])->validate();
    }
}
