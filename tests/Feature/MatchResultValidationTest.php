<?php

namespace Tests\Feature;

use App\Models\TournamentMatch;
use App\Services\Tournament\DeliveryCount;
use App\Services\Tournament\MatchResultValidator;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MatchResultValidationTest extends TestCase
{
    private function match(string $stage = 'G', int $balls = 4): TournamentMatch
    {
        $match = new TournamentMatch;
        $match->forceFill(['stage' => $stage, 'team1_id' => 10, 'team2_id' => 20, 'batting_first_id' => 10,
            'balls_per_over' => $balls, 'overs_per_innings' => 5, 'max_wickets' => 7]);

        return $match;
    }

    private function ordinary(): array
    {
        return ['result_type' => 'win', 'result_method' => 'normal', 'winner_id' => '10',
            'team1_runs' => '100', 'team1_wickets' => '5', 'team1_overs' => '5', 'team1_balls' => '0',
            'team2_runs' => '90', 'team2_wickets' => '7', 'team2_overs' => '4', 'team2_balls' => '3'];
    }

    private function rejected(array $input, string $field, ?TournamentMatch $match = null): void
    {
        try {
            app(MatchResultValidator::class)->validate($match ?? $this->match(), $input);
            $this->fail('Expected invalid result to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    public static function conversions(): array
    {
        return [[0, 0, 4, 0], [3, 2, 4, 14], [3, 2, 6, 20], [5, 0, 4, 20], [5, 0, 6, 30], ['4', '5', 6, 29]];
    }

    #[DataProvider('conversions')]
    public function test_delivery_conversion_uses_whole_overs_and_the_recorded_format(mixed $overs, mixed $balls, int $format, int $expected): void
    {
        $this->assertSame($expected, app(DeliveryCount::class)->fromParts($overs, $balls, $format));
    }

    public static function invalidConversions(): array
    {
        return [['3.2', 0, 4], [3, 4, 4], [3, 6, 6], [5, 1, 4], [6, 0, 6], [-1, 0, 4], [0, -1, 4], [0, 0, 5], [null, 0, 4]];
    }

    #[DataProvider('invalidConversions')]
    public function test_invalid_or_overallocated_delivery_counts_are_rejected(mixed $overs, mixed $balls, int $format): void
    {
        $this->expectException(ValidationException::class);
        app(DeliveryCount::class)->fromParts($overs, $balls, $format);
    }

    public function test_normal_result_is_typed_read_only_and_uses_actual_deliveries_without_all_out_adjustment(): void
    {
        $match = $this->match();
        $before = $match->getAttributes();
        $result = app(MatchResultValidator::class)->validate($match, $this->ordinary());
        $this->assertSame(10, $result['winner_id']);
        $this->assertSame(10, $result['batting_first_id']);
        $this->assertSame(100, $result['team1_runs']);
        $this->assertSame(20, $result['team1_deliveries']);
        $this->assertSame(19, $result['team2_deliveries']);
        $this->assertArrayNotHasKey('team1_overs', $result);
        $this->assertNull($result['nrr_inputs']);
        $this->assertSame($before, $match->getAttributes());
    }

    public static function invalidResults(): array
    {
        return [
            [['winner_id' => 20], 'winner_id'], [['winner_id' => 30], 'winner_id'], [['winner_id' => null], 'winner_id'],
            [['team1_runs' => 90], 'winner_id'], [['team1_runs' => -1], 'team1_runs'],
            [['team1_runs' => 2147483648], 'team1_runs'], [['team1_wickets' => 8], 'team1_wickets'],
            [['team1_runs' => null], 'team1_runs'], [['team1_overs' => 3.2], 'team1_overs'],
            [['team1_overs' => 5, 'team1_balls' => 1], 'team1_balls'],
            [['team1_balls' => 4], 'team1_balls'], [['result_method' => null], 'result_method'],
            [['result_type' => 'unknown'], 'result_type'], [['result_type' => 'tie'], 'result_type'],
            [['batting_first_id' => 30], 'batting_first_id'],
            [['created_by' => 1], 'tournament'], [['team1_deliveries' => 1], 'tournament'],
            [['super_over_runs' => 20], 'tournament'], [['status' => 'finished'], 'tournament'],
            [['result_note' => str_repeat('x', 2001)], 'result_note'],
            [['nrr_inputs' => ['included' => false]], 'nrr_inputs'],
        ];
    }

    #[DataProvider('invalidResults')]
    public function test_invalid_or_injected_result_values_are_rejected(array $changes, string $field): void
    {
        $this->rejected(array_replace($this->ordinary(), $changes), $field);
    }

    public function test_successful_chase_and_optional_unknown_batting_order(): void
    {
        $input = array_replace($this->ordinary(), ['winner_id' => 20, 'team2_runs' => 101, 'team2_wickets' => 6]);
        $this->assertSame(20, app(MatchResultValidator::class)->validate($this->match(), $input)['winner_id']);
        $this->rejected(array_replace($input, ['team2_wickets' => 7]), 'team2_wickets');
        $match = $this->match();
        $match->batting_first_id = null;
        $this->assertNull(app(MatchResultValidator::class)->validate($match, $this->ordinary())['batting_first_id']);
    }

    public function test_group_tie_preserves_real_zeroes_and_knockout_tie_is_rejected(): void
    {
        $input = ['result_type' => 'tie', 'result_method' => 'normal'];
        foreach (['team1', 'team2'] as $team) {
            foreach (['runs', 'wickets', 'overs', 'balls'] as $field) {
                $input[$team.'_'.$field] = 0;
            }
        }
        $result = app(MatchResultValidator::class)->validate($this->match(), $input);
        $this->assertSame(0, $result['team1_runs']);
        $this->assertSame(0, $result['team2_deliveries']);
        $this->assertNull($result['winner_id']);
        $this->rejected($input, 'result_type', $this->match('QF'));
        $this->rejected(array_replace($input, ['team2_runs' => 1]), 'result_type');
    }

    public function test_super_over_keeps_tied_normal_innings_and_records_only_the_official_decision(): void
    {
        $input = array_replace($this->ordinary(), ['result_method' => 'super_over', 'team2_runs' => 100,
            'winner_id' => 20, 'result_note' => 'Team 2 won the Super Over.']);
        $result = app(MatchResultValidator::class)->validate($this->match('F', 6), $input);
        $this->assertSame(100, $result['team2_runs']);
        $this->assertSame(30, $result['team1_deliveries']);
        $this->assertSame(20, $result['winner_id']);
        $this->rejected(array_replace($input, ['team2_runs' => 101]), 'result_type');
        $this->rejected(array_replace($input, ['result_note' => ' ']), 'result_note');
    }

    public function test_abandonment_and_no_result_do_not_invent_missing_innings_or_allow_a_winner(): void
    {
        foreach (['no_result', 'abandoned'] as $type) {
            $input = ['result_type' => $type];
            $result = app(MatchResultValidator::class)->validate($this->match(), $input);
            $this->assertNull($result['team1_runs']);
            $this->assertNull($result['team2_deliveries']);
            $this->assertNull($result['result_method']);
            $this->rejected($input + ['winner_id' => 10], 'result_type');
            $this->rejected($input, 'result_type', $this->match('SF'));
        }
        $partial = app(MatchResultValidator::class)->validate($this->match(), ['result_type' => 'abandoned', 'team1_runs' => 12,
            'team1_wickets' => 1, 'team1_overs' => 2, 'team1_balls' => 1]);
        $this->assertSame(9, $partial['team1_deliveries']);
        $this->assertNull($partial['team2_runs']);
    }

    public function test_coin_toss_or_official_decision_needs_an_explanation_without_invented_innings(): void
    {
        foreach (['coin_toss', 'official_decision'] as $method) {
            $input = ['result_type' => 'win', 'result_method' => $method, 'winner_id' => 20, 'result_note' => 'Committee decision.'];
            $result = app(MatchResultValidator::class)->validate($this->match('SF'), $input);
            $this->assertSame($method, $result['result_method']);
            $this->assertSame(20, $result['winner_id']);
            $this->assertNull($result['team1_runs']);
            $this->rejected(array_replace($input, ['result_note' => null]), 'result_note');
        }
    }

    private function revised(): array
    {
        return array_replace($this->ordinary(), ['result_method' => 'revised_target', 'winner_id' => 20, 'revised_target' => 60,
            'result_note' => 'Rain-shortened official result.', 'nrr_inputs' => [
                'included' => true, 'reason' => 'Committee supplied the NRR figures.',
                'team1' => ['runs' => 70, 'overs' => 4, 'balls' => 0],
                'team2' => ['runs' => 61, 'overs' => 3, 'balls' => 2],
            ]]);
    }

    public function test_revised_target_keeps_actual_and_adjusted_figures_separate_and_allows_explicit_nrr_exclusion(): void
    {
        $result = app(MatchResultValidator::class)->validate($this->match(), $this->revised());
        $this->assertSame(100, $result['team1_runs']);
        $this->assertSame(70, $result['nrr_inputs']['team1']['runs']);
        $this->assertSame(14, $result['nrr_inputs']['team2']['deliveries']);
        $this->assertSame(20, $result['winner_id']);
        $input = array_replace($this->revised(), ['nrr_inputs' => ['included' => false, 'reason' => 'Committee excluded NRR.']]);
        $this->assertSame(['included' => false, 'reason' => 'Committee excluded NRR.'],
            app(MatchResultValidator::class)->validate($this->match(), $input)['nrr_inputs']);
        $this->rejected(array_replace($this->revised(), ['nrr_inputs' => null]), 'nrr_inputs.included');
        $this->rejected(array_replace($this->revised(), ['revised_target' => null]), 'revised_target');
        $input = $this->revised();
        unset($input['nrr_inputs']['team2']['balls']);
        $this->rejected($input, 'nrr_inputs.team2.balls');
        $input = $this->revised();
        $input['nrr_inputs']['team1']['wickets'] = 2;
        $this->rejected($input, 'nrr_inputs.team1');
    }

    public function test_partial_delivery_pairs_locked_batting_order_and_oversized_data_are_rejected(): void
    {
        $this->rejected(['result_type' => 'abandoned', 'team1_overs' => 2], 'team1_overs');
        $match = $this->match();
        $match->structure_locked_at = now();
        $this->rejected(array_replace($this->ordinary(), ['batting_first_id' => 20]), 'batting_first_id', $match);
        $this->rejected(array_replace($this->ordinary(), ['result_note' => str_repeat('x', 8193)]), 'tournament');
        $this->rejected(['result_type' => "\xFF"], 'tournament');
    }
}
