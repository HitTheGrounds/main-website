<?php

namespace Tests\Support;

use App\Models\Company;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\Tournament\RosterProtectionService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;

trait TournamentRosterChecks
{
    private function assertRosterBlocked(callable $operation): void
    {
        try {
            $operation();
            $this->fail('A referenced record was changed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('team', $exception->errors());
        }
    }

    public static function historicalTeamReferences(): array
    {
        return array_combine(
            ['assignment', 'team1_id', 'team2_id', 'batting_first_id', 'winner_id', 'draft', 'published', 'summary', 'member_reference'],
            array_map(fn ($type) => [$type], ['assignment', 'team1_id', 'team2_id', 'batting_first_id', 'winner_id', 'draft', 'published', 'summary', 'member_reference'])
        );
    }

    #[DataProvider('historicalTeamReferences')]
    public function test_every_historical_reference_blocks_team_deletion_even_when_feature_is_off(string $type): void
    {
        config(['tournament.enabled' => false]);
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $members = TeamMember::factory()->count(2)->create(['team_id' => $team->id]);
        $other = Team::factory()->create();
        $another = Team::factory()->create();
        if ($type === 'assignment') {
            $group = DB::table('tournament_groups')->insertGetId(['tournament_year' => 2025, 'name' => 'Historical']);
            DB::table('group_teams')->insert(['tournament_year' => 2025, 'group_id' => $group, 'team_id' => $team->id]);
        } elseif ($type === 'summary') {
            $this->insertRosterSummary($members->first());
        } else {
            $match = ['tournament_year' => 2025, 'stage' => 'QF', 'team1_id' => $other->id, 'team2_id' => $another->id, 'balls_per_over' => 4];
            if (in_array($type, ['team1_id', 'team2_id', 'batting_first_id', 'winner_id'], true)) {
                $match[$type] = $team->id;
            }
            $matchId = DB::table('matches')->insertGetId($match);
            if (in_array($type, ['draft', 'published', 'member_reference'], true)) {
                $row = [
                    'match_id' => $matchId, 'team_id' => $type === 'member_reference' ? $other->id : $team->id,
                    'team_member_id' => $members->first()->id, 'player_name' => 'Historical player', 'player_gender' => 'Other',
                ];
                if ($type === 'published') {
                    $row += ['record_state' => 'published', 'pending_action' => null, 'published_at' => '2025-11-01 09:00:00'];
                }
                DB::table('player_match_stats')->insert($row);
            }
        }
        $this->assertRosterBlocked(fn () => app(RosterProtectionService::class)->deleteTeam($team->id, $owner));
        $this->assertDatabaseHas('teams', ['id' => $team->id]);
        $this->assertSame($members->modelKeys(), $team->members()->orderBy('id')->pluck('id')->all());
        $this->assertFalse($team->fresh()->locked);
    }

    private function insertRosterSummary(TeamMember $member): void
    {
        DB::table('player_tournament_stats')->insert([
            'tournament_year' => 2025, 'team_id' => $member->team_id, 'team_member_id' => $member->id,
            'player_name' => 'Original player', 'player_gender' => $member->gender,
        ]);
    }

    public static function playerReferenceStates(): array
    {
        return ['draft' => ['draft'], 'published' => ['published'], 'summary' => ['summary']];
    }

    #[DataProvider('playerReferenceStates')]
    public function test_all_player_reference_states_block_deletion_and_reassignment(string $state): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $player = TeamMember::factory()->create(['team_id' => $team->id]);
        $other = Team::factory()->create();
        if ($state === 'summary') {
            $this->insertRosterSummary($player);
        } else {
            $match = DB::table('matches')->insertGetId(['tournament_year' => 2025, 'stage' => 'F', 'team1_id' => $team->id, 'team2_id' => $other->id, 'balls_per_over' => 6]);
            DB::table('player_match_stats')->insert([
                'match_id' => $match, 'team_id' => $team->id, 'team_member_id' => $player->id,
                'player_name' => 'Original player', 'player_gender' => $player->gender, 'record_state' => $state,
                'pending_action' => $state === 'draft' ? 'withdraw' : null,
                'published_at' => $state === 'published' ? '2025-11-01 09:00:00' : null,
            ]);
        }
        $this->assertRosterBlocked(fn () => app(RosterProtectionService::class)->deleteMember($team->id, $player->id, $owner));
        $this->assertRosterBlocked(fn () => $player->update(['team_id' => $other->id]));
        $this->assertSame($team->id, $player->fresh()->team_id);
        $player->refresh()->update(['name' => 'Corrected name', 'is_captain' => true]);
        $this->assertSame('Corrected name', $player->fresh()->name);
        $table = $state === 'summary' ? 'player_tournament_stats' : 'player_match_stats';
        $this->assertSame('Original player', DB::table($table)->where('team_member_id', $player->id)->value('player_name'));
    }

    public function test_stale_lock_and_foreign_member_targets_cannot_bypass_protection(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $player = TeamMember::factory()->create(['team_id' => $team->id]);
        $foreign = TeamMember::factory()->create();
        $service = app(RosterProtectionService::class);
        $this->assertFalse($service->deleteMember($team->id, $foreign->id, $owner));
        $this->assertDatabaseHas('team_members', ['id' => $foreign->id]);
        DB::table('teams')->where('id', $team->id)->update(['locked' => true]);
        $this->assertFalse($team->locked);
        $this->assertRosterBlocked(fn () => $service->deleteMember($team->id, $player->id, $owner));
        $admin = User::factory()->create(['is_admin' => true]);
        $this->assertTrue($service->deleteMember($team->id, $player->id, $admin, asAdmin: true));
    }

    public function test_locked_membership_check_rejects_stale_team_and_rolls_back_callback_writes(): void
    {
        $player = TeamMember::factory()->create();
        $oldTeam = $player->team_id;
        $destination = Team::factory()->create();
        $player->update(['team_id' => $destination->id]);
        $service = app(RosterProtectionService::class);
        $this->assertRosterBlocked(fn () => $service->withLockedMember($oldTeam, $player->id, fn () => $this->fail('Stale callback ran.')));
        try {
            $service->withLockedMember($destination->id, $player->id, function ($locked) {
                $this->insertRosterSummary($locked);
                throw new \RuntimeException('Intentional rollback');
            });
            $this->fail('Callback failure was swallowed.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Intentional rollback', $exception->getMessage());
        }
        $this->assertDatabaseCount('player_tournament_stats', 0);
    }

    public function test_organization_delete_cascade_is_atomic_with_referenced_and_unrelated_teams(): void
    {
        $company = Company::factory()->create();
        $owner = User::factory()->create(['company_id' => $company->id]);
        $teams = Team::factory()->count(2)->create(['user_id' => $owner->id, 'company_id' => $company->id]);
        foreach ($teams as $team) {
            TeamMember::factory()->create(['team_id' => $team->id]);
        }
        $this->insertRosterSummary($teams->last()->members()->firstOrFail());
        try {
            DB::transaction(fn () => DB::table('companies')->where('id', $company->id)->delete());
            $this->fail('Referenced organization was deleted.');
        } catch (QueryException $exception) {
            $this->assertStringStartsWith('23', (string) $exception->getCode());
        }
        $this->assertDatabaseHas('companies', ['id' => $company->id]);
        $this->assertDatabaseHas('users', ['id' => $owner->id]);
        foreach ($teams as $team) {
            $this->assertDatabaseHas('teams', ['id' => $team->id]);
            $this->assertSame(1, $team->members()->count());
        }
    }
}
