<?php

namespace Tests\Support;

use App\Models\Team;
use App\Services\Tournament\MatchStructureValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;

trait MatchStructureChecks
{
    private function fixture(int $year = 2026, string $name = 'A', int $count = 2): array
    {
        $group = DB::table('tournament_groups')->insertGetId(['tournament_year' => $year, 'name' => $name]);
        $teams = Team::factory()->count($count)->create(['approved' => true]);
        foreach ($teams as $team) {
            DB::table('group_teams')->insert(['tournament_year' => $year, 'group_id' => $group, 'team_id' => $team->id]);
        }

        return ['stage' => 'G', 'group_id' => $group, 'team1_id' => $teams[0]->id,
            'team2_id' => $teams[1]->id, 'balls_per_over' => 4];
    }

    private function rejected(array $input, string $field, int $year = 2026): void
    {
        try {
            app(MatchStructureValidator::class)->validate($year, $input);
            $this->fail('Expected invalid match structure to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    public function test_valid_formats_return_only_structure_without_writing_or_reordering_teams(): void
    {
        $input = $this->fixture();
        $before = DB::table('teams')->orderBy('id')->get()->toJson();
        foreach ([4, 6] as $balls) {
            $input['balls_per_over'] = (string) $balls;
            $result = app(MatchStructureValidator::class)->validate(2026, $input);
            $this->assertSame($input['team1_id'], $result['team1_id']);
            $this->assertSame($input['team2_id'], $result['team2_id']);
            $this->assertSame($balls, $result['balls_per_over']);
            $this->assertSame(5, $result['overs_per_innings']);
            $this->assertSame(7, $result['max_wickets']);
            $this->assertNull($result['bracket_position']);
            $this->assertCount(8, $result);
        }
        $this->assertDatabaseCount('matches', 0);
        $this->assertSame($before, DB::table('teams')->orderBy('id')->get()->toJson());
    }

    public static function invalidFields(): array
    {
        return [
            ['stage', 'unknown', 'stage'], ['stage', null, 'stage'],
            ['balls_per_over', 5, 'balls_per_over'], ['balls_per_over', null, 'balls_per_over'],
            ['balls_per_over', 4.5, 'balls_per_over'], ['overs_per_innings', 6, 'overs_per_innings'],
            ['overs_per_innings', null, 'overs_per_innings'], ['max_wickets', 10, 'max_wickets'],
            ['group_id', null, 'group_id'], ['group_id', 999999, 'group_id'],
            ['team1_id', 999999, 'team1_id'], ['team2_id', 999999, 'team2_id'],
            ['team1_id', [], 'team1_id'], ['bracket_position', 1, 'bracket_position'],
            ['winner_id', 1, 'tournament'], ['created_by', 1, 'tournament'],
            ['team1_runs', 0, 'tournament'], ['tournament_year', 2027, 'tournament'],
        ];
    }

    #[DataProvider('invalidFields')]
    public function test_invalid_structure_and_unrelated_fields_are_rejected(string $key, mixed $value, string $field): void
    {
        $input = $this->fixture();
        $input[$key] = $value;
        $this->rejected($input, $field);
        $this->assertDatabaseCount('matches', 0);
    }

    public function test_distinct_approved_teams_must_belong_to_the_selected_group_and_year(): void
    {
        $a = $this->fixture();
        $b = $this->fixture(2026, 'B');
        $nextYear = $this->fixture(2027);
        $this->rejected(array_replace($a, ['team2_id' => (string) $a['team1_id']]), 'team2_id');
        $this->rejected(array_replace($a, ['team2_id' => $b['team2_id']]), 'team2_id');
        $this->rejected(array_replace($a, ['team1_id' => $nextYear['team1_id']]), 'team1_id');
        $this->rejected($a, 'group_id', 2027);
        Team::whereKey($a['team2_id'])->update(['approved' => false]);
        $this->rejected($a, 'team2_id');
        $unassigned = Team::factory()->create(['approved' => true]);
        $this->rejected(array_replace($a, ['team2_id' => $unassigned->id]), 'team2_id');
        $this->assertDatabaseCount('matches', 0);
    }

    public function test_existing_pair_is_rejected_in_either_order_even_when_already_finished(): void
    {
        $input = $this->fixture();
        DB::table('matches')->insert($input + ['tournament_year' => 2026, 'status' => 'finished']);
        $this->rejected($input, 'team2_id');
        $this->rejected(array_replace($input, ['team1_id' => $input['team2_id'], 'team2_id' => $input['team1_id']]), 'team2_id');
        $this->assertDatabaseCount('matches', 1);
    }

    public function test_group_sizes_counts_and_fixture_coverage_are_not_hardcoded(): void
    {
        foreach (range(1, 8) as $number) {
            $input = $this->fixture(2026, 'Group '.$number, 6);
            $members = DB::table('group_teams')->where('group_id', $input['group_id'])->pluck('team_id');
            for ($i = 0; $i < $members->count(); $i++) {
                for ($j = $i + 1; $j < $members->count(); $j++) {
                    $pair = array_replace($input, ['team1_id' => $members[$i], 'team2_id' => $members[$j]]);
                    $validated = app(MatchStructureValidator::class)->validate(2026, $pair);
                    DB::table('matches')->insert($validated + ['tournament_year' => 2026]);
                }
            }
        }
        $this->assertDatabaseCount('tournament_groups', 8);
        $this->assertDatabaseCount('matches', 120);
    }

    public static function knockoutStages(): array
    {
        return [['QF', 4], ['SF', 2], ['F', 1]];
    }

    #[DataProvider('knockoutStages')]
    public function test_knockout_shape_and_current_eligibility_are_checked(string $stage, int $slots): void
    {
        $input = array_replace($this->fixture(), ['stage' => $stage, 'group_id' => null, 'bracket_position' => $slots]);
        $this->rejected($input, 'tournament');
        $this->rejected(array_replace($input, ['bracket_position' => $slots + 1]), 'bracket_position');
        $this->rejected(array_replace($input, ['bracket_position' => null]), 'bracket_position');
        $this->rejected(array_replace($input, ['group_id' => 1]), 'group_id');
        $this->assertDatabaseCount('matches', 0);
    }

    public function test_invalid_tournament_years_are_rejected(): void
    {
        $input = $this->fixture();
        foreach ([0, 10000] as $year) {
            $this->rejected($input, 'year', $year);
        }
    }
}
