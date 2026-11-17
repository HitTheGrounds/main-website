<?php

namespace Tests\Feature;

use App\Services\Tournament\PlayerStatsValidator;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PlayerStatsValidationTest extends TestCase
{
    public function test_partial_values_preserve_unknowns_zero_and_manual_delivery_counts(): void
    {
        $validator = app(PlayerStatsValidator::class);
        $data = $validator->validate(['runs_scored' => '0', 'balls_faced' => '', 'dot_balls' => '6']);
        $this->assertSame(0, $data['runs_scored']);
        $this->assertNull($data['balls_faced']);
        $this->assertNull($data['bowling_balls']);
        $this->assertSame(6, $data['dot_balls']);
        $bowling = $validator->validate(['bowling_balls' => 25, 'dot_balls' => 12, 'wickets_taken' => 7]);
        $this->assertNull($bowling['runs_scored']);
        $this->assertSame(25, $bowling['bowling_balls']);
        $this->assertSame(12, $bowling['dot_balls']);
        $this->assertSame(0, $validator->validate(['runs_scored' => 0, 'fours' => 0, 'sixes' => 0])['runs_scored']);
        $this->assertSame(10, $validator->validate(['runs_scored' => 10, 'fours' => 1, 'sixes' => 1])['runs_scored']);
        $this->assertSame(1, $validator->validate(['runs_scored' => 1, 'fours' => 2])['runs_scored']);
        $this->assertSame(2147483647, $validator->validate(['runs_conceded' => '2147483647'])['runs_conceded']);
    }

    public static function invalidFigures(): array
    {
        return [
            [['runs_scored' => -1], 'runs_scored'], [['runs_scored' => true], 'runs_scored'],
            [['runs_scored' => 1.0], 'runs_scored'], [['runs_scored' => 1.5], 'runs_scored'],
            [['runs_scored' => '1.0'], 'runs_scored'], [['runs_scored' => '1e2'], 'runs_scored'],
            [['runs_scored' => 'abc'], 'runs_scored'], [['runs_scored' => []], 'runs_scored'],
            [['runs_scored' => ' 1'], 'runs_scored'], [['runs_scored' => '+1'], 'runs_scored'],
            [['runs_scored' => '01'], 'runs_scored'], [['runs_scored' => 2147483648], 'runs_scored'],
            [['wickets_taken' => 8], 'wickets_taken'], [['dot_balls' => 4, 'bowling_balls' => 3], 'dot_balls'],
            [['runs_scored' => 9, 'fours' => 1, 'sixes' => 1], 'runs_scored'],
            [['batting_status' => '<script>out</script>'], 'batting_status'],
            [['record_state' => 'published'], 'player'], [['player_gender' => 'Male'], 'player'],
            [['team_member_id' => 2], 'player'], [['published_by' => 1], 'player'],
            [['super_over_runs' => 6], 'player'], [['bowling_overs' => 1], 'player'],
            [['runs_scored' => str_repeat('9', 4096)], 'player'],
        ];
    }

    #[DataProvider('invalidFigures')]
    public function test_invalid_figures_are_rejected(array $input, string $field): void
    {
        try {
            app(PlayerStatsValidator::class)->validate($input);
            $this->fail('Expected invalid player figures to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }
}
