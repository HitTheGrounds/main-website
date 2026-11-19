<?php

namespace Tests\Feature;

use App\Models\TournamentMatch;
use App\Services\Tournament\KnockoutIntegrity;
use App\Services\Tournament\MatchResultValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class KnockoutIntegrityTest extends TestCase
{
    private function fixture(array $changes = []): TournamentMatch
    {
        return (new TournamentMatch)->forceFill(array_replace(['id' => 41, 'tournament_year' => 2026, 'stage' => 'QF',
            'group_id' => null, 'bracket_position' => 1, 'team1_id' => 1, 'team2_id' => 2, 'batting_first_id' => 1,
            'status' => 'upcoming', 'balls_per_over' => 4, 'overs_per_innings' => 5, 'max_wickets' => 7], $changes));
    }

    private function rejected(callable $action): void
    {
        try {
            $action();
            $this->fail('Expected knockout integrity rejection.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }

    public static function roundCapacities(): array
    {
        return [['QF', 4], ['SF', 2], ['F', 1]];
    }

    #[DataProvider('roundCapacities')]
    public function test_round_slot_limits_and_distinct_participants(string $stage, int $capacity): void
    {
        $guard = app(KnockoutIntegrity::class);
        $guard->assertFixture(2026, $stage, $capacity, 1, 2, []);
        $this->rejected(fn () => $guard->assertFixture(2026, $stage, $capacity + 1, 1, 2, []));
        $this->rejected(fn () => $guard->assertFixture(2026, $stage, 0, 1, 2, []));
        $this->rejected(fn () => $guard->assertFixture(2026, $stage, 1, 1, 1, []));
    }

    public static function reservedTeams(): array
    {
        return [[1, 3], [3, 1], [2, 3], [3, 2], [2, 1]];
    }

    #[DataProvider('reservedTeams')]
    public function test_team_cannot_appear_twice_in_one_round_in_either_orientation(int $a, int $b): void
    {
        $this->rejected(fn () => app(KnockoutIntegrity::class)->assertFixture(2026, 'QF', 2, $a, $b, [$this->fixture()]));
    }

    public static function fixtureStates(): array
    {
        return [['upcoming'], ['live'], ['finished']];
    }

    #[DataProvider('fixtureStates')]
    public function test_all_saved_fixtures_reserve_their_slot_and_teams(string $status): void
    {
        $match = $this->fixture(['status' => $status]);
        $guard = app(KnockoutIntegrity::class);
        $this->rejected(fn () => $guard->assertFixture(2026, 'QF', 1, 3, 4, [$match]));
        $this->rejected(fn () => $guard->assertFixture(2026, 'QF', 2, 3, 1, [$match]));
    }

    public function test_current_fixture_can_be_checked_for_an_edit_but_other_collisions_still_reject(): void
    {
        $guard = app(KnockoutIntegrity::class);
        $match = $this->fixture();
        $guard->assertFixture(2026, 'QF', 2, 1, 2, [$match], 41);
        $other = $this->fixture(['id' => 42, 'bracket_position' => 2, 'team1_id' => 3, 'team2_id' => 4]);
        $this->rejected(fn () => $guard->assertFixture(2026, 'QF', 2, 1, 2, [$match, $other], 41));
        $this->rejected(fn () => $guard->assertFixture(2026, 'QF', 3, 1, 3, [$match, $other], 41));
    }

    public function test_team_and_slot_reservations_are_scoped_to_round_and_year(): void
    {
        $guard = app(KnockoutIntegrity::class);
        $matches = [$this->fixture(['tournament_year' => 2025]), $this->fixture(['id' => 42, 'stage' => 'SF'])];
        $before = array_map(fn ($match) => $match->getAttributes(), $matches);
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });
        $guard->assertFixture(2026, 'QF', 1, 1, 2, $matches);
        $this->assertSame([], $queries);
        $this->assertSame($before, array_map(fn ($match) => $match->getAttributes(), $matches));
    }

    public static function invalidFinishedResults(): array
    {
        $cases = [];
        foreach (['QF', 'SF', 'F'] as $stage) {
            foreach ([['result_type' => 'tie', 'winner_id' => null], ['result_type' => 'no_result', 'winner_id' => null],
                ['result_type' => 'abandoned', 'winner_id' => null], ['result_type' => 'win', 'winner_id' => null],
                ['result_type' => 'win', 'winner_id' => 999]] as $result) {
                $cases[] = [$stage, $result];
            }
        }

        return $cases;
    }

    #[DataProvider('invalidFinishedResults')]
    public function test_completed_knockout_proposals_require_a_participating_winner(string $stage, array $result): void
    {
        $this->rejected(fn () => app(KnockoutIntegrity::class)->assertFinishedResult($this->fixture(['stage' => $stage]), $result));
    }

    #[DataProvider('roundCapacities')]
    public function test_existing_result_validator_allows_decided_knockouts_and_incomplete_drafts(string $stage, int $capacity): void
    {
        $match = $this->fixture(['stage' => $stage]);
        $validator = app(MatchResultValidator::class);
        $draft = $validator->validateDraft($match, ['team1_runs' => 0]);
        $this->assertSame(0, $draft['team1_runs']);
        $input = ['result_type' => 'win', 'result_method' => 'normal', 'winner_id' => 1,
            'team1_runs' => 100, 'team1_wickets' => 3, 'team1_overs' => 5, 'team1_balls' => 0,
            'team2_runs' => 90, 'team2_wickets' => 7, 'team2_overs' => 3, 'team2_balls' => 0];
        $this->assertSame(1, $validator->validate($match, $input)['winner_id']);
        $input['result_method'] = 'super_over';
        $input['team2_runs'] = 100;
        $input['result_note'] = 'Official Super Over winner';
        $this->assertSame(1, $validator->validate($match, $input)['winner_id']);
        foreach (['tie', 'no_result', 'abandoned'] as $type) {
            $this->rejected(fn () => $validator->validate($match, ['result_type' => $type,
                'result_method' => $type === 'tie' ? 'normal' : null, 'winner_id' => null]));
        }
    }
}
