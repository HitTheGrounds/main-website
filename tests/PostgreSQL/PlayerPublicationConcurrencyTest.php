<?php

namespace Tests\PostgreSQL;

use App\Models\PlayerMatchStat;
use App\Models\PlayerTournamentStat;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TournamentMatch;
use App\Models\User;
use App\Services\Tournament\MatchResultService;
use App\Services\Tournament\PlayerPublicationService;
use App\Services\Tournament\PlayerStatsService;
use App\Services\Tournament\TournamentWriteService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Support\PostgreSqlTestDatabaseSafety;

class PlayerPublicationConcurrencyTest extends PostgreSqlTestCase
{
    use DatabaseMigrations;

    private function scorer(bool $resolve = true): User
    {
        config(['tournament.enabled' => true, 'tournament.writes_per_minute' => 1000]);
        $user = User::factory()->create(['company_id' => null, 'is_admin' => false]);
        $user->is_scorer = true;
        $user->save();
        if ($resolve) {
            request()->attributes->set('user', $user);
        }

        return $user;
    }

    private function compete(User $actor, string $action, array $arguments, callable $first): string
    {
        $tag = 'htg-player-'.bin2hex(random_bytes(8));
        $code = <<<'PHP'
require 'vendor/autoload.php';
try {
    $test = new class('probe') extends \Tests\PostgreSQL\PostgreSqlTestCase {};
    $test->createApplication();
    config(['tournament.enabled' => true, 'tournament.writes_per_minute' => 1000]);
    \Illuminate\Support\Facades\DB::selectOne("SELECT set_config('application_name', ?, false)", [$argv[4]]);
    request()->attributes->set('user', \App\Models\User::findOrFail((int) $argv[1]));
    $args = json_decode($argv[3], true, flags: JSON_THROW_ON_ERROR);
    app(\App\Services\Tournament\PlayerPublicationService::class)->publish(2026, $args['match_id'], $args['version'], $args['player_stats_revision'], $args['receipt']);
    echo 'WROTE';
} catch (\Illuminate\Validation\ValidationException $exception) {
    echo 'REJECTED:'.array_key_first($exception->errors());
} catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
    echo 'DENIED:'.$exception->getStatusCode();
} catch (\Throwable) {
    echo 'UNEXPECTED WORKER FAILURE';
    exit(2);
}
PHP;
        $worker = new Process([PHP_BINARY, '-r', $code, (string) $actor->id, $action, json_encode($arguments, JSON_THROW_ON_ERROR), $tag], base_path());
        $worker->setTimeout(15);
        $observer = PostgreSqlTestDatabaseSafety::connect(config('database.connections.pgsql'));
        DB::beginTransaction();
        try {
            app(TournamentWriteService::class)->run(2026, $first);
            $worker->start();
            $statement = $observer->prepare("SELECT count(*) FROM pg_stat_activity WHERE application_name = ? AND wait_event_type = 'Lock' AND wait_event = 'advisory'");
            $deadline = microtime(true) + 5;
            $waiting = false;
            do {
                $statement->execute([$tag]);
                $waiting = (int) $statement->fetchColumn() === 1;
                if ($waiting || ! $worker->isRunning()) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            $this->assertTrue($waiting, 'The competing service must actually wait for the common year lock.');
            DB::commit();
            $worker->wait();
            $this->assertTrue($worker->isSuccessful(), 'Guarded worker failed.');

            return trim($worker->getOutput());
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            if ($worker->isRunning()) {
                $worker->stop(0);
            }
        }
    }

    private function fixture(): array
    {
        $actor = $this->scorer();
        $teams = Team::factory()->count(3)->create(['approved' => true]);
        $groupId = DB::table('tournament_groups')->insertGetId(['tournament_year' => 2026, 'name' => 'A']);
        foreach ($teams as $team) {
            DB::table('group_teams')->insert(['group_id' => $groupId, 'team_id' => $team->id, 'tournament_year' => 2026]);
        }
        $member = TeamMember::factory()->create(['team_id' => $teams[0]->id]);
        $matches = [];
        foreach ([1, 2] as $index) {
            $id = DB::table('matches')->insertGetId(['group_id' => $groupId, 'stage' => 'G', 'tournament_year' => 2026,
                'team1_id' => $teams[0]->id, 'team2_id' => $teams[$index]->id, 'balls_per_over' => 4]);
            $match = TournamentMatch::findOrFail($id);
            $results = app(MatchResultService::class);
            $results->saveDraft(2026, $id, 1, ['result_type' => 'win', 'result_method' => 'normal', 'winner_id' => $match->team1_id,
                'batting_first_id' => $match->team1_id, 'team1_runs' => 100, 'team1_wickets' => 3, 'team1_overs' => 5,
                'team1_balls' => 0, 'team2_runs' => 90, 'team2_wickets' => 11, 'team2_overs' => 3, 'team2_balls' => 0]);
            $review = $results->review(2026, $id);
            $results->publish(2026, $id, $review['version'], $review['draft_hash']);
            app(PlayerStatsService::class)->saveDraft(2026, $id, $member->team_id, $member->id,
                ['draft' => null, 'published' => null], ['runs_scored' => 10 * $index]);
            $matches[] = $match->fresh();
        }

        return [$actor, $matches, $member];
    }

