<?php

namespace Tests\PostgreSQL;

use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\Tournament\RosterProtectionService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;
use Symfony\Component\Process\Process;
use Tests\Support\PostgreSqlTestDatabaseSafety;

class TournamentRosterConcurrencyTest extends PostgreSqlTestCase
{
    // Fixtures must be committed so independent server sessions can see them.
    use DatabaseMigrations;

    private function worker(string $action, int $playerId, int $teamId): string
    {
        $code = <<<'PHP'
require 'vendor/autoload.php';
try {
    // Bootstrap the guarded app only; never invoke a migration/test refresh trait.
    $test = new class('probe') extends \Tests\PostgreSQL\PostgreSqlTestCase {};
    $test->createApplication();
    \Illuminate\Support\Facades\DB::statement("SET lock_timeout = '250ms'");
    if ($argv[3] === 'move') {
        \App\Models\TeamMember::findOrFail((int) $argv[1])->update(['team_id' => (int) $argv[2]]);
        echo 'MOVED';
    } else {
        app(\App\Services\Tournament\RosterProtectionService::class)->withLockedMember((int) $argv[2], (int) $argv[1], function ($member) {
            \Illuminate\Support\Facades\DB::table('player_tournament_stats')->insert([
                'tournament_year' => 2026, 'team_id' => $member->team_id, 'team_member_id' => $member->id,
                'player_name' => $member->name, 'player_gender' => $member->gender,
            ]);
        });
        echo 'WROTE';
    }
} catch (\Illuminate\Validation\ValidationException) {
    echo 'PROTECTED';
} catch (\Illuminate\Database\QueryException $exception) {
    if ($exception->getCode() !== '55P03') { echo 'UNEXPECTED SQLSTATE'; exit(2); }
    echo 'LOCKED';
} catch (\Throwable) {
    echo 'UNEXPECTED WORKER FAILURE';
    exit(2);
}
PHP;
        $worker = new Process([PHP_BINARY, '-r', $code, (string) $playerId, (string) $teamId, $action], base_path());
        $worker->setTimeout(20)->run();
        $this->assertTrue($worker->isSuccessful(), 'Guarded worker did not complete.');

        return trim($worker->getOutput());
    }

    private function assertSqlState(PDO $connection, string $sql, array $bindings, string $state): void
    {
        try {
            $connection->prepare($sql)->execute($bindings);
            $this->fail('Competing database write unexpectedly succeeded.');
        } catch (PDOException $exception) {
            $this->assertSame($state, $exception->getCode());
        }
    }

    public function test_stats_write_locks_membership_then_blocks_reassignment_after_commit(): void
    {
        $player = TeamMember::factory()->create();
        $destination = Team::factory()->create();
        $other = PostgreSqlTestDatabaseSafety::connect(config('database.connections.pgsql'));
        $service = app(RosterProtectionService::class);
        $service->withLockedMember($player->team_id, $player->id, function ($locked) use ($player, $destination, $other) {
            $this->assertSqlState($other, 'SELECT id FROM teams WHERE id = ? FOR UPDATE NOWAIT', [$player->team_id], '55P03');
            // Another team is still available: the lock is not tournament-wide.
            $statement = $other->prepare('SELECT id FROM teams WHERE id = ? FOR UPDATE NOWAIT');
            $statement->execute([$destination->id]);
            $this->assertSame($destination->id, (int) $statement->fetchColumn());
            $this->assertSame('LOCKED', $this->worker('move', $player->id, $destination->id));
            DB::table('player_tournament_stats')->insert([
                'tournament_year' => 2026, 'team_id' => $locked->team_id, 'team_member_id' => $locked->id,
                'player_name' => $locked->name, 'player_gender' => $locked->gender,
            ]);
        });
        $this->assertSame('PROTECTED', $this->worker('move', $player->id, $destination->id));
        $this->assertSame($player->team_id, $player->fresh()->team_id);
        $this->assertDatabaseCount('player_tournament_stats', 1);
    }

    public function test_reassignment_locks_membership_then_rejects_a_stale_stats_writer(): void
    {
        $player = TeamMember::factory()->create();
        $oldTeam = $player->team_id;
        $destination = Team::factory()->create();
        DB::beginTransaction();
        try {
            $player->update(['team_id' => $destination->id]);
            $this->assertSame('LOCKED', $this->worker('write', $player->id, $oldTeam));
            DB::commit();
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
        $this->assertSame('PROTECTED', $this->worker('write', $player->id, $oldTeam));
        $this->assertDatabaseCount('player_tournament_stats', 0);
        $this->assertSame('WROTE', $this->worker('write', $player->id, $destination->id));
        $this->assertDatabaseHas('player_tournament_stats', ['team_id' => $destination->id, 'team_member_id' => $player->id]);
    }

    public function test_pending_team_delete_blocks_a_late_reference_and_fk_rejects_it_after_commit(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $player = TeamMember::factory()->create(['team_id' => $team->id]);
        $group = DB::table('tournament_groups')->insertGetId(['tournament_year' => 2026, 'name' => 'Concurrent']);
        $other = PostgreSqlTestDatabaseSafety::connect(config('database.connections.pgsql'));
        $other->exec("SET lock_timeout = '250ms'");
        $sql = 'INSERT INTO group_teams (tournament_year, group_id, team_id) VALUES (2026, ?, ?)';
        DB::beginTransaction();
        try {
            app(RosterProtectionService::class)->deleteTeam($team->id, $owner);
            $this->assertSqlState($other, $sql, [$group, $team->id], '55P03');
            DB::commit();
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
        $this->assertSqlState($other, $sql, [$group, $team->id], '23503');
        $this->assertDatabaseMissing('teams', ['id' => $team->id]);
        $this->assertDatabaseMissing('team_members', ['id' => $player->id]);
        $this->assertDatabaseCount('group_teams', 0);
    }
}
