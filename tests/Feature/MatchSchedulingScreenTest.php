<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Team;
use App\Models\TournamentMatch;
use App\Models\User;
use App\Services\JWTService;
use App\Services\Tournament\MatchSchedulingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MatchSchedulingScreenTest extends TestCase
{
    use RefreshDatabase;

    private function scorer(): User
    {
        config(['tournament.enabled' => true, 'tournament.year' => 2026, 'tournament.writes_per_minute' => 1000]);
        Route::middleware('web')->group(base_path('routes/tournament.php'));
        Route::getRoutes()->refreshNameLookups();
        $actor = User::factory()->create(['is_admin' => false, 'company_id' => null]);
        $actor->is_scorer = true;
        $actor->save();
        Livewire::withCookie('company_token', (new JWTService)->generateToken($actor));
        request()->attributes->set('user', $actor);

        return $actor;
    }

    private function fixture(int $year = 2026, string $name = 'Custom group'): array
    {
        $groupId = DB::table('tournament_groups')->insertGetId(['name' => $name, 'tournament_year' => $year, 'version' => 3]);
        $teams = Team::factory()->count(2)->create(['approved' => true]);
        foreach ($teams as $team) {
            DB::table('group_teams')->insert(['tournament_year' => $year, 'group_id' => $groupId, 'team_id' => $team->id]);
        }

        return ['group_id' => $groupId, 'stage' => 'G', 'team1_id' => $teams[0]->id,
            'team2_id' => $teams[1]->id, 'balls_per_over' => 4];
    }

    private function ready(array $input)
    {
        return Volt::test('scorer.matches-manager')->set('groupChoice', (string) $input['group_id'])
            ->set('newStructure.team1_id', (string) $input['team1_id'])
            ->set('newStructure.team2_id', (string) $input['team2_id']);
    }

    public function test_empty_dashboard_preserves_group_navigation_and_has_no_invented_scores(): void
    {
        $actor = $this->scorer();
        $this->signedIn($actor)->get('/scorer/dashboard')->assertOk()
            ->assertSee('Scorer dashboard')->assertSee('No groups are available')
            ->assertSee('Group setup')->assertSee('Create group match')
            ->assertSee('href="'.route('scorer.knockouts').'"', false)
            ->assertDontSee('wire:poll', false);
        $this->assertDatabaseCount('matches', 0);
    }

    public function test_create_and_edit_schedule_workflow_uses_backend_versions_and_keeps_scores_null(): void
    {
        $this->scorer();
        $input = $this->fixture();
        $component = $this->ready($input)->assertSet('groupVersion', 3)
            ->set('newSchedule.status', 'live')->set('newSchedule.venue', 'Ground A')
            ->call('createMatch')->assertHasNoErrors()->assertSee('Match created.')
            ->assertSet('editingVersion', 1)->assertSet('schedule.status', 'live');
        $match = TournamentMatch::firstOrFail();
        $component->assertSet('editingId', $match->id)->set('schedule.venue', 'Corrected ground')
            ->set('schedule.scheduled_at', '2026-11-20T09:00')->call('saveSchedule')
            ->assertHasNoErrors()->assertSet('editingVersion', 2)->assertSee('Match schedule saved.');
        $this->assertSame('Corrected ground', $match->fresh()->venue);
        $this->assertSame('2026-11-20 03:30:00', $match->fresh()->scheduled_at->utc()->format('Y-m-d H:i:s'));
        $this->assertNull($match->fresh()->team1_runs);
        $this->assertNull($match->fresh()->draft_result);
    }

    public function test_stale_schedule_and_group_inputs_are_retained_until_explicit_reload(): void
    {
        $this->scorer();
        $input = $this->fixture();
        $component = $this->ready($input);
        DB::table('tournament_groups')->where('id', $input['group_id'])->update(['version' => 4]);
        $component->set('newSchedule.venue', 'Unsaved venue')->call('createMatch')
            ->assertHasErrors('tournament')->assertSet('newSchedule.venue', 'Unsaved venue')
            ->assertSet('newStructure.team1_id', (string) $input['team1_id']);
        $component->call('chooseGroup')->assertSet('groupVersion', 4);
        $match = app(MatchSchedulingService::class)->create(2026, 4, $input);
        $component->call('openMatch', $match->id);
        DB::table('matches')->where('id', $match->id)->update(['version' => 2, 'venue' => 'Other scorer']);
        $component->set('schedule.venue', 'My edit')->call('saveSchedule')->assertHasErrors('tournament')
            ->assertSet('schedule.venue', 'My edit')->assertSet('editingVersion', 1)
            ->call('reloadMatch')->assertHasNoErrors()->assertSet('schedule.venue', 'Other scorer')->assertSet('editingVersion', 2);
    }

    public function test_validation_errors_remain_visible_and_do_not_create_partial_fixtures(): void
    {
        $this->scorer();
        $input = $this->fixture();
        $this->ready($input)->set('newStructure.team2_id', (string) $input['team1_id'])
            ->call('createMatch')->assertHasErrors('team2_id')
            ->assertSet('newStructure.team1_id', (string) $input['team1_id'])
            ->set('newStructure.team2_id', (string) $input['team2_id'])
            ->set('newSchedule.winner_id', $input['team1_id'])->call('createMatch')->assertHasErrors('tournament');
        $this->assertDatabaseCount('matches', 0);
    }

    public function test_filters_are_year_scoped_paginated_allowlisted_and_omit_private_drafts(): void
    {
        $this->scorer();
        $first = null;
        foreach (range(1, 11) as $number) {
            $input = $this->fixture(2026, 'Group '.$number);
            $match = app(MatchSchedulingService::class)->create(2026, 3, $input, ['status' => $number === 1 ? 'live' : 'upcoming']);
            $match->draft_result = ['private_note' => 'DRAFT-MUST-NOT-LEAK'];
            $match->save();
            $first ??= $match;
        }
        $other = $this->fixture(2027, 'Other year');
        DB::table('matches')->insert($other + ['tournament_year' => 2027]);
        $page = app(MatchSchedulingService::class)->listing(2026);
        $this->assertSame(11, $page->total());
        $this->assertSame(10, $page->count());
        $this->assertArrayNotHasKey('draft_result', $page->first()->getAttributes());
        $this->assertSame(0, app(MatchSchedulingService::class)->listing(2026, ['group_id' => $other['group_id']])->total());
        $this->assertSame(0, app(MatchSchedulingService::class)->listing(2026, ['group_id' => 999999])->total());
        Volt::test('scorer.matches-manager')->assertSee('Matches found: 11')->assertDontSee('Other year')
            ->assertDontSee('DRAFT-MUST-NOT-LEAK')->set('filterForm.status', 'live')->call('applyFilters')
            ->assertHasNoErrors()->assertSee('Matches found: 1')
            ->set('filterForm.group_id', (string) $first->group_id)->set('filterForm.stage', 'G')->call('applyFilters')->assertHasNoErrors()
            ->set('filterForm.stage', 'DROP TABLE matches')->call('applyFilters')->assertHasErrors('stage')
            ->assertSet('filters.stage', 'G');
    }

    public function test_unapproved_teams_and_other_year_groups_are_not_offered_and_names_are_escaped(): void
    {
        $this->scorer();
        $input = $this->fixture(2026, '<script>group</script>');
        Team::whereKey($input['team1_id'])->update(['team_name' => '<script>team</script>']);
        Team::whereKey($input['team2_id'])->update(['approved' => false, 'team_name' => 'Unapproved team']);
        $this->fixture(2027, 'Other year group');
        $this->ready($input)->assertSee('&lt;script&gt;group&lt;/script&gt;', false)
            ->assertSee('&lt;script&gt;team&lt;/script&gt;', false)->assertDontSee('<script>team</script>', false)
            ->assertDontSee('Unapproved team')->assertDontSee('Other year group')
            ->assertSee('at least two approved teams');
    }

    public function test_published_schedule_shows_locks_and_preserves_official_values(): void
    {
        $this->scorer();
        $input = $this->fixture();
        $match = app(MatchSchedulingService::class)->create(2026, 3, $input);
        $match->status = 'finished';
        $match->team1_runs = 0;
        $match->result_published_at = now();
        $match->structure_locked_at = now();
        $match->save();
        Volt::test('scorer.matches-manager')->call('openMatch', $match->id)
            ->assertSet('statusEditable', false)->assertSet('battingEditable', false)
            ->assertSee('Result status is locked')->set('schedule.venue', 'Updated venue')
            ->call('saveSchedule')->assertHasNoErrors();
        $this->assertSame('finished', $match->fresh()->status);
        $this->assertSame(0, $match->fresh()->team1_runs);
    }

    public function test_reviewed_version_cannot_be_changed_from_the_browser(): void
    {
        $this->scorer();
        $this->expectException(CannotUpdateLockedPropertyException::class);
        Volt::test('scorer.matches-manager')->set('editingVersion', 999);
    }

    public static function unauthorizedActors(): array
    {
        return [['guest'], ['representative'], ['pending'], ['admin'], ['revoked'], ['disabled']];
    }

    #[DataProvider('unauthorizedActors')]
    public function test_screen_reads_recheck_current_permissions(string $role): void
    {
        $actor = $this->scorer();
        if ($role === 'guest') {
            Livewire::withCookie('company_token', '');
            request()->attributes->remove('user');
        } elseif ($role === 'disabled') {
            config(['tournament.enabled' => false]);
        } elseif ($role === 'revoked') {
            DB::table('users')->where('id', $actor->id)->update(['is_scorer' => false]);
        } else {
            $user = User::factory()->create(['is_admin' => $role === 'admin',
                'company_id' => $role === 'representative' ? Company::factory()->create()->id : null]);
            Livewire::withCookie('company_token', (new JWTService)->generateToken($user));
            request()->attributes->set('user', $user);
        }
        $component = Volt::test('scorer.matches-manager');
        $role === 'disabled' ? $component->assertNotFound() : $component->assertForbidden();
    }

    public function test_real_signed_snapshot_creates_edits_and_is_rejected_after_scorer_revocation(): void
    {
        $actor = $this->scorer();
        $input = $this->fixture();
        $html = $this->signedIn($actor)->get('/scorer/dashboard')->assertOk()->getContent();
        preg_match('/wire:snapshot="([^"]+)"/', $html, $matches);
        $snapshot = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $update = function ($snapshot, $method, $updates = []) {
            return $this->withCredentials()->postJson('/livewire/update', ['components' => [[
                'snapshot' => $snapshot, 'updates' => $updates,
                'calls' => [['path' => '', 'method' => $method, 'params' => []]],
            ]]], ['X-Livewire' => 'true']);
        };
        $response = $update($snapshot, 'chooseGroup', ['groupChoice' => (string) $input['group_id']])->assertOk();
        $response = $update($response->json('components.0.snapshot'), 'createMatch',
            ['newStructure.team1_id' => (string) $input['team1_id'], 'newStructure.team2_id' => (string) $input['team2_id']])->assertOk();
        $match = TournamentMatch::firstOrFail();
        $response = $update($response->json('components.0.snapshot'), 'saveSchedule', ['schedule.venue' => 'HTTP venue'])->assertOk();
        $this->assertSame('HTTP venue', $match->fresh()->venue);
        DB::table('users')->where('id', $actor->id)->update(['is_scorer' => false]);
        $update($response->json('components.0.snapshot'), 'saveSchedule', ['schedule.venue' => 'Unauthorized'])->assertForbidden();
        $this->assertSame('HTTP venue', $match->fresh()->venue);
        $this->assertNull($match->fresh()->team1_runs);
    }
}
