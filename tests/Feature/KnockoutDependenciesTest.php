<?php

namespace Tests\Feature;

use App\Models\TournamentMatch;
use App\Services\Tournament\KnockoutDependencies;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class KnockoutDependenciesTest extends TestCase
{
    private function source(string $stage = 'QF'): TournamentMatch
    {
        return (new TournamentMatch)->forceFill(['id' => 11, 'tournament_year' => 2026, 'stage' => $stage, 'team1_id' => 1,
            'team2_id' => 2, 'status' => 'finished', 'result_type' => 'win', 'winner_id' => 1, 'result_published_at' => now()]);
    }

    public static function invalidatedWins(): array
    {
        $cases = [];
        foreach (['QF' => 'SF', 'SF' => 'F'] as $stage => $next) {
            foreach ([['winner_id' => 2], ['status' => 'live'], ['status' => 'upcoming'], ['result_published_at' => null]] as $change) {
                $cases[] = [$stage, $next, $change];
            }
        }

        return $cases;
    }

    #[DataProvider('invalidatedWins')]
    public function test_actual_next_round_participant_blocks_invalidating_its_source(string $stage, string $next, array $change): void
    {
        $before = $this->source($stage);
        $proposed = clone $before;
        $proposed->forceFill($change);
        $dependent = $this->source($next);
        $dependent->forceFill(['id' => 22, 'team1_id' => 3, 'team2_id' => 1, 'status' => 'upcoming', 'result_published_at' => null]);
        try {
            app(KnockoutDependencies::class)->assertChange($before, $proposed, [$before, $dependent]);
            $this->fail('Expected actual dependency rejection.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString($next.' match #22', $exception->errors()['tournament'][0]);
        }
        $this->assertSame(1, $before->winner_id);
    }

    public function test_published_dependent_requires_explicit_organizer_resolution(): void
    {
        $before = $this->source();
        $proposed = clone $before;
        $proposed->winner_id = 2;
        $dependent = $this->source('SF');
        $dependent->id = 22;
        try {
            app(KnockoutDependencies::class)->assertChange($before, $proposed, [$dependent]);
            $this->fail('Expected dependency rejection.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('history is already published', $exception->errors()['tournament'][0]);
        }
    }

    public function test_unrelated_fixtures_other_years_and_group_ranking_corrections_do_not_block(): void
    {
        $guard = app(KnockoutDependencies::class);
        $before = $this->source();
        $proposed = clone $before;
        $proposed->winner_id = 2;
        $other = $this->source('SF');
        $other->forceFill(['id' => 22, 'team1_id' => 3, 'team2_id' => 4]);
        $foreign = $this->source('SF');
        $foreign->tournament_year = 2025;
        $guard->assertChange($before, $proposed, [$other, $foreign]);
        $before = $this->source('G');
        $proposed = clone $before;
        $proposed->winner_id = 2;
        $guard->assertChange($before, $proposed, [$this->source()]);
        $this->assertSame(1, $before->winner_id);
    }

    public function test_unchanged_winner_score_or_slot_correction_retains_actual_connection(): void
    {
        $before = $this->source();
        $proposed = clone $before;
        $proposed->forceFill(['team1_runs' => 101, 'bracket_position' => 4]);
        $dependent = $this->source('SF');
        $beforeAttributes = $before->getAttributes();
        app(KnockoutDependencies::class)->assertChange($before, $proposed, [$dependent]);
        $this->assertSame($beforeAttributes, $before->getAttributes());
    }
}
