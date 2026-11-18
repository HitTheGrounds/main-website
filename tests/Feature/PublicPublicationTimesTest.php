<?php

namespace Tests\Feature;

use App\Models\PlayerMatchStat;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\Tournament\MatchResultService;
use App\Services\Tournament\PlayerPublicationService;
use App\Services\Tournament\PlayerStatsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PublicPublicationTimesTest extends TestCase
{
    use DatabaseMigrations;

    public function test_drafts_preserve_public_figures_and_clocks_until_next_published_page_load(): void
    {
        config(['tournament.enabled' => true, 'tournament.year' => 2026]);
        Route::middleware('web')->group(base_path('routes/tournament.php'));
        Route::getRoutes()->refreshNameLookups();
        $actor = User::factory()->create(['is_admin' => false, 'company_id' => null]);
        $actor->is_scorer = true;
        $actor->save();
        $teams = Team::factory()->count(2)->create(['approved' => true]);
        $group = DB::table('tournament_groups')->insertGetId(['name' => 'A', 'tournament_year' => 2026]);
        foreach ($teams as $team) {
            DB::table('group_teams')->insert(['group_id' => $group, 'team_id' => $team->id, 'tournament_year' => 2026]);
        }
        $id = DB::table('matches')->insertGetId(['tournament_year' => 2026, 'stage' => 'G', 'group_id' => $group,
            'team1_id' => $teams[0]->id, 'team2_id' => $teams[1]->id, 'balls_per_over' => 4]);
        $member = TeamMember::factory()->create(['team_id' => $teams[0]->id]);
        $this->travelTo(CarbonImmutable::parse('2026-11-16 11:00:00 UTC'));
        request()->attributes->set('user', $actor);
        $results = app(MatchResultService::class);
        $results->saveDraft(2026, $id, 1, ['result_type' => 'win', 'result_method' => 'normal',
            'winner_id' => $teams[0]->id, 'batting_first_id' => $teams[0]->id,
            'team1_runs' => 100, 'team1_wickets' => 3, 'team1_overs' => 5, 'team1_balls' => 0,
            'team2_runs' => 90, 'team2_wickets' => 7, 'team2_overs' => 3, 'team2_balls' => 0]);
        $review = $results->review(2026, $id);
        $results->publish(2026, $id, $review['version'], $review['draft_hash']);
        $this->travelTo(CarbonImmutable::parse('2026-11-16 12:00:00 UTC'));
        app(PlayerStatsService::class)->saveDraft(2026, $id, $teams[0]->id, $member->id,
            ['draft' => null, 'published' => null], ['runs_scored' => 20]);
        $players = app(PlayerPublicationService::class);
        $review = $players->review(2026, $id);
        $players->publish(2026, $id, $review['version'], $review['player_stats_revision'], $review['receipt']);
        request()->attributes->remove('user');
        // Beside Refresh, the stats view shows the newer of the result and player-stats publications.
        $oldPage = $this->get('/tournament?tab=stats')->assertOk()->assertDontSee('tc-updated')->assertDontSee('Publication details');
        $oldData = $oldPage->viewData('rankingData');
        $oldHtml = $oldPage->getContent();
        $this->assertSame(20, $oldData['entries'][0]['value']);
        foreach (['matches', 'table', 'bracket'] as $tab) {
            $this->get('/tournament?tab='.$tab)->assertOk()->assertDontSee('tc-updated')->assertSee('Refresh');
        }

        $this->travelTo(CarbonImmutable::parse('2026-11-16 13:00:00 UTC'));
        request()->attributes->set('user', $actor);
        $official = PlayerMatchStat::where('match_id', $id)->where('record_state', 'published')->firstOrFail();
        app(PlayerStatsService::class)->saveDraft(2026, $id, $teams[0]->id, $member->id,
            ['draft' => null, 'published' => ['id' => $official->id, 'version' => $official->version]], ['runs_scored' => 30]);
        request()->attributes->remove('user');
        $this->assertSame($oldData, $this->get('/tournament?tab=stats')->assertOk()->viewData('rankingData'));

        $this->travelTo(CarbonImmutable::parse('2026-11-16 14:00:00 UTC'));
        request()->attributes->set('user', $actor);
        $review = $players->review(2026, $id);
        $players->publish(2026, $id, $review['version'], $review['player_stats_revision'], $review['receipt']);
        request()->attributes->remove('user');
        $newPage = $this->get('/tournament?tab=stats')->assertOk()->assertDontSee('tc-updated')
            ->assertSee('Refresh this page for the latest published results.');
        $this->assertSame(30, $newPage->viewData('rankingData')['entries'][0]['value']);
        $this->assertSame($oldHtml, $oldPage->getContent());
        foreach (['wire:poll', 'setInterval(', 'WebSocket(', 'http-equiv="refresh"'] as $automaticRefresh) {
            $newPage->assertDontSee($automaticRefresh, false);
        }
        $this->travelTo(CarbonImmutable::parse('2026-11-16 15:00:00 UTC'));
        request()->attributes->set('user', $actor);
        $official = $official->fresh();
        app(PlayerStatsService::class)->stageWithdrawal(2026, $id, $teams[0]->id, $member->id,
            ['draft' => null, 'published' => ['id' => $official->id, 'version' => $official->version]]);
        request()->attributes->remove('user');
        $this->assertSame($newPage->viewData('rankingData'), $this->get('/tournament?tab=stats')->assertOk()->viewData('rankingData'));
        request()->attributes->set('user', $actor);
        $review = $players->review(2026, $id);
        $players->publish(2026, $id, $review['version'], $review['player_stats_revision'], $review['receipt']);
        request()->attributes->remove('user');
        $withdrawn = $this->get('/tournament?tab=stats')->assertOk()->assertSee('No published figures are available')
            ->assertDontSee('tc-updated')->assertDontSee('award/orange_cap.avif');
        $this->assertSame([], $withdrawn->viewData('rankingData')['entries']);
        $this->assertSame(0, $withdrawn->viewData('rankingData')['coverage']['players_with_published_entries']);
        $this->travelBack();
    }
}
