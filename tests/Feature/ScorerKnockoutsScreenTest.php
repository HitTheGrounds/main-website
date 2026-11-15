<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\GroupTeam;
use App\Models\Team;
use App\Models\TournamentMatch;
use App\Models\User;
use App\Services\JWTService;
use App\Services\Tournament\MatchResultService;
use App\Services\Tournament\MatchSchedulingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ScorerKnockoutsScreenTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(int $count = 4): array
    {
        config(['tournament.enabled' => true, 'tournament.year' => 2026, 'tournament.writes_per_minute' => 1000]);
        Route::middleware('web')->group(base_path('routes/tournament.php'));
        Route::getRoutes()->refreshNameLookups();
        $actor = User::factory()->create(['is_admin' => false, 'company_id' => null]);
        $actor->is_scorer = true;
        $actor->save();
        $this->actAs($actor);
        Livewire::withCookie('company_token', (new JWTService)->generateToken($actor));
        $id = DB::table('tournament_groups')->insertGetId(['name' => 'Flexible group', 'tournament_year' => 2026]);
        $teams = Team::factory()->count($count)->create(['approved' => true]);
        foreach ($teams as $index => $team) {
            DB::table('group_teams')->insert(['group_id' => $id, 'tournament_year' => 2026, 'team_id' => $team->id,
                'points' => $index, 'nrr' => null]);
        }

        return [$actor, $teams, $id];
    }

    private function knockoutScreen()
    {
        return Volt::test('scorer.knockouts-manager');
    }

    private function fixtureInput($a, $b, int $position = 1): array
    {
        return ['team1_id' => (string) $a, 'team2_id' => (string) $b, 'bracket_position' => (string) $position, 'balls_per_over' => '4'];
    }

    private function official(TournamentMatch $match): TournamentMatch
    {
        $service = app(MatchResultService::class);
        $service->saveDraft(2026, $match->id, $match->fresh()->version, ['result_type' => 'win', 'result_method' => 'normal',
            'winner_id' => $match->team1_id, 'team1_runs' => 100, 'team1_wickets' => 3, 'team1_overs' => 5, 'team1_balls' => 0,
            'team2_runs' => 90, 'team2_wickets' => 3, 'team2_overs' => 5, 'team2_balls' => 0]);
        $review = $service->review(2026, $match->id);

        return $service->publish(2026, $match->id, $review['version'], $review['draft_hash']);
    }

    public function test_route_empty_rounds_and_navigation_use_existing_private_layout(): void
    {
        [$actor] = $this->fixture(0);
        $this->signedIn($actor)->get('/scorer/knockouts')->assertOk()
            ->assertSee('Qualification &amp; bracket', false)->assertSee('noindex, nofollow')
            ->assertSee('No teams assigned.')->assertSee('To be confirmed')->assertDontSee('wire:poll', false);
        $this->knockoutScreen()->call('chooseRound', 'SF')->assertHasNoErrors()->assertSee('Semi-finals')
            ->assertSet('fixture.balls_per_over', '6')->assertSee('To be confirmed')
            ->call('chooseRound', 'F')->assertHasNoErrors()->assertSee('Final');
    }

    public function test_partial_empty_and_non_leader_selections_save_without_complete_groups(): void
    {
        [, $teams] = $this->fixture();
        $component = $this->knockoutScreen()->assertSee('Incomplete group results. Selection is still allowed.')
            ->assertSee('Some NRR values are unknown.')
            ->set('selected', [$teams[0]->id])->call('saveSelection')->assertHasNoErrors()
            ->assertSet('selection.selected', [$teams[0]->id])->assertSee('Saved qualifier');
        $this->assertTrue(GroupTeam::where('team_id', $teams[0]->id)->firstOrFail()->qualified);
        $component->set('selected', [])->call('saveSelection')->assertHasNoErrors()->assertSet('selection.selected', []);
        $component->set('selected', $teams->take(3)->pluck('id')->all())->call('saveSelection')->assertHasNoErrors();
        $this->assertSame(3, GroupTeam::where('qualified', true)->count());
    }

    public function test_stale_selection_preserves_unsaved_choices_until_explicit_reload(): void
    {
        [, $teams, $groupId] = $this->fixture();
        $component = $this->knockoutScreen()->set('selected', [$teams[0]->id]);
        DB::table('tournament_groups')->where('id', $groupId)->increment('version');
        $component->call('saveSelection')->assertHasErrors('tournament')->assertSet('selected', [$teams[0]->id]);
        $this->assertSame(0, GroupTeam::where('qualified', true)->count());
        $component->call('reloadSelection')->assertHasNoErrors()->assertSet('selected', []);
    }

    public function test_qualification_capacity_and_invalid_ids_are_enforced_without_losing_choices(): void
    {
        [, $teams] = $this->fixture(9);
        $component = $this->knockoutScreen()->set('selected', $teams->pluck('id')->all())
            ->call('saveSelection')->assertHasErrors('teamIds');
        $component->set('selected', [999999])->call('saveSelection')->assertHasErrors('tournament')
            ->assertSet('selected', [999999]);
        $this->assertSame(0, GroupTeam::where('qualified', true)->count());
    }

    public function test_manual_quarter_creation_requires_reload_and_keeps_pending_input_on_stale_revision(): void
    {
        [, $teams] = $this->fixture();
        $component = $this->knockoutScreen()->set('selected', $teams->pluck('id')->all())->call('saveSelection');
        $input = $this->fixtureInput($teams[0]->id, $teams[1]->id, 4);
        $component->set('fixture', $input)->call('createMatch')->assertHasErrors('tournament')->assertSet('fixture', $input)
            ->call('reloadRound')->assertHasNoErrors()->set('fixture', $input)->call('createMatch')->assertHasNoErrors()
            ->assertSee('Knockout match #')->assertSee('QF 4');
        $match = TournamentMatch::firstOrFail();
        $this->assertSame(4, $match->bracket_position);
        $this->assertNull($match->team1_runs);
        $component->set('fixture', $this->fixtureInput($teams[2]->id, $teams[3]->id, 1));
        app(MatchSchedulingService::class)->updateSchedule(2026, $match->id, $match->version, ['venue' => 'Other scorer']);
        $component->call('createMatch')->assertHasErrors('tournament')->assertSet('fixture.bracket_position', '1');
        $this->assertDatabaseCount('matches', 1);
    }

    public function test_position_review_supports_swap_and_rejects_stale_target(): void
    {
        [, $teams] = $this->fixture();
        $component = $this->knockoutScreen()->set('selected', $teams->pluck('id')->all())->call('saveSelection')->call('reloadRound');
        $component->set('fixture', $this->fixtureInput($teams[0]->id, $teams[1]->id, 1))->call('createMatch')
            ->set('fixture', $this->fixtureInput($teams[2]->id, $teams[3]->id, 4))->call('createMatch')->assertHasNoErrors();
        [$first, $second] = TournamentMatch::orderBy('id')->get()->all();
        $component->call('prepareMove', $first->id, 4)->assertHasNoErrors()->assertSee('Review position change');
        DB::table('matches')->where('id', $second->id)->increment('version');
        $component->call('moveMatch')->assertHasErrors('tournament')->assertSet('moveReview.to', 4);
        $this->assertSame(1, $first->fresh()->bracket_position);
        $component->call('reloadRound')->call('prepareMove', $first->id, 4)->call('moveMatch')->assertHasNoErrors()
            ->assertSet('moveReview', null)->assertSee('Quarter-final positions updated.');
        $this->assertSame(4, $first->fresh()->bracket_position);
        $this->assertSame(1, $second->fresh()->bracket_position);
        $component->call('prepareMove', $first->id, 2)->call('cancelMove')->assertSet('moveReview', null);
        $this->assertSame(4, $first->fresh()->bracket_position);
    }

    public function test_dynamic_groups_show_coverage_and_provisional_runner_guidance(): void
    {
        [, , $first] = $this->fixture(2);
        foreach (range(2, 7) as $i) {
            $group = DB::table('tournament_groups')->insertGetId(['name' => 'Custom group '.$i, 'tournament_year' => 2026]);
            foreach (Team::factory()->count($i === 7 ? 3 : 2)->create(['approved' => true]) as $team) {
                DB::table('group_teams')->insert(['group_id' => $group, 'tournament_year' => 2026, 'team_id' => $team->id]);
            }
        }
        $component = $this->knockoutScreen()->assertSee('Flexible group')->assertSee('Custom group 7')
            ->assertSee('Group sizes differ.')->assertSee('Joint positions exist.')
            ->assertSee('Runner-up guidance (points, then NRR)')->assertSet('selection.capacity', 8);
        $this->assertCount(7, $component->get('selection')['groups']);
    }

    public function test_published_prior_winners_are_chosen_manually_and_final_shows_champion(): void
    {
        [, $teams] = $this->fixture(8);
        $component = $this->knockoutScreen()->set('selected', $teams->pluck('id')->all())->call('saveSelection')->call('reloadRound');
        $quarters = [];
        foreach (range(0, 3) as $i) {
            $component->set('fixture', $this->fixtureInput($teams[$i * 2]->id, $teams[$i * 2 + 1]->id, $i + 1))->call('createMatch')->assertHasNoErrors();
            $quarters[] = TournamentMatch::latest('id')->firstOrFail();
        }
        foreach ($quarters as $match) {
            $this->official($match);
        }
        $component->call('chooseRound', 'SF')->assertSet('fixture.balls_per_over', '6');
        foreach ([[6, 0, 2], [2, 4, 1]] as [$a, $b, $position]) {
            $component->set('fixture', $this->fixtureInput($teams[$a]->id, $teams[$b]->id, $position))->call('createMatch')->assertHasNoErrors();
        }
        $component->assertSee('winner of QF match #'.$quarters[3]->id);
        foreach (TournamentMatch::where('stage', 'SF')->get() as $match) {
            $this->official($match);
        }
        $component->call('chooseRound', 'F')->set('fixture', $this->fixtureInput($teams[6]->id, $teams[2]->id))->call('createMatch')->assertHasNoErrors();
        $final = TournamentMatch::where('stage', 'F')->firstOrFail();
        $this->official($final);
        $component->call('reloadRound')->assertSee('Champion: '.$teams[6]->team_name)->assertSee('All positions in this round are filled.');
        Volt::test('scorer.result-editor', ['matchId' => $final->id])->assertSee('Final', false)->assertDontSee('<option value="tie">', false);
    }

    public function test_browser_cannot_deselect_a_team_with_an_existing_knockout_fixture(): void
    {
        [, $teams] = $this->fixture();
        $component = $this->knockoutScreen()->set('selected', $teams->pluck('id')->all())->call('saveSelection')->call('reloadRound')
            ->set('fixture', $this->fixtureInput($teams[0]->id, $teams[1]->id))->call('createMatch')->call('reloadSelection');
        $component->set('selected', [$teams[2]->id, $teams[3]->id])->call('saveSelection')->assertHasErrors('tournament');
        $this->assertSame(4, GroupTeam::where('qualified', true)->count());
    }

    public static function lockedFields(): array
    {
        return [['selection', []], ['round', []], ['stage', 'F'], ['moveReview', ['version' => 999]]];
    }

    #[DataProvider('lockedFields')]
    public function test_browser_cannot_replace_server_snapshots_or_move_reviews(string $field, mixed $value): void
    {
        $this->fixture();
        $this->expectException(CannotUpdateLockedPropertyException::class);
        $this->knockoutScreen()->set($field, $value);
    }

    public static function roles(): array
    {
        return [['guest'], ['admin'], ['representative'], ['revoked'], ['disabled']];
    }

    #[DataProvider('roles')]
    public function test_page_rejects_non_scorer_or_disabled_access(string $role): void
    {
        [$actor] = $this->fixture();
        if ($role === 'guest') {
            Livewire::withCookie('company_token', '');
            request()->attributes->remove('user');
        } elseif ($role === 'disabled') {
            config(['tournament.enabled' => false]);
        } elseif ($role === 'revoked') {
            DB::table('users')->where('id', $actor->id)->update(['is_scorer' => false]);
        } else {
            $actor = User::factory()->create(['is_admin' => $role === 'admin', 'company_id' => $role === 'representative' ? Company::factory()->create()->id : null]);
            Livewire::withCookie('company_token', (new JWTService)->generateToken($actor));
            $this->actAs($actor);
        }
        $role === 'disabled' ? $this->knockoutScreen()->assertNotFound() : $this->knockoutScreen()->assertForbidden();
        $this->assertDatabaseCount('matches', 0);
    }

    public function test_actions_recheck_revoked_permission_and_escape_names_without_private_fields(): void
    {
        [$actor, $teams, $group] = $this->fixture();
        DB::table('tournament_groups')->where('id', $group)->update(['name' => '<script>group()</script>']);
        DB::table('teams')->where('id', $teams[0]->id)->update(['team_name' => '<script>team()</script>']);
        $component = $this->knockoutScreen()->assertDontSee('<script>group()</script>', false)->assertDontSee('<script>team()</script>', false)
            ->set('selected', [$teams[0]->id]);
        $this->assertArrayNotHasKey('email', $component->get('selection')['groups'][0]['teams'][0]);
        DB::table('users')->where('id', $actor->id)->update(['is_scorer' => false]);
        $component->call('saveSelection')->assertForbidden();
        $this->assertSame(0, GroupTeam::where('qualified', true)->count());
    }

    public function test_signed_http_snapshot_requires_csrf_and_rejects_permission_revocation(): void
    {
        [$actor, $teams] = $this->fixture();
        $this->app->bind(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $this->signedIn($actor)->withSession(['_token' => 'knockout-test-token']);
        $html = $this->get('/scorer/knockouts')->assertOk()->getContent();
        preg_match('/wire:snapshot="([^"]+)"/', $html, $matches);
        $snapshot = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $payload = ['components' => [[
            'snapshot' => $snapshot, 'updates' => ['selected' => $teams->pluck('id')->all()],
            'calls' => [['path' => '', 'method' => 'saveSelection', 'params' => []]],
        ]]];
        $this->withCredentials()->postJson('/livewire/update', $payload, ['X-Livewire' => 'true'])->assertStatus(419);
        $this->assertSame(0, GroupTeam::where('qualified', true)->count());
        $payload['_token'] = 'knockout-test-token';
        $response = $this->withCredentials()->postJson('/livewire/update', $payload, ['X-Livewire' => 'true'])->assertOk();
        $this->assertSame(4, GroupTeam::where('qualified', true)->count());
        $payload['components'][0] = ['snapshot' => $response->json('components.0.snapshot'), 'updates' => [],
            'calls' => [['path' => '', 'method' => 'reloadRound', 'params' => []]]];
        $response = $this->withCredentials()->postJson('/livewire/update', $payload, ['X-Livewire' => 'true'])->assertOk();
        $payload['components'][0] = ['snapshot' => $response->json('components.0.snapshot'),
            'updates' => ['fixture' => $this->fixtureInput($teams[0]->id, $teams[1]->id)],
            'calls' => [['path' => '', 'method' => 'createMatch', 'params' => []]]];
        $withoutToken = $payload;
        unset($withoutToken['_token']);
        $this->withCredentials()->postJson('/livewire/update', $withoutToken, ['X-Livewire' => 'true'])->assertStatus(419);
        $this->assertDatabaseCount('matches', 0);
        $response = $this->withCredentials()->postJson('/livewire/update', $payload, ['X-Livewire' => 'true'])->assertOk();
        $this->assertDatabaseCount('matches', 1);
        DB::table('users')->where('id', $actor->id)->update(['is_scorer' => false]);
        $payload['components'][0] = ['snapshot' => $response->json('components.0.snapshot'), 'updates' => [],
            'calls' => [['path' => '', 'method' => 'reloadRound', 'params' => []]]];
        $this->withCredentials()->postJson('/livewire/update', $payload, ['X-Livewire' => 'true'])->assertForbidden();
        $this->assertDatabaseCount('matches', 1);
    }

    public function test_dashboard_reopens_restores_and_reports_real_later_round_dependency(): void
    {
        [, $teams] = $this->fixture();
        $component = $this->knockoutScreen()->set('selected', $teams->pluck('id')->all())->call('saveSelection')->call('reloadRound');
        foreach ([[0, 1, 1], [2, 3, 2]] as [$a, $b, $position]) {
            $component->set('fixture', $this->fixtureInput($teams[$a]->id, $teams[$b]->id, $position))->call('createMatch')->assertHasNoErrors();
        }
        [$first, $second] = TournamentMatch::orderBy('id')->get()->all();
        $first = $this->official($first);
        $this->official($second);
        app(MatchResultService::class)->saveDraft(2026, $first->id, $first->version, ['team1_runs' => 0]);
        $dashboard = Volt::test('scorer.matches-manager')->call('openMatch', $first->id)
            ->assertSee('Reopen match')->call('changeResultStatus', 'live')->assertHasNoErrors()->assertSee('Match reopened.')
            ->assertSee('Restore official result')->call('changeResultStatus', 'finished')->assertHasNoErrors()
            ->assertSee('Stored official result restored.');
        $this->assertSame(100, $first->fresh()->team1_runs);
        $this->assertSame(['team1_runs' => 0], $first->fresh()->draft_result);
        $component->call('chooseRound', 'SF')->set('fixture', $this->fixtureInput($teams[2]->id, $teams[0]->id))->call('createMatch')->assertHasNoErrors();
        $sf = TournamentMatch::where('stage', 'SF')->firstOrFail();
        $dashboard->call('reloadMatch')->call('changeResultStatus', 'live')->assertHasErrors('tournament')
            ->assertSee('SF match #'.$sf->id);
        $this->assertSame('finished', $first->fresh()->status);
        $this->assertSame($sf->getAttributes(), $sf->fresh()->getAttributes());
    }

    public function test_unpublished_knockout_schedule_is_editable_but_cannot_finish_without_result_publication(): void
    {
        [, $teams] = $this->fixture();
        $this->knockoutScreen()->set('selected', $teams->pluck('id')->all())->call('saveSelection')->call('reloadRound')
            ->set('fixture', $this->fixtureInput($teams[0]->id, $teams[1]->id))->call('createMatch')->assertHasNoErrors();
        $match = TournamentMatch::firstOrFail();
        Volt::test('scorer.matches-manager')->assertSee(route('scorer.matches.result', ['matchId' => $match->id]), false)
            ->call('openMatch', $match->id)->assertSet('statusEditable', true)->assertSet('battingEditable', true)
            ->set('schedule.status', 'live')->set('schedule.batting_first_id', $teams[1]->id)->call('saveSchedule')->assertHasNoErrors()
            ->call('changeResultStatus', 'finished')->assertHasErrors('tournament');
        $this->assertSame('live', $match->fresh()->status);
        $this->assertSame($teams[1]->id, $match->fresh()->batting_first_id);
        $this->assertNull($match->fresh()->result_published_at);
    }
}
