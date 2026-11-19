<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Team;
use App\Models\TournamentGroup;
use App\Models\User;
use App\Services\JWTService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TournamentGroupManagementTest extends TestCase
{
    use RefreshDatabase;

    private function scorer(): User
    {
        config(['tournament.enabled' => true, 'tournament.year' => 2026, 'tournament.writes_per_minute' => 1000]);
        $user = User::factory()->create(['company_id' => null, 'is_admin' => false]);
        $user->is_scorer = true;
        $user->save();
        Livewire::withCookie('company_token', (new JWTService)->generateToken($user));

        return $user;
    }

    public function test_empty_screen_and_create_assign_remove_rename_delete_workflow(): void
    {
        $this->scorer();
        $team = Team::factory()->create(['approved' => true]);
        $before = $team->fresh()->getAttributes();
        $component = Volt::test('scorer.groups-manager')
            ->assertSee('No groups have been created')
            ->set('newGroupName', 'Group A')->call('createGroup')->assertHasNoErrors()
            ->assertSet('newGroupName', '')->assertSet('editingVersion', 1)
            ->assertSee('Group created.')->assertSet('panel', 'manage');
        $group = TournamentGroup::firstOrFail();
        $component->assertSet('editingGroupId', $group->id)
            ->set('selectedTeamId', (string) $team->id)->call('assignTeam')
            ->assertHasNoErrors()->assertSet('selectedTeamId', '')->assertSet('editingVersion', 2)
            ->assertSee('Team assigned.')->assertSee('No approved unassigned teams are available.')
            ->call('removeTeam', $team->id)->assertHasNoErrors()->assertSet('editingVersion', 3)
            ->set('groupName', 'Corrected name')->call('renameGroup')->assertHasNoErrors()
            ->assertSet('editingVersion', 4)->assertSee('Group name saved.')
            ->call('deleteGroup')->assertHasNoErrors()->assertSet('editingGroupId', null)
            ->assertSet('panel', '')->assertSee('Empty group deleted.');
        $this->assertDatabaseCount('tournament_groups', 0);
        $this->assertDatabaseCount('group_teams', 0);
        $this->assertSame($before, $team->fresh()->getAttributes());
    }

    public function test_inline_validation_preserves_names_and_team_selection(): void
    {
        $this->scorer();
        $team = Team::factory()->create(['approved' => false]);
        $component = Volt::test('scorer.groups-manager')
            ->set('newGroupName', 'A')->call('createGroup');
        $component->set('newGroupName', ' a ')->call('createGroup')
            ->assertHasErrors(['newGroupName'])->assertSet('newGroupName', ' a ')
            ->assertSee('A group with this name already exists')
            ->set('selectedTeamId', (string) $team->id)->call('assignTeam')
            ->assertHasErrors(['selectedTeamId'])->assertSet('selectedTeamId', (string) $team->id)
            ->assertSee('Select an existing approved team.');
        $this->assertDatabaseCount('group_teams', 0);
    }

    public function test_stale_editor_keeps_inputs_until_explicit_reload(): void
    {
        $this->scorer();
        $component = Volt::test('scorer.groups-manager')->set('newGroupName', 'A')->call('createGroup');
        $group = TournamentGroup::firstOrFail();
        DB::table('tournament_groups')->where('id', $group->id)->update(['name' => 'Another scorer', 'version' => 2]);
        $component->set('groupName', 'My unsaved name')->call('renameGroup')
            ->assertHasErrors(['tournament'])->assertSet('groupName', 'My unsaved name')
            ->assertSet('editingVersion', 1)->assertSee('Reload it before saving.')
            ->call('reloadGroups')->assertHasNoErrors()->assertSet('groupName', 'Another scorer')
            ->assertSet('editingVersion', 2);
        $this->assertSame('Another scorer', $group->fresh()->name);
    }

    public function test_group_selector_is_dynamic_year_scoped_and_read_only_actions_do_not_spend_write_budget(): void
    {
        $user = $this->scorer();
        $component = Volt::test('scorer.groups-manager')->set('newGroupName', 'Custom label')->call('createGroup');
        $group = TournamentGroup::firstOrFail();
        $key = 'tournament:scorer-write:'.$user->id;
        $before = RateLimiter::attempts($key);
        $component->call('openGroup', $group->id)->call('reloadGroups')->assertHasNoErrors();
        $this->assertSame($before, RateLimiter::attempts($key));
        $other = DB::table('tournament_groups')->insertGetId(['tournament_year' => 2027, 'name' => 'Other year']);
        $component->call('openGroup', $other)->assertHasErrors(['group'])->assertDontSee('Other year');
    }

    public static function lockedFields(): array
    {
        return [['editingGroupId'], ['editingVersion']];
    }

    #[DataProvider('lockedFields')]
    public function test_reviewed_group_identity_and_version_cannot_be_changed_as_form_fields(string $field): void
    {
        $this->scorer();
        $component = Volt::test('scorer.groups-manager')->set('newGroupName', 'A')->call('createGroup');
        $this->expectException(CannotUpdateLockedPropertyException::class);
        $component->set($field, 999999);
    }

    public static function nonScorers(): array
    {
        return [['guest'], ['representative'], ['admin'], ['pending']];
    }

    #[DataProvider('nonScorers')]
    public function test_component_rejects_non_scorers(string $role): void
    {
        config(['tournament.enabled' => true]);
        if ($role !== 'guest') {
            $user = User::factory()->create([
                'is_admin' => $role === 'admin',
                'company_id' => $role === 'representative' ? Company::factory()->create()->id : null,
            ]);
            Livewire::withCookie('company_token', (new JWTService)->generateToken($user));
        }
        Volt::test('scorer.groups-manager')->assertForbidden();
        $this->assertDatabaseCount('tournament_groups', 0);
    }

    public function test_actual_page_updates_create_assign_and_reject_revoked_scorer(): void
    {
        $user = $this->scorer();
        $this->signedIn($user);
        Route::middleware('web')->group(base_path('routes/tournament.php'));
        Route::getRoutes()->refreshNameLookups();
        $team = Team::factory()->create(['approved' => true, 'team_name' => '<script>Injected</script>']);
        $html = $this->get('/scorer/groups')->assertOk()->assertSee('Group setup')->getContent();
        preg_match('/wire:snapshot="([^"]+)"/', $html, $matches);
        $snapshot = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $update = function (string $snapshot, string $method, array $updates = []) {
            return $this->withCredentials()->postJson('/livewire/update', ['components' => [[
                'snapshot' => $snapshot, 'updates' => $updates,
                'calls' => [['path' => '', 'method' => $method, 'params' => []]],
            ]]], ['X-Livewire' => 'true']);
        };
        $response = $update($snapshot, 'createGroup', ['newGroupName' => 'HTTP group'])->assertOk();
        $group = TournamentGroup::firstOrFail();
        $snapshot = $response->json('components.0.snapshot');
        $response = $update($snapshot, 'assignTeam', ['selectedTeamId' => (string) $team->id])->assertOk();
        $this->assertStringContainsString('&lt;script&gt;Injected&lt;/script&gt;', $response->json('components.0.effects.html'));
        $this->assertDatabaseHas('group_teams', ['group_id' => $group->id, 'team_id' => $team->id]);
        DB::table('users')->where('id', $user->id)->update(['is_scorer' => false]);
        $update($response->json('components.0.snapshot'), 'deleteGroup')->assertForbidden();
        $this->assertDatabaseCount('tournament_groups', 1);
        $this->assertDatabaseCount('group_teams', 1);
    }

    public static function writeMethods(): array
    {
        return [['createGroup'], ['renameGroup'], ['assignTeam'], ['removeTeam'], ['deleteGroup']];
    }

    #[DataProvider('writeMethods')]
    public function test_each_direct_write_rejects_a_revoked_scorer(string $method): void
    {
        $actor = $this->scorer();
        $team = Team::factory()->create(['approved' => true]);
        $component = Volt::test('scorer.groups-manager')->set('newGroupName', 'A')->call('createGroup')
            ->set('newGroupName', 'Injected')->set('groupName', 'Injected')
            ->set('selectedTeamId', (string) $team->id);
        DB::table('users')->where('id', $actor->id)->update(['is_scorer' => false]);
        $component->call($method, ...($method === 'removeTeam' ? [$team->id] : []))->assertForbidden();
        $this->assertDatabaseCount('tournament_groups', 1);
        $this->assertSame('A', TournamentGroup::firstOrFail()->name);
        $this->assertDatabaseCount('group_teams', 0);
    }

    private function groupPageSnapshot(): string
    {
        Route::middleware('web')->group(base_path('routes/tournament.php'));
        Route::getRoutes()->refreshNameLookups();
        $html = $this->get('/scorer/groups')->assertOk()->getContent();
        preg_match('/wire:snapshot="([^"]+)"/', $html, $matches);

        return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function groupUpdate(string $snapshot, string $method, array $updates = [], array $params = []): \Illuminate\Testing\TestResponse
    {
        return $this->withCredentials()->postJson('/livewire/update', ['components' => [[
            'snapshot' => $snapshot, 'updates' => $updates,
            'calls' => [['path' => '', 'method' => $method, 'params' => $params]],
        ]]], ['X-Livewire' => 'true']);
    }

    public function test_real_http_workflow_removes_renames_and_deletes_only_feature_rows(): void
    {
        $this->signedIn($this->scorer());
        $team = Team::factory()->create(['approved' => true]);
        $before = $team->fresh()->getAttributes();
        $response = $this->groupUpdate($this->groupPageSnapshot(), 'createGroup', ['newGroupName' => 'HTTP lifecycle'])->assertOk();
        $response = $this->groupUpdate($response->json('components.0.snapshot'), 'assignTeam', ['selectedTeamId' => (string) $team->id])->assertOk();
        $this->assertDatabaseCount('group_teams', 1);
        $response = $this->groupUpdate($response->json('components.0.snapshot'), 'removeTeam', params: [$team->id])->assertOk();
        $this->assertDatabaseCount('group_teams', 0);
        $response = $this->groupUpdate($response->json('components.0.snapshot'), 'renameGroup', ['groupName' => 'HTTP renamed'])->assertOk();
        $this->assertSame('HTTP renamed', TournamentGroup::firstOrFail()->name);
        $this->groupUpdate($response->json('components.0.snapshot'), 'deleteGroup')->assertOk();
        $this->assertDatabaseCount('tournament_groups', 0);
        $this->assertSame($before, $team->fresh()->getAttributes());
    }

    public function test_two_real_editors_cannot_overwrite_a_newer_group_name(): void
    {
        $first = $this->scorer();
        $this->signedIn($first);
        $response = $this->groupUpdate($this->groupPageSnapshot(), 'createGroup', ['newGroupName' => 'A'])->assertOk();
        $firstSnapshot = $response->json('components.0.snapshot');
        $group = TournamentGroup::firstOrFail();
        $second = $this->scorer();
        $this->signedIn($second);
        $response = $this->groupUpdate($this->groupPageSnapshot(), 'openGroup', params: [$group->id])->assertOk();
        $this->groupUpdate($response->json('components.0.snapshot'), 'renameGroup', ['groupName' => 'New official label'])->assertOk();
        $this->signedIn($first);
        $response = $this->groupUpdate($firstSnapshot, 'renameGroup', ['groupName' => 'Stale label'])->assertOk();
        $this->assertStringContainsString('Reload it before saving', $response->json('components.0.effects.html'));
        $this->assertSame('Stale label', json_decode($response->json('components.0.snapshot'), true)['data']['groupName']);
        $this->assertSame('New official label', $group->fresh()->name);
        $this->assertSame(2, $group->fresh()->version);
    }

    public function test_disabled_feature_blocks_a_previously_valid_group_editor(): void
    {
        $this->signedIn($this->scorer());
        $snapshot = $this->groupPageSnapshot();
        config(['tournament.enabled' => false]);
        $this->groupUpdate($snapshot, 'createGroup', ['newGroupName' => 'Injected'])->assertNotFound();
        $this->assertDatabaseCount('tournament_groups', 0);
    }

    public function test_fixture_protection_is_rendered_inline_without_removing_assignment(): void
    {
        $this->scorer();
        $teams = Team::factory()->count(2)->create(['approved' => true]);
        $component = Volt::test('scorer.groups-manager')->set('newGroupName', 'A')->call('createGroup');
        foreach ($teams as $team) {
            $component->set('selectedTeamId', (string) $team->id)->call('assignTeam');
        }
        $group = TournamentGroup::firstOrFail();
        DB::table('matches')->insert([
            'tournament_year' => 2026, 'group_id' => $group->id, 'stage' => 'G',
            'team1_id' => $teams[0]->id, 'team2_id' => $teams[1]->id, 'balls_per_over' => 4,
        ]);
        $component->call('removeTeam', $teams[0]->id)->assertHasErrors(['group'])
            ->assertSee('This group already has fixtures.');
        $this->assertDatabaseCount('group_teams', 2);
    }
}