    private function review(TournamentMatch $match): array
    {
        return app(PlayerPublicationService::class)->review(2026, $match->id);
    }

    private function publish(array $review): void
    {
        app(PlayerPublicationService::class)->publish(2026, $review['match_id'], $review['version'], $review['player_stats_revision'], $review['receipt']);
    }

    public function test_same_player_review_waits_and_cannot_publish_twice(): void
    {
        [, $matches] = $this->fixture();
        $review = $this->review($matches[0]);
        $this->assertSame('REJECTED:player', $this->compete($this->scorer(false), 'publish', $review, fn () => $this->publish($review)));
        $this->assertSame(10, PlayerTournamentStat::firstOrFail()->runs_scored);
        $this->assertSame(1, PlayerMatchStat::where('record_state', 'published')->count());
    }

    public function test_distinct_matches_for_same_player_serialize_without_losing_totals(): void
    {
        [, $matches] = $this->fixture();
        $a = $this->review($matches[0]);
        $b = $this->review($matches[1]);
        $this->assertSame('WROTE', $this->compete($this->scorer(false), 'publish', $b, fn () => $this->publish($a)));
        $total = PlayerTournamentStat::firstOrFail();
        $this->assertSame(30, $total->runs_scored);
        $this->assertSame(2, $total->runs_scored_recorded_count);
        $this->assertSame(20, $total->highest_score);
        $this->assertSame($matches[1]->id, $total->highest_score_match_id);
    }

    public function test_waiting_publication_rejects_newer_draft(): void
    {
        [, $matches, $member] = $this->fixture();
        $match = $matches[0];
        $review = $this->review($match);
        $draft = PlayerMatchStat::where('match_id', $match->id)->firstOrFail();
        $this->assertSame('REJECTED:player', $this->compete($this->scorer(false), 'publish', $review,
            fn () => app(PlayerStatsService::class)->saveDraft(2026, $match->id, $member->team_id, $member->id,
                ['draft' => ['id' => $draft->id, 'version' => $draft->version], 'published' => null], ['runs_scored' => 15])));
        $this->assertSame(15, $draft->fresh()->runs_scored);
        $this->assertDatabaseCount('player_tournament_stats', 0);
    }

    public function test_concurrent_correction_and_withdrawal_keep_only_the_remaining_best_score(): void
    {
        [, $matches, $member] = $this->fixture();
        foreach ($matches as $match) {
            $this->publish($this->review($match));
        }
        foreach ($matches as $index => $match) {
            $official = PlayerMatchStat::where('match_id', $match->id)->where('record_state', 'published')->firstOrFail();
            $expected = ['draft' => null, 'published' => ['id' => $official->id, 'version' => $official->version]];
            if ($index === 0) {
                app(PlayerStatsService::class)->saveDraft(2026, $match->id, $member->team_id, $member->id, $expected, ['runs_scored' => 5]);
            } else {
                app(PlayerStatsService::class)->stageWithdrawal(2026, $match->id, $member->team_id, $member->id, $expected);
            }
        }
        $a = $this->review($matches[0]);
        $b = $this->review($matches[1]);
        $this->assertSame('WROTE', $this->compete($this->scorer(false), 'publish', $b, fn () => $this->publish($a)));
        $total = PlayerTournamentStat::firstOrFail();
        $this->assertSame(5, $total->runs_scored);
        $this->assertSame(5, $total->highest_score);
        $this->assertSame($matches[0]->id, $total->highest_score_match_id);
        $this->assertSame(1, $total->runs_scored_recorded_count);
        $this->assertDatabaseCount('player_match_stats', 1);
    }

    public function test_waiting_publication_rechecks_result_exclusion(): void
    {
        [, $matches] = $this->fixture();
        $match = $matches[0];
        $review = $this->review($match);
        $this->assertSame('REJECTED:player', $this->compete($this->scorer(false), 'publish', $review, function () use ($match) {
            $results = app(MatchResultService::class);
            $results->saveDraft(2026, $match->id, $match->version, ['result_type' => 'no_result', 'result_note' => 'Committee decision']);
            $result = $results->review(2026, $match->id);
            $results->publish(2026, $match->id, $result['version'], $result['draft_hash']);
        }));
        $this->assertSame('no_result', $match->fresh()->result_type);
        $this->assertDatabaseCount('player_tournament_stats', 0);
        $this->assertSame(2, PlayerMatchStat::where('record_state', 'draft')->count());
    }

    public function test_waiting_publication_rechecks_revoked_scorer_permission(): void
    {
        [$actor, $matches] = $this->fixture();
        $review = $this->review($matches[0]);
        $this->assertSame('DENIED:403', $this->compete($actor, 'publish', $review,
            fn () => DB::table('users')->where('id', $actor->id)->update(['is_scorer' => false])));
        $this->assertDatabaseCount('player_tournament_stats', 0);
        $this->assertSame(0, PlayerMatchStat::where('record_state', 'published')->count());
    }
}
