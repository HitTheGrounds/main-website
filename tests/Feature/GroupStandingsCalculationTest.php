<?php

namespace Tests\Feature;

use App\Models\GroupTeam;
use App\Models\TournamentGroup;
use App\Models\TournamentMatch;
use App\Services\Tournament\GroupStandingsCalculator;
use App\Services\Tournament\MatchResultValidator;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GroupStandingsCalculationTest extends TestCase
{
    private function group(): TournamentGroup
    {
        return (new TournamentGroup)->forceFill(['id' => 1, 'tournament_year' => 2026]);
    }

    private function members(int $count = 2): array
    {
        return array_map(fn ($id) => (new GroupTeam)->forceFill(['id' => $id, 'group_id' => 1,
            'tournament_year' => 2026, 'team_id' => $id, 'point_adjustments' => [], 'qualified' => $id === 1]), range(1, $count));
    }

    private function match(array $changes = []): TournamentMatch
    {
        return (new TournamentMatch)->forceFill(array_replace([
            'id' => 1, 'tournament_year' => 2026, 'group_id' => 1, 'stage' => 'G', 'status' => 'finished',
            'result_published_at' => '2026-11-15 10:00:00', 'team1_id' => 1, 'team2_id' => 2,
            'result_type' => 'win', 'result_method' => 'normal', 'winner_id' => 1,
            'balls_per_over' => 4, 'overs_per_innings' => 5, 'max_wickets' => 11,
            'team1_runs' => 100, 'team2_runs' => 90, 'team1_wickets' => 3, 'team2_wickets' => 11,
            'team1_deliveries' => 20, 'team2_deliveries' => 12,
        ], $changes));
    }

    private function calculate(array $matches, ?array $members = null): array
    {
        return app(GroupStandingsCalculator::class)->calculate($this->group(), $members ?? $this->members(), $matches);
    }

    public function test_empty_group_returns_zero_totals_and_undefined_nrr_without_changing_qualification_or_history(): void
    {
        $members = $this->members();
        $members[0]->point_adjustments = [['points' => -3, 'reason' => 'Committee penalty', 'actor' => 7, 'at' => '2026-11-15T10:00:00Z']];
        $before = array_map(fn ($member) => $member->getAttributes(), $members);
        $result = $this->calculate([], $members);
        $a = $result['rows'][1];
        $this->assertSame(0, $a['played']);
        $this->assertSame(0, $a['base_points']);
        $this->assertSame(-3, $a['points']);
        $this->assertSame('0.00000000', $a['nrr_overs_faced']);
        $this->assertNull($a['nrr']);
        $this->assertArrayNotHasKey('qualified', $a);
        $this->assertArrayNotHasKey('point_adjustments', $a);
        $this->assertSame([], $result['nrr_exclusions']);
        $this->assertSame($before, array_map(fn ($member) => $member->getAttributes(), $members));
    }

    public function test_win_loss_points_and_all_out_denominators_preserve_actual_source_deliveries(): void
    {
        $match = $this->match();
        $before = $match->getAttributes();
        $rows = $this->calculate([$match])['rows'];
        $this->assertSame([1, 1, 0, 2], [$rows[1]['played'], $rows[1]['wins'], $rows[1]['losses'], $rows[1]['points']]);
        $this->assertSame([1, 0, 1, 0], [$rows[2]['played'], $rows[2]['wins'], $rows[2]['losses'], $rows[2]['points']]);
        $this->assertSame(100, $rows[1]['runs_for']);
        $this->assertSame(90, $rows[1]['runs_against']);
        $this->assertSame('5.00000000', $rows[1]['nrr_overs_bowled']);
        $this->assertSame('2.000000', $rows[1]['nrr']);
        $this->assertSame('-2.000000', $rows[2]['nrr']);
        $this->assertSame($before, $match->getAttributes());
        $match->team2_wickets = 10;
        $this->assertSame('-10.000000', $this->calculate([$match])['rows'][1]['nrr']);
    }

    public function test_mixed_formats_sum_each_innings_overs_instead_of_averaging_match_nrr(): void
    {
        $first = $this->match(['team1_runs' => 60, 'team2_runs' => 48, 'team1_deliveries' => 12,
            'team2_deliveries' => 12, 'team2_wickets' => 4]);
        $second = $this->match(['id' => 2, 'team2_id' => 3, 'winner_id' => 3, 'balls_per_over' => 6,
            'team1_runs' => 40, 'team2_runs' => 50, 'team1_deliveries' => 12, 'team2_deliveries' => 12, 'team2_wickets' => 2]);
        $a = $this->calculate([$first, $second], $this->members(3))['rows'][1];
        $this->assertSame(2, $a['played']);
        $this->assertSame(2, $a['points']);
        $this->assertSame(100, $a['runs_for']);
        $this->assertSame(98, $a['runs_against']);
        $this->assertSame('5.00000000', $a['nrr_overs_faced']);
        $this->assertSame('0.400000', $a['nrr']);
    }

    public function test_ties_and_no_results_get_points_but_abandoned_partial_scores_do_not_affect_nrr(): void
    {
        $tie = $this->match(['result_type' => 'tie', 'winner_id' => null, 'team1_runs' => 10, 'team2_runs' => 10,
            'team1_deliveries' => 4, 'team2_deliveries' => 4, 'team2_wickets' => 1]);
        $noResult = $this->match(['id' => 2, 'team2_id' => 3, 'result_type' => 'no_result', 'result_method' => null,
            'winner_id' => null, 'team1_runs' => null, 'team2_runs' => null]);
        $abandoned = $this->match(['id' => 3, 'team2_id' => 4, 'result_type' => 'abandoned', 'result_method' => null,
            'winner_id' => null, 'team1_runs' => 200, 'team2_runs' => null]);
        $result = $this->calculate([$tie, $noResult, $abandoned], $this->members(4));
        $a = $result['rows'][1];
        $this->assertSame([3, 1, 2, 3], [$a['played'], $a['ties'], $a['no_results'], $a['points']]);
        $this->assertSame(10, $a['runs_for']);
        $this->assertSame('1.00000000', $a['nrr_overs_faced']);
        $this->assertSame('0.000000', $a['nrr']);
        $this->assertNull($result['rows'][3]['nrr']);
        $this->assertSame([2, 3], array_keys($result['nrr_exclusions']));
    }

    public function test_only_published_finished_group_results_in_the_selected_year_and_group_count(): void
    {
        $matches = [$this->match(['id' => 1, 'result_published_at' => null, 'draft_result' => ['team1_runs' => 999]]),
            $this->match(['id' => 2, 'status' => 'live']), $this->match(['id' => 3, 'tournament_year' => 2027]),
            $this->match(['id' => 4, 'group_id' => 2]), $this->match(['id' => 5, 'stage' => 'QF']),
            $this->match(['id' => 6])];
        $rows = $this->calculate($matches)['rows'];
        $this->assertSame(1, $rows[1]['played']);
        $this->assertSame(2, $rows[1]['points']);
        $this->assertSame(100, $rows[1]['runs_for']);
        $this->assertSame('2.000000', $rows[1]['nrr']);
    }

    public function test_recalculation_and_correction_do_not_double_count_or_erase_penalties(): void
    {
        $members = $this->members();
        $members[0]->point_adjustments = [['points' => -5, 'reason' => 'Penalty'], ['points' => 1, 'reason' => 'Correction']];
        $members[0]->played = 99;
        $members[0]->base_points = 999;
        $members[0]->runs_for = 777;
        $match = $this->match();
        $first = $this->calculate([$match], $members);
        $this->assertSame($first, $this->calculate([$match], $members));
        $this->assertSame(-2, $first['rows'][1]['points']);
        $this->assertSame(1, $first['rows'][1]['played']);
        $match->team1_runs = 70;
        $match->winner_id = 2;
        $corrected = $this->calculate([$match], $members);
        $this->assertSame([0, 1, 0, -4], [$corrected['rows'][1]['wins'], $corrected['rows'][1]['losses'],
            $corrected['rows'][1]['base_points'], $corrected['rows'][1]['points']]);
        $this->assertSame(-5, $members[0]->point_adjustments[0]['points']);
        $match->status = 'upcoming';
        $this->assertSame(0, $this->calculate([$match], $members)['rows'][1]['played']);
    }

    public function test_adjusted_nrr_uses_official_figures_without_reapplying_the_actual_all_out_rule(): void
    {
        $match = $this->match(['result_method' => 'revised_target', 'winner_id' => 2, 'team1_wickets' => 11,
            'nrr_inputs' => ['included' => true, 'reason' => 'Official adjusted figures',
                'team1' => ['runs' => 70, 'deliveries' => 16], 'team2' => ['runs' => 61, 'deliveries' => 14]]]);
        $a = $this->calculate([$match])['rows'][1];
        $this->assertSame(70, $a['runs_for']);
        $this->assertSame(61, $a['runs_against']);
        $this->assertSame('4.00000000', $a['nrr_overs_faced']);
        $this->assertSame('3.50000000', $a['nrr_overs_bowled']);
        $this->assertSame('0.071429', $a['nrr']);
        $this->assertSame(0, $a['points']);
        $match->nrr_inputs = ['included' => false, 'reason' => 'Official exclusion'];
        $result = $this->calculate([$match]);
        $this->assertSame(2, $result['rows'][2]['points']);
        $this->assertSame(0, $result['rows'][1]['runs_for']);
        $this->assertNull($result['rows'][1]['nrr']);
        $this->assertSame('Official exclusion', $result['nrr_exclusions'][1]);
    }

    public function test_zero_denominators_are_undefined_and_official_unknown_innings_are_explicitly_excluded(): void
    {
        $match = $this->match(['team2_runs' => 0, 'team2_deliveries' => 0, 'team2_wickets' => 0]);
        $result = $this->calculate([$match]);
        $this->assertNull($result['rows'][1]['nrr']);
        $this->assertSame(0, $result['rows'][2]['runs_for']);
        $this->assertSame('0.00000000', $result['rows'][1]['nrr_overs_bowled']);
        $match->result_method = 'coin_toss';
        $match->team2_runs = null;
        $result = $this->calculate([$match]);
        $this->assertSame(2, $result['rows'][1]['points']);
        $this->assertSame(0, $result['rows'][1]['runs_for']);
        $this->assertSame('Official innings figures are incomplete.', $result['nrr_exclusions'][1]);
    }

    public function test_exact_six_ball_fractions_are_summed_before_decimal_rounding(): void
    {
        $matches = [];
        foreach (range(2, 7) as $opponent) {
            $matches[] = $this->match(['id' => $opponent, 'team2_id' => $opponent, 'balls_per_over' => 6,
                'team1_runs' => 3, 'team2_runs' => 1, 'team1_deliveries' => 1, 'team2_deliveries' => 1, 'team2_wickets' => 0]);
        }
        $a = $this->calculate($matches, $this->members(7))['rows'][1];
        $this->assertSame('1.00000000', $a['nrr_overs_faced']);
        $this->assertSame('1.00000000', $a['nrr_overs_bowled']);
        $this->assertSame('12.000000', $a['nrr']);
    }

    public function test_super_over_normal_scores_are_counted_without_any_extra_super_over_runs(): void
    {
        $match = $this->match(['result_method' => 'super_over', 'winner_id' => 2, 'team2_runs' => 100]);
        $result = $this->calculate([$match]);
        $this->assertSame(100, $result['rows'][2]['runs_for']);
        $this->assertSame(2, $result['rows'][2]['points']);
        $this->assertSame('0.000000', $result['rows'][1]['nrr']);
    }

    public static function badMatches(): array
    {
        return [
            [['winner_id' => 3]], [['team2_id' => 3]], [['result_type' => 'unknown']],
            [['team1_runs' => null]], [['team1_deliveries' => 21]], [['team2_wickets' => 12]],
            [['winner_id' => 2]], [['result_type' => 'tie', 'winner_id' => null]],
            [['result_method' => 'super_over']], [['result_method' => 'revised_target', 'nrr_inputs' => null]],
            [['result_method' => 'revised_target', 'nrr_inputs' => ['included' => true, 'reason' => 'Official', 'team1' => ['runs' => 2, 'deliveries' => 3]]]],
        ];
    }

    #[DataProvider('badMatches')]
    public function test_inconsistent_published_sources_are_rejected_instead_of_guessed(array $changes): void
    {
        $this->expectException(ValidationException::class);
        $this->calculate([$this->match($changes)]);
    }

    public static function badAdjustments(): array
    {
        return [[['points' => -1, 'reason' => 'Not a list']], [[['points' => -1.5, 'reason' => 'Fraction']]],
            [[['points' => -1, 'reason' => ' ']]], [[['amount' => -1, 'reason' => 'Wrong key']]]];
    }

    #[DataProvider('badAdjustments')]
    public function test_invalid_adjustment_history_cannot_silently_change_points(array $entries): void
    {
        $members = $this->members();
        $members[0]->point_adjustments = $entries;
        $this->expectException(ValidationException::class);
        $this->calculate([], $members);
    }

    public function test_duplicate_source_matches_are_not_counted_twice(): void
    {
        $match = $this->match();
        $this->expectException(ValidationException::class);
        $this->calculate([$match, $match]);
    }

    public function test_cumulative_totals_cannot_overflow_the_existing_integer_columns(): void
    {
        $first = $this->match(['team1_runs' => 2147483647, 'team2_runs' => 0]);
        $second = $this->match(['id' => 2, 'team2_id' => 3, 'team1_runs' => 1, 'team2_runs' => 0]);
        $this->expectException(ValidationException::class);
        $this->calculate([$first, $second], $this->members(3));
    }

    public function test_result_validator_output_is_compatible_with_adjusted_standings_input(): void
    {
        $match = $this->match();
        $result = app(MatchResultValidator::class)->validate($match, [
            'result_type' => 'win', 'result_method' => 'revised_target', 'winner_id' => 2,
            'revised_target' => 60, 'result_note' => 'Official rain-shortened result.',
            'team1_runs' => 100, 'team1_wickets' => 11, 'team1_overs' => 4, 'team1_balls' => 0,
            'team2_runs' => 61, 'team2_wickets' => 2, 'team2_overs' => 3, 'team2_balls' => 2,
            'nrr_inputs' => ['included' => true, 'reason' => 'Official adjusted figures',
                'team1' => ['runs' => 70, 'overs' => 4, 'balls' => 0],
                'team2' => ['runs' => 61, 'overs' => 3, 'balls' => 2]],
        ]);
        $match->forceFill($result);
        $rows = $this->calculate([$match])['rows'];
        $this->assertSame(70, $rows[1]['runs_for']);
        $this->assertSame('4.00000000', $rows[1]['nrr_overs_faced']);
        $this->assertSame('0.071429', $rows[1]['nrr']);
        $this->assertSame(2, $rows[2]['points']);
    }
}
