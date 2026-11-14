<?php

namespace Tests\PostgreSQL;

use App\Models\Team;
use App\Models\User;
use App\Services\Tournament\TournamentGroupService;
use App\Services\Tournament\TournamentWriteService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Support\PostgreSqlTestDatabaseSafety;

class TournamentGroupConcurrencyTest extends PostgreSqlTestCase
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

    private function compete(User $actor, string $action, int $groupId, int $version, int $teamId, callable $first): string
    {
        $tag = 'htg-group-'.bin2hex(random_bytes(8));
        $code = <<<'PHP'
require 'vendor/autoload.php';
try {
    $test = new class('probe') extends \Tests\PostgreSQL\PostgreSqlTestCase {};
    $test->createApplication();
    config(['tournament.enabled' => true, 'tournament.writes_per_minute' => 1000]);
    \Illuminate\Support\Facades\DB::selectOne("SELECT set_config('application_name', ?, false)", [$argv[6]]);
    request()->attributes->set('user', \App\Models\User::findOrFail((int) $argv[1]));
    $service = app(\App\Services\Tournament\TournamentGroupService::class);
    if ($argv[2] === 'remove') {
        $service->remove(2026, (int) $argv[3], (int) $argv[4], (int) $argv[5]);
    } else {
        $service->assign(2026, (int) $argv[3], (int) $argv[4], (int) $argv[5]);
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
        $worker = new Process([PHP_BINARY, '-r', $code, (string) $actor->id, $action, (string) $groupId, (string) $version, (string) $teamId, $tag], base_path());
        $worker->setTimeout(15);
        $observer = PostgreSqlTestDatabaseSafety::connect(config('database.connections.pgsql'));
        DB::beginTransaction();
        try {
            app(TournamentWriteService::class)->run(2026, $first);
            $worker->start();
            // Observe actual lock waiting, rather than guessing with a fixed sleep.
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
            $this->assertTrue($waiting, 'Competing service must wait for the common year lock.');
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

    public function test_two_groups_cannot_claim_the_same_team_while_first_assignment_commits(): void
    {
        $this->scorer();
        $actor = $this->scorer(false);
        $service = app(TournamentGroupService::class);
        $a = $service->create(2026, 'A');
        $b = $service->create(2026, 'B');
        $team = Team::factory()->create(['approved' => true]);
        $result = $this->compete($actor, 'assign', $b->id, 1, $team->id,
            fn () => $service->assign(2026, $a->id, 1, $team->id));
        $this->assertSame('REJECTED:team', $result);
        $this->assertDatabaseCount('group_teams', 1);
        $this->assertDatabaseHas('group_teams', ['team_id' => $team->id, 'group_id' => $a->id]);
        $this->assertSame(1, $b->fresh()->version);
    }

    public function test_waiting_assignment_rechecks_group_version_after_other_assignment_commits(): void
    {
        $this->scorer();
        $actor = $this->scorer(false);
        $service = app(TournamentGroupService::class);
        $group = $service->create(2026, 'A');
        $teams = Team::factory()->count(2)->create(['approved' => true]);
        $result = $this->compete($actor, 'assign', $group->id, 1, $teams[1]->id,
            fn () => $service->assign(2026, $group->id, 1, $teams[0]->id));
        $this->assertSame('REJECTED:tournament', $result);
        $this->assertDatabaseCount('group_teams', 1);
        $this->assertSame(2, $group->fresh()->version);
    }

    public function test_fixture_committed_under_common_lock_blocks_waiting_removal(): void
    {
        $this->scorer();
        $actor = $this->scorer(false);
        $service = app(TournamentGroupService::class);
        $group = $service->create(2026, 'A');
        $teams = Team::factory()->count(2)->create(['approved' => true]);
        foreach ($teams as $team) {
            $service->assign(2026, $group->id, $group->fresh()->version, $team->id);
        }
        // Match scheduling does not exist yet. This test-only insert exercises
        // the common-lock contract that its future service must follow.
        $result = $this->compete($actor, 'remove', $group->id, 3, $teams[0]->id,
            fn () => DB::table('matches')->insert([
                'tournament_year' => 2026, 'group_id' => $group->id, 'stage' => 'G',
                'team1_id' => $teams[0]->id, 'team2_id' => $teams[1]->id, 'balls_per_over' => 4,
            ]));
        $this->assertSame('REJECTED:group', $result);
        $this->assertDatabaseCount('group_teams', 2);
        $this->assertDatabaseCount('matches', 1);
    }

    public function test_scorer_permission_is_rechecked_after_waiting_for_a_committed_revocation(): void
    {
        $actor = $this->scorer();
        $group = app(TournamentGroupService::class)->create(2026, 'A');
        $team = Team::factory()->create(['approved' => true]);
        $result = $this->compete($actor, 'assign', $group->id, 1, $team->id,
            fn () => DB::table('users')->where('id', $actor->id)->update(['is_scorer' => false]));
        $this->assertSame('DENIED:403', $result);
        $this->assertDatabaseCount('group_teams', 0);
        $this->assertSame(1, $group->fresh()->version);
    }
}
