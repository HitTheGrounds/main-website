<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\GroupTeam;
use App\Models\Team;
use App\Models\User;
use App\Services\Tournament\QualificationService;
use App\Services\Tournament\ScorerStandingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class QualificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(int $count = 3, int $size = 4): array
    {
        config(['tournament.enabled' => true, 'tournament.writes_per_minute' => 1000]);
        $actor = User::factory()->create(['company_id' => null, 'is_admin' => false]);
        $actor->is_scorer = true;
        $actor->save();
        request()->attributes->set('user', $actor);
        $groups = $teams = [];
        for ($g = 0; $g < $count; $g++) {
            $id = DB::table('tournament_groups')->insertGetId(['name' => 'Group '.$g, 'tournament_year' => 2026]);
            $groups[] = $id;
            for ($i = 0; $i < $size; $i++) {
                $team = Team::factory()->create(['approved' => true]);
                $teams[] = $team->id;
                DB::table('group_teams')->insert(['group_id' => $id, 'tournament_year' => 2026, 'team_id' => $team->id,
                    'points' => $i, 'nrr' => null]);
            }
        }

        return [$actor, $groups, $teams];
    }

    private function selection(): array
    {
        return app(QualificationService::class)->selection(2026);
    }

    private function save(array $ids, ?string $revision = null): array
    {
        return app(QualificationService::class)->save(2026, $ids, $revision ?? $this->selection()['revision']);
    }

    private function rejected(callable $action): void
    {
        try {
            $action();
            $this->fail('Expected qualification validation rejection.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }

    public static function groupFormats(): array
    {
        return [[7, 4], [4, 5], [2, 6]];
    }

    #[DataProvider('groupFormats')]
    public function test_dynamic_partial_selection_ignores_table_rank_and_group_quota(int $groups, int $size): void
    {
        [, , $teams] = $this->fixture($groups, $size);
        $state = $this->selection();
        $this->assertCount($groups, $state['groups']);
        $this->assertSame(8, $state['capacity']);
        $this->assertFalse($state['groups'][0]['coverage']['complete']);
        $this->assertTrue($state['groups'][0]['undefined_nrr']);
        $this->assertSame(intdiv($size * ($size - 1), 2), $state['groups'][0]['coverage']['missing']);
        $selected = [$teams[0], $teams[1], $teams[2]];
        $saved = $this->save($selected, $state['revision']);
        $this->assertSame($selected, $saved['selected']);
        $this->assertSame($saved, $this->save($selected, $saved['revision']));
        $this->assertSame([], $this->save([])['selected']);
    }

    public function test_runner_guidance_preserves_joint_first_and_second_places_and_unequal_group_notices(): void
    {
        [, $groups, $teams] = $this->fixture();
        foreach ([[6, 4, 4, 0], [6, 6, 2, 0], [6, 4, 3, 0]] as $group => $points) {
            foreach ($points as $index => $value) {
                DB::table('group_teams')->where('team_id', $teams[$group * 4 + $index])->update(['points' => $value, 'nrr' => '1.000000']);
            }
        }
        $extra = Team::factory()->create(['approved' => true]);
        DB::table('group_teams')->insert(['group_id' => $groups[2], 'tournament_year' => 2026, 'team_id' => $extra->id]);
        $state = $this->selection();
        $this->assertTrue($state['unequal_groups']);
        $this->assertCount(5, $state['runner_up_guidance']);
        $this->assertSame([1, 1, 3, 3, 3], array_column($state['runner_up_guidance'], 'rank'));
        $this->assertTrue($state['groups'][0]['tied']);
        $this->assertSame([1, 2, 2, 4], array_column($state['groups'][0]['teams'], 'rank'));
        $this->assertTrue($state['runner_up_guidance'][0]['provisional']);
        $this->assertSame([$teams[0]], $this->save([$teams[0]])['selected']);
    }

    public function test_points_then_exact_decimal_nrr_order_does_not_choose_a_tied_winner(): void
    {
        [, , $teams] = $this->fixture(1);
        foreach (['-0.000001', '0.000001', '0.000002', '0.000002'] as $i => $nrr) {
            DB::table('group_teams')->where('team_id', $teams[$i])->update(['points' => 4, 'nrr' => $nrr]);
        }
        $state = $this->selection();
        $this->assertSame([$teams[2], $teams[3], $teams[1], $teams[0]], array_column($state['groups'][0]['teams'], 'id'));
        $this->assertSame([1, 1, 3, 4], array_column($state['groups'][0]['teams'], 'rank'));
        $this->assertCount(2, $state['runner_up_guidance']);
    }

    public static function invalidSelections(): array
    {
        return [['duplicate'], ['foreign'], ['unapproved'], ['capacity']];
    }

    #[DataProvider('invalidSelections')]
    public function test_selection_enforces_only_identity_approval_and_capacity_integrity(string $case): void
    {
        [, , $teams] = $this->fixture();
        $ids = [$teams[0], $teams[0]];
        if ($case === 'foreign') {
            $ids = [Team::factory()->create(['approved' => true])->id];
        } elseif ($case === 'unapproved') {
            DB::table('teams')->where('id', $teams[0])->update(['approved' => false]);
            $ids = [$teams[0]];
        } elseif ($case === 'capacity') {
            $ids = array_slice($teams, 0, 9);
        }
        $this->rejected(fn () => $this->save($ids));
        $this->assertSame(0, GroupTeam::where('qualified', true)->count());
    }

    public function test_stale_selection_and_updated_ranking_preserve_saved_choices(): void
    {
        [, $groups, $teams] = $this->fixture();
        $old = $this->selection();
        $this->save([$teams[0]], $old['revision']);
        $this->rejected(fn () => $this->save([$teams[1]], $old['revision']));
        $saved = $this->selection();
        app(ScorerStandingsService::class)->adjust(2026, $groups[0], $saved['groups'][0]['version'], $teams[1], ['points' => 2, 'reason' => 'Committee correction']);
        $this->rejected(fn () => $this->save([$teams[1]], $saved['revision']));
        $this->assertSame([$teams[0]], $this->selection()['selected']);
        $this->assertSame([$teams[1]], $this->save([$teams[1]])['selected']);
    }

    public function test_selected_team_used_in_knockout_cannot_be_removed(): void
    {
        [, , $teams] = $this->fixture();
        $this->save([$teams[0], $teams[1]]);
        DB::table('matches')->insert(['tournament_year' => 2026, 'stage' => 'QF', 'bracket_position' => 1,
            'team1_id' => $teams[0], 'team2_id' => $teams[1], 'balls_per_over' => 4]);
        $this->rejected(fn () => $this->save([$teams[1]]));
        $this->assertSame([$teams[0], $teams[1]], $this->selection()['selected']);
    }

    public function test_failure_rolls_back_all_selection_and_version_changes_without_touching_registration(): void
    {
        [, , $teams] = $this->fixture();
        $before = [];
        foreach (['group_teams', 'tournament_groups', 'teams', 'users'] as $table) {
            $before[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }
        $event = 'eloquent.updated: '.GroupTeam::class;
        Event::listen($event, fn () => throw new RuntimeException('Synthetic qualification failure'));
        try {
            $this->save([$teams[0], $teams[4]]);
            $this->fail('Expected rollback.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic qualification failure', $exception->getMessage());
        } finally {
            Event::forget($event);
        }
        foreach ($before as $table => $snapshot) {
            $this->assertSame($snapshot, DB::table($table)->orderBy('id')->get()->toJson());
        }
    }

    public static function deniedRoles(): array
    {
        return [['guest'], ['admin'], ['representative'], ['pending'], ['revoked'], ['disabled']];
    }

    #[DataProvider('deniedRoles')]
    public function test_reads_and_saves_require_current_scorer_permission(string $role): void
    {
        [$actor, , $teams] = $this->fixture();
        $revision = $this->selection()['revision'];
        if ($role === 'guest') {
            request()->attributes->remove('user');
        } elseif ($role === 'revoked') {
            DB::table('users')->where('id', $actor->id)->update(['is_scorer' => false]);
        } elseif ($role === 'disabled') {
            config(['tournament.enabled' => false]);
        } else {
            request()->attributes->set('user', User::factory()->create(['is_admin' => $role === 'admin',
                'company_id' => $role === 'representative' ? Company::factory()->create()->id : null]));
        }
        foreach ([fn () => $this->selection(), fn () => $this->save([$teams[0]], $revision)] as $action) {
            try {
                $action();
                $this->fail('Expected scorer denial.');
            } catch (HttpException $exception) {
                $this->assertSame($role === 'disabled' ? 404 : 403, $exception->getStatusCode());
            }
        }
        $this->assertSame(0, GroupTeam::where('qualified', true)->count());
    }
}
