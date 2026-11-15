<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TournamentMatch;
use App\Models\User;
use App\Services\Tournament\BracketSlotService;
use App\Services\Tournament\KnockoutEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class BracketSlotServiceTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        config(['tournament.enabled' => true, 'tournament.writes_per_minute' => 1000]);
        $actor = User::factory()->create(['company_id' => null, 'is_admin' => false]);
        $actor->is_scorer = true;
        $actor->save();
        request()->attributes->set('user', $actor);
        $teams = Team::factory()->count(4)->create(['approved' => true]);
        $group = DB::table('tournament_groups')->insertGetId(['name' => 'A', 'tournament_year' => 2026]);
        foreach ($teams as $team) {
            DB::table('group_teams')->insert(['group_id' => $group, 'tournament_year' => 2026, 'team_id' => $team->id, 'qualified' => true]);
        }
        $matches = [];
        foreach ([1, 2] as $position) {
            $a = $teams[($position - 1) * 2]->id;
            $b = $teams[($position - 1) * 2 + 1]->id;
            $id = DB::table('matches')->insertGetId(['tournament_year' => 2026, 'stage' => 'QF', 'bracket_position' => $position,
                'team1_id' => $a, 'team2_id' => $b, 'batting_first_id' => $a, 'balls_per_over' => 4,
                'status' => 'finished', 'result_type' => 'win', 'winner_id' => $a, 'result_method' => 'normal',
                'team1_runs' => 100, 'team1_wickets' => 3, 'team1_deliveries' => 20,
                'team2_runs' => 90, 'team2_wickets' => 11, 'team2_deliveries' => 12,
                'result_published_at' => now(), 'structure_locked_at' => now(), 'published_by' => $actor->id,
                'draft_result' => json_encode(['team1_runs' => 101])]);
            $matches[] = TournamentMatch::findOrFail($id);
        }
        DB::table('matches')->insert(['tournament_year' => 2026, 'stage' => 'SF', 'bracket_position' => 1,
            'team1_id' => $teams[2]->id, 'team2_id' => $teams[0]->id, 'balls_per_over' => 6]);
        $member = TeamMember::factory()->create(['team_id' => $teams[0]->id]);
        DB::table('player_match_stats')->insert(['match_id' => $matches[0]->id, 'team_id' => $member->team_id,
            'team_member_id' => $member->id, 'record_state' => 'published', 'pending_action' => null,
            'published_at' => now(), 'player_name' => $member->name, 'player_gender' => $member->gender, 'runs_scored' => 35]);

        return [$actor, $matches[0], $matches[1]];
    }

    private function snapshot(): array
    {
        $result = [];
        foreach (['matches', 'player_match_stats', 'player_tournament_stats', 'tournament_groups', 'group_teams', 'teams', 'team_members', 'users'] as $table) {
            $result[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }

        return $result;
    }

    public function test_atomic_swap_preserves_sources_and_actual_semifinal_connections(): void
    {
        [, $one, $two] = $this->fixture();
        $before = $this->snapshot();
        $edges = $this->edges();
        $sources = [$one->getAttributes(), $two->getAttributes()];
        app(BracketSlotService::class)->move(2026, $one->id, $one->version, 2, ['id' => $two->id, 'version' => $two->version]);
        $this->assertSame(2, $one->fresh()->bracket_position);
        $this->assertSame(1, $two->fresh()->bracket_position);
        $this->assertSame($edges, $this->edges());
        foreach ([$one, $two] as $index => $match) {
            $current = $match->fresh();
            $this->assertSame($sources[$index]['version'] + 1, $current->version);
            foreach ($sources[$index] as $key => $value) {
                if (! in_array($key, ['bracket_position', 'version', 'updated_at', 'updated_by'], true)) {
                    $this->assertSame($value, $current->getAttributes()[$key], $key);
                }
            }
        }
        $after = $this->snapshot();
        unset($before['matches'], $after['matches']);
        $this->assertSame($before, $after);
    }

    private function edges(): array
    {
        return app(KnockoutEligibility::class)->round(2026, 'SF', Team::all(), \App\Models\GroupTeam::all(), TournamentMatch::all());
    }

    public function test_empty_position_move_and_same_position_noop_are_versioned_safely(): void
    {
        [, $one] = $this->fixture();
        $service = app(BracketSlotService::class);
        $service->move(2026, $one->id, $one->version, 4, null);
        $this->assertSame(4, $one->fresh()->bracket_position);
        $this->assertSame(2, $one->fresh()->version);
        $before = $this->snapshot();
        $service->move(2026, $one->id, 2, 4, null);
        $this->assertSame($before, $this->snapshot());
    }

    public static function staleVersions(): array
    {
        return [['source'], ['target'], ['absent'], ['wrong_id']];
    }

    #[DataProvider('staleVersions')]
    public function test_stale_source_or_target_cannot_silently_swap(string $change): void
    {
        [, $one, $two] = $this->fixture();
        $version = $change === 'source' ? 99 : 1;
        $expected = $change === 'absent' ? null : ['id' => $change === 'wrong_id' ? 999 : $two->id, 'version' => $change === 'target' ? 99 : 1];
        $before = $this->snapshot();
        try {
            app(BracketSlotService::class)->move(2026, $one->id, $version, 2, $expected);
            $this->fail('Expected stale swap rejection.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
        $this->assertSame($before, $this->snapshot());
    }

    public function test_late_failure_restores_both_slots_and_versions_after_temporary_null(): void
    {
        [, $one, $two] = $this->fixture();
        $before = $this->snapshot();
        $event = 'eloquent.updated: '.TournamentMatch::class;
        Event::listen($event, function ($model) use ($one) {
            if ($model->id === $one->id) {
                throw new RuntimeException('Synthetic final slot save failure');
            }
        });
        try {
            app(BracketSlotService::class)->move(2026, $one->id, 1, 2, ['id' => $two->id, 'version' => 1]);
            $this->fail('Expected slot rollback.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic final slot save failure', $exception->getMessage());
        } finally {
            Event::forget($event);
        }
        $this->assertSame($before, $this->snapshot());
    }

    public static function deniedRoles(): array
    {
        return [['revoked'], ['disabled'], ['guest'], ['admin']];
    }

    #[DataProvider('deniedRoles')]
    public function test_slot_changes_require_current_scorer_permission(string $role): void
    {
        [$actor, $one] = $this->fixture();
        if ($role === 'revoked') {
            DB::table('users')->where('id', $actor->id)->update(['is_scorer' => false]);
        } elseif ($role === 'disabled') {
            config(['tournament.enabled' => false]);
        } elseif ($role === 'guest') {
            request()->attributes->remove('user');
        } else {
            request()->attributes->set('user', User::factory()->create(['is_admin' => true]));
        }
        $before = $this->snapshot();
        try {
            app(BracketSlotService::class)->move(2026, $one->id, 1, 4, null);
            $this->fail('Expected scorer denial.');
        } catch (HttpException $exception) {
            $this->assertSame($role === 'disabled' ? 404 : 403, $exception->getStatusCode());
        }
        $this->assertSame($before, $this->snapshot());
    }
}
