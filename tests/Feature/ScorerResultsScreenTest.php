<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\GroupTeam;
use App\Models\Team;
use App\Models\TournamentGroup;
use App\Models\TournamentMatch;
use App\Models\User;
use App\Services\JWTService;
use App\Services\Tournament\MatchResultService;
use App\Services\Tournament\ScorerStandingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ScorerResultsScreenTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        config(['tournament.enabled' => true, 'tournament.year' => 2026, 'tournament.writes_per_minute' => 1000]);
        Route::middleware('web')->group(base_path('routes/tournament.php'));
        Route::getRoutes()->refreshNameLookups();
        $actor = User::factory()->create(['is_admin' => false, 'company_id' => null]);
        $actor->is_scorer = true;
        $actor->save();
        Livewire::withCookie('company_token', (new JWTService)->generateToken($actor));
        request()->attributes->set('user', $actor);
        $groupId = DB::table('tournament_groups')->insertGetId(['name' => 'Group A', 'tournament_year' => 2026]);
        $teams = Team::factory()->count(2)->create(['approved' => true]);
        foreach ($teams as $team) {
            DB::table('group_teams')->insert(['group_id' => $groupId, 'tournament_year' => 2026, 'team_id' => $team->id]);
        }
        $id = DB::table('matches')->insertGetId(['stage' => 'G', 'group_id' => $groupId, 'tournament_year' => 2026,
            'team1_id' => $teams[0]->id, 'team2_id' => $teams[1]->id, 'balls_per_over' => 4]);

        return [$actor, TournamentMatch::findOrFail($id)];
    }

    private function input(TournamentMatch $match): array
    {
        return ['result_type' => 'win', 'result_method' => 'normal', 'winner_id' => $match->team1_id,
            'batting_first_id' => $match->team1_id, 'team1_runs' => 100, 'team1_wickets' => 3,
            'team1_overs' => 5, 'team1_balls' => 0, 'team2_runs' => 90, 'team2_wickets' => 11,
            'team2_overs' => 3, 'team2_balls' => 0];
    }

    public function test_result_route_and_draft_review_publish_without_player_figures(): void
    {
        [$actor, $match] = $this->fixture();
        $this->signedIn($actor)->get('/scorer/matches/'.$match->id.'/result')->assertOk()
            ->assertSee('Match result')->assertSee('noindex, nofollow');
        Volt::test('scorer.result-editor', ['matchId' => $match->id])
            ->set('form.team1_runs', '0')->call('saveDraft')->assertHasNoErrors()->assertSet('hasDraft', true)
            ->assertSee('No team result has been published.')
            ->set('form', $this->input($match))->call('reviewResult')->assertHasNoErrors()->assertSee('Review before publishing')
            ->call('publishResult')->assertHasNoErrors()->assertSet('hasDraft', false)->assertSet('review', null)
            ->assertSee('Team result published');
        $this->assertSame('finished', $match->fresh()->status);
        $this->assertSame(2, GroupTeam::where('team_id', $match->team1_id)->firstOrFail()->points);
        $this->assertDatabaseCount('player_match_stats', 0);
    }

    public function test_unsaved_edits_invalidate_review_and_stale_publication_preserves_draft(): void
    {
        [, $match] = $this->fixture();
        $component = Volt::test('scorer.result-editor', ['matchId' => $match->id])
            ->set('form', $this->input($match))->call('reviewResult')->assertHasNoErrors();
        $component->set('form.team1_runs', 102)->assertSet('review', null)
            ->call('publishResult')->assertHasErrors('tournament');
        $component->call('reviewResult')->assertHasNoErrors();
        DB::table('matches')->where('id', $match->id)->increment('version');
        $component->call('publishResult')->assertHasErrors('tournament')->assertSet('form.team1_runs', 102);
        $this->assertNull($match->fresh()->result_published_at);
        $this->assertSame(102, $match->fresh()->draft_result['team1_runs']);
        $component->call('reloadResult')->assertHasNoErrors()->call('discardDraft')->assertHasNoErrors()->assertSet('hasDraft', false);
        $this->assertNull($match->fresh()->draft_result);
    }

    public function test_incomplete_drafts_and_invalid_hidden_fields_cannot_be_published(): void
    {
        [, $match] = $this->fixture();
        $component = Volt::test('scorer.result-editor', ['matchId' => $match->id])
            ->set('form.team1_runs', 0)->call('reviewResult')->assertHasErrors('result_type')
            ->assertSet('review', null)->assertSet('hasDraft', true)
            ->set('form.published_by', 999)->call('saveDraft')->assertHasErrors('tournament');
        $this->assertNull($match->fresh()->result_published_at);
        $this->assertSame(0, $match->fresh()->draft_result['team1_runs']);
    }

    public function test_exceptional_revised_target_review_shows_official_nrr_inputs(): void
    {
        [, $match] = $this->fixture();
        $input = array_replace($this->input($match), ['result_method' => 'revised_target', 'revised_target' => 60,
            'winner_id' => $match->team2_id, 'result_note' => '<script>official</script>', 'nrr_inputs' => [
                'included' => true, 'reason' => 'Committee figures', 'team1' => ['runs' => 70, 'overs' => 4, 'balls' => 0],
                'team2' => ['runs' => 61, 'overs' => 3, 'balls' => 2]]]);
        Volt::test('scorer.result-editor', ['matchId' => $match->id])->set('form', $input)->call('reviewResult')
            ->assertHasNoErrors()->assertSee('70 runs, 4 overs, 0 balls.')->assertSee('61 runs, 3 overs, 2 balls.')
            ->assertSee('&lt;script&gt;official&lt;/script&gt;', false)->assertDontSee('<script>official</script>', false)
            ->call('publishResult')->assertHasNoErrors();
        $this->assertSame(14, $match->fresh()->nrr_inputs['team2']['deliveries']);
    }

    public function test_published_correction_keeps_official_score_until_publication_and_batting_order_locked(): void
    {
        [, $match] = $this->fixture();
        $service = app(MatchResultService::class);
        $service->saveDraft(2026, $match->id, 1, $this->input($match));
        $review = $service->review(2026, $match->id);
        $service->publish(2026, $match->id, $review['version'], $review['draft_hash']);
        $service->saveDraft(2026, $match->id, $match->fresh()->version, ['team1_runs' => 102]);
        $component = Volt::test('scorer.result-editor', ['matchId' => $match->id])
            ->assertSet('form.batting_first_id', $match->team1_id)
            ->assertSet('official.team1_runs', 100)->assertSee('Batting order and match format are locked');
        $component->set('form', array_replace($this->input($match), ['team1_runs' => 103]))
            ->call('saveDraft')->assertHasNoErrors()->assertSet('official.team1_runs', 100)
            ->call('reviewResult')->call('publishResult')->assertHasNoErrors()->assertSet('official.team1_runs', 103);
        $component->set('form.batting_first_id', $match->team2_id)->call('saveDraft')->assertHasErrors('batting_first_id');
    }

    public function test_standings_adjustment_requires_reason_and_keeps_correction_history(): void
    {
        [$actor, $match] = $this->fixture();
        $this->signedIn($actor)->get('/scorer/standings')->assertOk()->assertSee('Group standings');
        $component = Volt::test('scorer.standings-manager')->assertSee('Group A')
            ->call('openAdjustment', $match->group_id, $match->team1_id)
            ->set('points', '-1')->set('reason', '   ')->call('saveAdjustment')->assertHasErrors('reason')
            ->set('reason', '<script>Penalty</script>')->call('saveAdjustment')->assertHasNoErrors()
            ->assertSee('&lt;script&gt;Penalty&lt;/script&gt;', false)->assertDontSee('<script>Penalty</script>', false);
        $row = GroupTeam::where('team_id', $match->team1_id)->firstOrFail();
        $this->assertSame(-1, $row->points);
        $history = $row->point_adjustments;
        $this->assertSame($actor->id, $history[0]['actor_id']);
        $this->assertNotEmpty($history[0]['recorded_at']);
        $component->call('openAdjustment', $match->group_id, $match->team1_id)
            ->set('points', '1')->set('reason', 'Penalty corrected')->call('saveAdjustment')->assertHasNoErrors();
        $this->assertSame(0, $row->fresh()->points);
        $this->assertSame($history[0], $row->fresh()->point_adjustments[0]);
        $this->assertCount(2, $row->fresh()->point_adjustments);
    }

    public function test_stale_adjustment_does_not_overwrite_history_or_reset_qualification(): void
    {
        [, $match] = $this->fixture();
        DB::table('group_teams')->where('team_id', $match->team1_id)->update(['qualified' => true]);
        $component = Volt::test('scorer.standings-manager')->call('openAdjustment', $match->group_id, $match->team1_id)
            ->set('points', '-1')->set('reason', 'My penalty');
        app(ScorerStandingsService::class)->adjust(2026, $match->group_id, 1, $match->team2_id, ['points' => -1, 'reason' => 'Other penalty']);
        $component->call('saveAdjustment')->assertHasErrors('tournament')->assertSet('reason', 'My penalty');
        $row = GroupTeam::where('team_id', $match->team1_id)->firstOrFail();
        $this->assertTrue($row->qualified);
        $this->assertSame([], $row->point_adjustments);
        $component->call('openAdjustment', $match->group_id, $match->team1_id)
            ->set('points', '-1')->set('reason', 'My penalty')->call('saveAdjustment')->assertHasNoErrors();
        $this->assertTrue($row->fresh()->qualified);
    }

    public function test_adjustment_is_preserved_when_result_is_published_and_corrected(): void
    {
        [, $match] = $this->fixture();
        app(ScorerStandingsService::class)->adjust(2026, $match->group_id, 1, $match->team1_id, ['points' => -1, 'reason' => 'Official penalty']);
        Volt::test('scorer.result-editor', ['matchId' => $match->id])->set('form', $this->input($match))->call('reviewResult')->call('publishResult')->assertHasNoErrors();
        $row = GroupTeam::where('team_id', $match->team1_id)->firstOrFail();
        $this->assertSame(2, $row->base_points);
        $this->assertSame(1, $row->points);
        $history = $row->point_adjustments;
        Volt::test('scorer.result-editor', ['matchId' => $match->id])->set('form.team1_runs', 101)->call('reviewResult')->call('publishResult')->assertHasNoErrors();
        $this->assertSame(1, $row->fresh()->points);
        $this->assertSame($history, $row->fresh()->point_adjustments);
    }

    public function test_adjustment_failure_rolls_back_history_totals_and_versions(): void
    {
        [, $match] = $this->fixture();
        $rows = GroupTeam::orderBy('id')->get()->map->getAttributes()->all();
        $group = TournamentGroup::findOrFail($match->group_id)->getAttributes();
        $event = 'eloquent.saving: '.TournamentGroup::class;
        Event::listen($event, fn () => throw new RuntimeException('Group save failed after standings writes'));
        try {
            app(ScorerStandingsService::class)->adjust(2026, $match->group_id, 1, $match->team1_id, ['points' => -1, 'reason' => 'Penalty']);
            $this->fail('Expected calculation failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Group save failed after standings writes', $exception->getMessage());
        } finally {
            Event::forget($event);
        }
        $this->assertSame($rows, GroupTeam::orderBy('id')->get()->map->getAttributes()->all());
        $this->assertSame($group, TournamentGroup::findOrFail($match->group_id)->getAttributes());
    }

    public static function unauthorizedRoles(): array
    {
        return [['guest'], ['admin'], ['representative'], ['pending'], ['revoked'], ['disabled']];
    }

    #[DataProvider('unauthorizedRoles')]
    public function test_both_screens_and_adjustment_service_reject_unauthorized_actors(string $role): void
    {
        [$actor, $match] = $this->fixture();
        if ($role === 'guest') {
            Livewire::withCookie('company_token', '');
            request()->attributes->remove('user');
        } elseif ($role === 'revoked') {
            DB::table('users')->where('id', $actor->id)->update(['is_scorer' => false]);
        } elseif ($role === 'disabled') {
            config(['tournament.enabled' => false]);
        } else {
            $actor = User::factory()->create(['is_admin' => $role === 'admin', 'company_id' => $role === 'representative' ? Company::factory()->create()->id : null]);
            Livewire::withCookie('company_token', (new JWTService)->generateToken($actor));
            request()->attributes->set('user', $actor);
        }
        foreach ([Volt::test('scorer.result-editor', ['matchId' => $match->id]), Volt::test('scorer.standings-manager')] as $component) {
            $role === 'disabled' ? $component->assertNotFound() : $component->assertForbidden();
        }
        try {
            app(ScorerStandingsService::class)->adjust(2026, $match->group_id, 1, $match->team1_id, ['points' => -1, 'reason' => 'Unauthorized']);
            $this->fail('Expected permission denial.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame($role === 'disabled' ? 404 : 403, $exception->getStatusCode());
        }
        $this->assertSame(0, GroupTeam::where('team_id', $match->team1_id)->firstOrFail()->points);
    }

    public static function lockedFields(): array
    {
        return [['scorer.result-editor', 'matchId', 999], ['scorer.result-editor', 'version', 999],
            ['scorer.result-editor', 'review', ['version' => 999, 'draft_hash' => 'forged']],
            ['scorer.standings-manager', 'groupVersion', 999], ['scorer.standings-manager', 'teamId', 999]];
    }

    #[DataProvider('lockedFields')]
    public function test_browser_cannot_rewrite_review_identity_or_versions(string $name, string $field, mixed $value): void
    {
        [, $match] = $this->fixture();
        $this->expectException(CannotUpdateLockedPropertyException::class);
        Volt::test($name, $name === 'scorer.result-editor' ? ['matchId' => $match->id] : [])->set($field, $value);
    }

    public function test_adjustments_reject_other_years_outside_teams_invalid_values_and_injection(): void
    {
        [, $match] = $this->fixture();
        $service = app(ScorerStandingsService::class);
        foreach ([[2027, $match->team1_id, ['points' => -1, 'reason' => 'Penalty']],
            [2026, 999999, ['points' => -1, 'reason' => 'Penalty']], [2026, $match->team1_id, ['points' => 0, 'reason' => 'Penalty']],
            [2026, $match->team1_id, ['points' => '1.5', 'reason' => 'Penalty']],
            [2026, $match->team1_id, ['points' => -1, 'reason' => 'Penalty', 'actor_id' => 999]]] as [$year, $teamId, $input]) {
            try {
                $service->adjust($year, $match->group_id, 1, $teamId, $input);
                $this->fail('Expected invalid adjustment rejection.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
        $this->assertSame([], $service->listing(2027));
        $this->assertSame(0, GroupTeam::where('team_id', $match->team1_id)->firstOrFail()->points);
    }

    public static function groupOutcomes(): array
    {
        return [['tie'], ['no_result'], ['abandoned']];
    }

    #[DataProvider('groupOutcomes')]
    public function test_result_type_controls_publish_ties_and_undecided_matches(string $type): void
    {
        [, $match] = $this->fixture();
        $component = Volt::test('scorer.result-editor', ['matchId' => $match->id])
            ->set('form.winner_id', $match->team1_id)->set('form.result_type', $type)
            ->assertSet('form.winner_id', null)
            ->assertSet('form.result_method', $type === 'tie' ? 'normal' : null);
        if ($type === 'tie') {
            foreach (['team1', 'team2'] as $side) {
                foreach (['runs' => 0, 'wickets' => 11, 'overs' => 5, 'balls' => 0] as $field => $value) {
                    $component->set('form.'.$side.'_'.$field, $value);
                }
            }
        }
        $component->call('reviewResult')->assertHasNoErrors()->call('publishResult')->assertHasNoErrors();
        $this->assertSame($type, $match->fresh()->result_type);
        $this->assertNull($match->fresh()->winner_id);
        $this->assertSame(1, GroupTeam::where('team_id', $match->team1_id)->firstOrFail()->points);
    }

    public function test_adjustment_limits_and_storage_overflow_preserve_history(): void
    {
        [, $match] = $this->fixture();
        $row = GroupTeam::where('team_id', $match->team1_id)->firstOrFail();
        $service = app(ScorerStandingsService::class);
        foreach ([array_fill(0, 100, ['points' => -1, 'reason' => 'Prior penalty']),
            [['points' => -2147483647, 'reason' => 'Prior penalty']]] as $history) {
            $row->point_adjustments = $history;
            $row->save();
            try {
                $service->adjust(2026, $match->group_id, 1, $match->team1_id, ['points' => -2, 'reason' => 'New penalty']);
                $this->fail('Expected adjustment limit rejection.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('tournament', $exception->errors());
            }
            $this->assertSame($history, $row->fresh()->point_adjustments);
            $this->assertSame(1, TournamentGroup::findOrFail($match->group_id)->version);
        }
    }

    public function test_new_routes_keep_existing_role_and_feature_boundaries(): void
    {
        [$scorer, $match] = $this->fixture();
        foreach (['/scorer/standings', '/scorer/matches/'.$match->id.'/result'] as $url) {
            $this->withCookie('company_token', '')->get($url)->assertRedirect(route('login'));
            foreach ([User::factory()->create(['is_admin' => true]),
                User::factory()->create(['is_admin' => false, 'company_id' => Company::factory()->create()->id])] as $user) {
                $this->signedIn($user)->get($url)->assertForbidden();
            }
            $this->signedIn($scorer)->get($url)->assertOk();
            config(['tournament.enabled' => false]);
            $this->get($url)->assertNotFound();
            config(['tournament.enabled' => true]);
        }
    }

    public function test_real_component_updates_reject_revoked_scorer_on_both_new_routes(): void
    {
        [$actor, $match] = $this->fixture();
        foreach ([['/scorer/matches/'.$match->id.'/result', 'saveDraft'], ['/scorer/standings', 'openAdjustment']] as [$url, $method]) {
            DB::table('users')->where('id', $actor->id)->update(['is_scorer' => true]);
            $html = $this->signedIn($actor)->get($url)->assertOk()->getContent();
            preg_match('/wire:snapshot="([^"]+)"/', $html, $snapshots);
            $snapshot = html_entity_decode($snapshots[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            DB::table('users')->where('id', $actor->id)->update(['is_scorer' => false]);
            $this->withCredentials()->postJson('/livewire/update', ['components' => [[
                'snapshot' => $snapshot, 'updates' => [], 'calls' => [['path' => '', 'method' => $method,
                    'params' => $method === 'openAdjustment' ? [$match->group_id, $match->team1_id] : []]],
            ]]], ['X-Livewire' => 'true'])->assertForbidden();
        }
        $this->assertNull($match->fresh()->draft_result);
        $this->assertSame(0, GroupTeam::where('team_id', $match->team1_id)->firstOrFail()->points);
    }
}
