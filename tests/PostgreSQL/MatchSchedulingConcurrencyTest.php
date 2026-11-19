<?php

namespace Tests\PostgreSQL;

use App\Models\Team;
use App\Models\User;
use App\Services\Tournament\MatchSchedulingService;
use App\Services\Tournament\TournamentGroupService;
use App\Services\Tournament\TournamentWriteService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Support\PostgreSqlTestDatabaseSafety;

class MatchSchedulingConcurrencyTest extends PostgreSqlTestCase
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
        $tag = 'htg-schedule-'.bin2hex(random_bytes(8));
        $code = <<<'PHP'
require 'vendor/autoload.php';
try {
    $test = new class('probe') extends \Tests\PostgreSQL\PostgreSqlTestCase {};
    $test->createApplication();
    config(['tournament.enabled' => true, 'tournament.writes_per_minute' => 1000]);
    \Illuminate\Support\Facades\DB::selectOne("SELECT set_config('application_name', ?, false)", [$argv[4]]);
    request()->attributes->set('user', \App\Models\User::findOrFail((int) $argv[1]));
    $args = json_decode($argv[3], true, flags: JSON_THROW_ON_ERROR);
    $service = app(\App\Services\Tournament\MatchSchedulingService::class);
    if ($argv[2] === 'create') {
        $service->create(2026, $args['version'], $args['structure']);
    } elseif ($argv[2] === 'update') {
        $service->updateSchedule(2026, $args['match'], $args['version'], ['venue' => 'Worker venue']);
    } elseif ($argv[2] === 'editFixture') {
        $service->updateFixture(2026, $args['match'], $args['version'], $args['groupVersion'], $args['structure'], []);
    } elseif ($argv[2] === 'deleteFixture') {
        $service->deleteFixture(2026, $args['match'], $args['version']);
    } else {
        $groups = app(\App\Services\Tournament\TournamentGroupService::class);
        if ($argv[2] === 'assign') {
            $groups->assign(2026, $args['group'], $args['version'], $args['team']);
        } else {
            $groups->remove(2026, $args['group'], $args['version'], $args['team']);
        }
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

    public function test_waiting_reversed_duplicate_cannot_create_a_second_fixture(): void
    {
        $this->scorer();
        [$group, $teams, $input] = $this->fixture();
        $other = $this->scorer(false);
        $reversed = array_replace($input, ['team1_id' => $teams[1]->id, 'team2_id' => $teams[0]->id]);
        $result = $this->compete($other, 'create', ['version' => $group->version, 'structure' => $reversed],
            fn () => app(MatchSchedulingService::class)->create(2026, $group->version, $input));
        $this->assertSame('REJECTED:team2_id', $result);
        $this->assertDatabaseCount('matches', 1);
        $this->assertDatabaseHas('matches', ['team1_id' => $teams[0]->id, 'team2_id' => $teams[1]->id, 'team1_runs' => null]);
    }

    public function test_waiting_fixture_edit_and_delete_cannot_override_a_committed_update(): void
    {
        $this->scorer();
        [$group, , $input] = $this->fixture();
        $match = app(MatchSchedulingService::class)->create(2026, $group->version, $input);
        $actor = $this->scorer(false);
        $result = $this->compete($actor, 'editFixture', ['match' => $match->id, 'version' => 1,
            'groupVersion' => $group->version, 'structure' => array_replace($input, ['balls_per_over' => 6])],
            fn () => app(MatchSchedulingService::class)->updateSchedule(2026, $match->id, 1, ['venue' => 'First venue']));
        $this->assertSame('REJECTED:tournament', $result);
        $result = $this->compete($actor, 'deleteFixture', ['match' => $match->id, 'version' => 2],
            fn () => app(MatchSchedulingService::class)->updateSchedule(2026, $match->id, 2, ['status' => 'live']));
        $this->assertSame('REJECTED:tournament', $result);
        $this->assertSame('live', $match->fresh()->status);
        $this->assertSame(4, $match->fresh()->balls_per_over);
    }

    public function test_waiting_deletion_rechecks_player_drafts_even_without_a_match_version_change(): void
    {
        $this->scorer();
        [$group, $teams, $input] = $this->fixture();
        $match = app(MatchSchedulingService::class)->create(2026, $group->version, $input);
        $member = \App\Models\TeamMember::factory()->create(['team_id' => $teams[0]->id]);
        $result = $this->compete($this->scorer(false), 'deleteFixture', ['match' => $match->id, 'version' => 1],
            fn () => app(\App\Services\Tournament\PlayerStatsService::class)->saveDraft(2026, $match->id,
                $teams[0]->id, $member->id, ['draft' => null, 'published' => null], ['runs_scored' => 0]));
        $this->assertSame('REJECTED:tournament', $result);
        $this->assertSame(1, $match->fresh()->version);
        $this->assertDatabaseCount('player_match_stats', 1);
    }

    public function test_revoked_scorer_cannot_finish_waiting_fixture_edit_or_delete(): void
    {
        foreach (['editFixture', 'deleteFixture'] as $action) {
            $actor = $this->scorer();
            if (! isset($match)) {
                [$group, , $input] = $this->fixture();
                $match = app(MatchSchedulingService::class)->create(2026, $group->version, $input);
            }
            $result = $this->compete($actor, $action, ['match' => $match->id, 'version' => 1,
                'groupVersion' => $group->version, 'structure' => $input],
                fn () => DB::table('users')->where('id', $actor->id)->update(['is_scorer' => false]));
            $this->assertSame('DENIED:403', $result);
            $this->assertNotNull($match->fresh());
        }
    }

    public function test_waiting_schedule_edit_rechecks_match_version(): void
    {
        $this->scorer();
        [$group, , $input] = $this->fixture();
        $match = app(MatchSchedulingService::class)->create(2026, $group->version, $input);
        $result = $this->compete($this->scorer(false), 'update', ['match' => $match->id, 'version' => 1],
            fn () => app(MatchSchedulingService::class)->updateSchedule(2026, $match->id, 1, ['venue' => 'First venue']));
        $this->assertSame('REJECTED:tournament', $result);
        $this->assertSame('First venue', $match->fresh()->venue);
        $this->assertSame(2, $match->fresh()->version);
    }

    public function test_real_fixture_creation_blocks_waiting_group_removal(): void
    {
        $this->scorer();
        [$group, $teams, $input] = $this->fixture();
        $result = $this->compete($this->scorer(false), 'remove', ['group' => $group->id, 'version' => $group->version, 'team' => $teams[0]->id],
            fn () => app(MatchSchedulingService::class)->create(2026, $group->version, $input));
        $this->assertSame('REJECTED:group', $result);
        $this->assertDatabaseCount('group_teams', 2);
        $this->assertDatabaseCount('matches', 1);
    }

    public function test_committed_removal_prevents_waiting_fixture_creation(): void
    {
        $this->scorer();
        [$group, $teams, $input] = $this->fixture();
        $result = $this->compete($this->scorer(false), 'create', ['version' => $group->version, 'structure' => $input],
            fn () => app(TournamentGroupService::class)->remove(2026, $group->id, $group->version, $teams[1]->id));
        $this->assertSame('REJECTED:team2_id', $result);
        $this->assertDatabaseCount('matches', 0);
        $this->assertDatabaseCount('group_teams', 1);
    }

    public function test_committed_assignment_invalidates_waiting_group_review(): void
    {
        $this->scorer();
        [$group, , $input] = $this->fixture();
        $extra = Team::factory()->create(['approved' => true]);
        $result = $this->compete($this->scorer(false), 'create', ['version' => $group->version, 'structure' => $input],
            fn () => app(TournamentGroupService::class)->assign(2026, $group->id, $group->version, $extra->id));
        $this->assertSame('REJECTED:tournament', $result);
        $this->assertDatabaseCount('matches', 0);
        $this->assertDatabaseCount('group_teams', 3);
    }

    public function test_real_fixture_creation_blocks_waiting_assignment(): void
    {
        $this->scorer();
        [$group, , $input] = $this->fixture();
        $extra = Team::factory()->create(['approved' => true]);
        $result = $this->compete($this->scorer(false), 'assign', ['group' => $group->id, 'version' => $group->version, 'team' => $extra->id],
            fn () => app(MatchSchedulingService::class)->create(2026, $group->version, $input));
        $this->assertSame('REJECTED:group', $result);
        $this->assertDatabaseCount('matches', 1);
        $this->assertDatabaseCount('group_teams', 2);
    }

    public function test_scorer_revocation_blocks_waiting_creation(): void
    {
        $actor = $this->scorer();
        [$group, , $input] = $this->fixture();
        $result = $this->compete($actor, 'create', ['version' => $group->version, 'structure' => $input],
            fn () => DB::table('users')->where('id', $actor->id)->update(['is_scorer' => false]));
        $this->assertSame('DENIED:403', $result);
        $this->assertDatabaseCount('matches', 0);
    }

    public function test_scorer_revocation_blocks_waiting_schedule_update(): void
    {
        $actor = $this->scorer();
        [$group, , $input] = $this->fixture();
        $match = app(MatchSchedulingService::class)->create(2026, $group->version, $input);
        $result = $this->compete($actor, 'update', ['match' => $match->id, 'version' => 1],
            fn () => DB::table('users')->where('id', $actor->id)->update(['is_scorer' => false]));
        $this->assertSame('DENIED:403', $result);
        $this->assertNull($match->fresh()->venue);
        $this->assertSame(1, $match->fresh()->version);
    }

    public function test_independent_scorers_can_create_distinct_fixtures_after_serialization(): void
    {
        $this->scorer();
        [$group, $teams, $input] = $this->fixture(3);
        $other = $this->scorer(false);
        $second = array_replace($input, ['team2_id' => $teams[2]->id]);
        $result = $this->compete($other, 'create', ['version' => $group->version, 'structure' => $second],
            fn () => app(MatchSchedulingService::class)->create(2026, $group->version, $input));
        $this->assertSame('WROTE', $result);
        $this->assertDatabaseCount('matches', 2);
        $this->assertDatabaseHas('matches', ['team2_id' => $teams[2]->id, 'created_by' => $other->id, 'team1_runs' => null]);
    }
}
