<?php

namespace Tests\PostgreSQL;

use App\Models\GroupTeam;
use App\Models\Team;
use App\Models\TournamentMatch;
use App\Models\User;
use App\Services\Tournament\MatchResultService;
use App\Services\Tournament\MatchSchedulingService;
use App\Services\Tournament\ScorerStandingsService;
use App\Services\Tournament\TournamentGroupService;
use App\Services\Tournament\TournamentWriteService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Support\PostgreSqlTestDatabaseSafety;

class MatchResultConcurrencyTest extends PostgreSqlTestCase
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

    private function fixture(int $count = 2): array
    {
        $service = app(TournamentGroupService::class);
        $group = $service->create(2026, 'A');
        $teams = Team::factory()->count($count)->create(['approved' => true]);
        foreach ($teams as $team) {
            $service->assign(2026, $group->id, $group->fresh()->version, $team->id);
        }

        return [$group->fresh(), $teams, ['stage' => 'G', 'group_id' => $group->id,
            'team1_id' => $teams[0]->id, 'team2_id' => $teams[1]->id, 'balls_per_over' => 4]];
    }

    private function compete(User $actor, string $action, array $arguments, callable $first): string
    {
        $tag = 'htg-result-'.bin2hex(random_bytes(8));
        $code = <<<'PHP'
require 'vendor/autoload.php';
try {
    $test = new class('probe') extends \Tests\PostgreSQL\PostgreSqlTestCase {};
    $test->createApplication();
    config(['tournament.enabled' => true, 'tournament.writes_per_minute' => 1000]);
    \Illuminate\Support\Facades\DB::selectOne("SELECT set_config('application_name', ?, false)", [$argv[4]]);
    request()->attributes->set('user', \App\Models\User::findOrFail((int) $argv[1]));
    $args = json_decode($argv[3], true, flags: JSON_THROW_ON_ERROR);
    if ($argv[2] === 'publish') {
        app(\App\Services\Tournament\MatchResultService::class)->publish(2026, $args['match_id'], $args['version'], $args['draft_hash']);
    } else {
        app(\App\Services\Tournament\ScorerStandingsService::class)->adjust(2026, $args['group'], $args['version'], $args['team'], ['points' => -1, 'reason' => 'Worker penalty']);
    }
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

    private function reviewed(TournamentMatch $match): array
    {
        $service = app(MatchResultService::class);
        $service->saveDraft(2026, $match->id, $match->fresh()->version, ['result_type' => 'win', 'result_method' => 'normal',
            'winner_id' => $match->team1_id, 'batting_first_id' => $match->team1_id, 'team1_runs' => 100, 'team1_wickets' => 3,
            'team1_overs' => 5, 'team1_balls' => 0, 'team2_runs' => 90, 'team2_wickets' => 7, 'team2_overs' => 3, 'team2_balls' => 0]);

        return $service->review(2026, $match->id);
    }

    private function publish(array $review): void
    {
        app(MatchResultService::class)->publish(2026, $review['match_id'], $review['version'], $review['draft_hash']);
    }

    public function test_same_review_cannot_publish_twice_after_waiting(): void
    {
        $this->scorer();
        [$group, , $input] = $this->fixture();
        $match = app(MatchSchedulingService::class)->create(2026, $group->version, $input);
        $review = $this->reviewed($match);
        $this->assertSame('REJECTED:tournament', $this->compete($this->scorer(false), 'publish', $review, fn () => $this->publish($review)));
        $this->assertSame(1, GroupTeam::where('team_id', $match->team1_id)->firstOrFail()->played);
        $this->assertSame(2, GroupTeam::where('team_id', $match->team1_id)->firstOrFail()->points);
    }

    public function test_distinct_matches_publish_without_losing_group_totals(): void
    {
        $this->scorer();
        [$group, $teams, $input] = $this->fixture(3);
        $one = app(MatchSchedulingService::class)->create(2026, $group->version, $input);
        $two = app(MatchSchedulingService::class)->create(2026, $group->version, array_replace($input, ['team2_id' => $teams[2]->id]));
        $a = $this->reviewed($one);
        $b = $this->reviewed($two);
        $this->assertSame('WROTE', $this->compete($this->scorer(false), 'publish', $b, fn () => $this->publish($a)));
        $row = GroupTeam::where('team_id', $teams[0]->id)->firstOrFail();
        $this->assertSame(2, $row->played);
        $this->assertSame(4, $row->points);
        $this->assertSame(200, $row->runs_for);
    }

    public function test_waiting_publication_keeps_committed_penalty(): void
    {
        $this->scorer();
        [$group, , $input] = $this->fixture();
        $match = app(MatchSchedulingService::class)->create(2026, $group->version, $input);
        $review = $this->reviewed($match);
        $this->assertSame('WROTE', $this->compete($this->scorer(false), 'publish', $review,
            fn () => app(ScorerStandingsService::class)->adjust(2026, $group->id, $group->version, $match->team1_id, ['points' => -1, 'reason' => 'Official penalty'])));
        $row = GroupTeam::where('team_id', $match->team1_id)->firstOrFail();
        $this->assertSame(1, $row->points);
        $this->assertSame('Official penalty', $row->point_adjustments[0]['reason']);
    }

    public function test_waiting_adjustment_rechecks_group_version_after_publication(): void
    {
        $this->scorer();
        [$group, , $input] = $this->fixture();
        $match = app(MatchSchedulingService::class)->create(2026, $group->version, $input);
        $review = $this->reviewed($match);
        $args = ['group' => $group->id, 'version' => $group->version, 'team' => $match->team1_id];
        $this->assertSame('REJECTED:tournament', $this->compete($this->scorer(false), 'adjust', $args, fn () => $this->publish($review)));
        app(ScorerStandingsService::class)->adjust(2026, $group->id, $group->fresh()->version, $match->team1_id, ['points' => -1, 'reason' => 'Reloaded penalty']);
        $row = GroupTeam::where('team_id', $match->team1_id)->firstOrFail();
        $this->assertSame(1, $row->points);
        $this->assertCount(1, $row->point_adjustments);
    }

    public function test_waiting_review_is_invalidated_by_schedule_edit(): void
    {
        $this->scorer();
        [$group, , $input] = $this->fixture();
        $match = app(MatchSchedulingService::class)->create(2026, $group->version, $input);
        $review = $this->reviewed($match);
        $this->assertSame('REJECTED:tournament', $this->compete($this->scorer(false), 'publish', $review,
            fn () => app(MatchSchedulingService::class)->updateSchedule(2026, $match->id, $review['version'], ['venue' => 'Changed venue'])));
        $this->assertNull($match->fresh()->result_published_at);
        $this->assertNotNull($match->fresh()->draft_result);
    }

    public function test_waiting_publication_rechecks_revoked_permission(): void
    {
        $actor = $this->scorer();
        [$group, , $input] = $this->fixture();
        $match = app(MatchSchedulingService::class)->create(2026, $group->version, $input);
        $review = $this->reviewed($match);
        $this->assertSame('DENIED:403', $this->compete($actor, 'publish', $review,
            fn () => DB::table('users')->where('id', $actor->id)->update(['is_scorer' => false])));
        $this->assertNull($match->fresh()->result_published_at);
    }

    public function test_waiting_adjustment_rechecks_revoked_permission(): void
    {
        $actor = $this->scorer();
        [$group, $teams] = $this->fixture();
        $this->assertSame('DENIED:403', $this->compete($actor, 'adjust', ['group' => $group->id, 'version' => $group->version, 'team' => $teams[0]->id],
            fn () => DB::table('users')->where('id', $actor->id)->update(['is_scorer' => false])));
        $this->assertSame(0, GroupTeam::where('team_id', $teams[0]->id)->firstOrFail()->points);
    }
}
