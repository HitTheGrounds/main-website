<?php

namespace Tests\Feature;

use App\Models\PlayerMatchStat;
use App\Models\PlayerTournamentStat;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TournamentMatch;
use App\Models\User;
use App\Services\JWTService;
use App\Services\Tournament\MatchResultService;
use App\Services\Tournament\MatchSchedulingService;
use App\Services\Tournament\PlayerEntryService;
use App\Services\Tournament\PlayerPublicationService;
use App\Services\Tournament\PlayerStatsService;
use App\Services\Tournament\PlayoffFeature;
use App\Services\Tournament\PublicTournamentService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TournamentPlayoffsTest extends TestCase
{
    use DatabaseMigrations;

    private User $admin;

    private User $scorer;

    protected function setUp(): void
    {
        parent::setUp();
        config(['tournament.enabled' => true, 'tournament.year' => 2026, 'tournament.writes_per_minute' => 1000]);
        Route::middleware('web')->group(base_path('routes/tournament.php'));
        Route::getRoutes()->refreshNameLookups();
        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->scorer = User::factory()->create(['is_admin' => false, 'is_scorer' => true, 'company_id' => null]);
        $this->actAs($this->scorer);
    }

    protected function tearDown(): void
    {
        // Migration rollback deliberately refuses to discard real Playoff history.
        // Clear only this isolated test database's source and derived fixtures first.
        if (isset($this->app) && Schema::hasTable('matches')) {
            DB::table('player_tournament_stats')->delete();
            DB::table('player_match_stats')->delete();
            DB::table('matches')->where('stage', 'PO')->delete();
        }
        parent::tearDown();
    }

    private function teams()
    {
        $group = DB::table('tournament_groups')->insertGetId(['name' => 'Playoff group', 'tournament_year' => 2026]);
        $teams = Team::factory()->count(5)->create(['approved' => true]);
        foreach ($teams as $index => $team) {
            DB::table('group_teams')->insert(['group_id' => $group, 'tournament_year' => 2026,
                'team_id' => $team->id, 'qualified' => $index < 4]);
        }

        return $teams;
    }

    private function toggle(bool $enabled): void
    {
        $this->actAs($this->admin);
        $feature = app(PlayoffFeature::class);
        $feature->save(2026, $enabled, $feature->settings(2026)['version']);
        $this->actAs($this->scorer);
    }

    private function schedule(int $a, int $b, string $stage = 'PO', ?string $revision = null): TournamentMatch
    {
        $service = app(MatchSchedulingService::class);
        $revision ??= $service->knockoutOptions(2026, $stage)['revision'];

        return $service->create(2026, 0, ['stage' => $stage, 'team1_id' => $a, 'team2_id' => $b,
            'bracket_position' => $stage === 'PO' ? null : 1, 'balls_per_over' => 4], [], $revision);
    }

    private function publish(TournamentMatch $match, bool $reverse = false): TournamentMatch
    {
        $service = app(MatchResultService::class);
        $service->saveDraft(2026, $match->id, $match->fresh()->version, [
            'result_type' => 'win', 'result_method' => 'normal', 'batting_first_id' => $match->team1_id,
            'winner_id' => $reverse ? $match->team2_id : $match->team1_id,
            'team1_runs' => $reverse ? 90 : 100, 'team1_wickets' => 3, 'team1_overs' => 5, 'team1_balls' => 0,
            'team2_runs' => $reverse ? 100 : 90, 'team2_wickets' => 3, 'team2_overs' => 5, 'team2_balls' => 0,
        ]);
        $review = $service->review(2026, $match->id);

        return $service->publish(2026, $match->id, $review['version'], $review['draft_hash']);
    }

    private function rejected(callable $action): void
    {
        try {
            $action();
            $this->fail('Expected disabled or ineligible Playoff action to fail.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }

    public function test_default_off_preserves_group_qualification_and_hides_every_playoff_control(): void
    {
        $teams = $this->teams();
        $feature = app(PlayoffFeature::class);
        $this->assertFalse($feature->enabled(2026));
        $this->assertSame(['G', 'QF', 'SF', 'F'], $feature->stages(2026));
        $service = app(MatchSchedulingService::class);
        $this->assertCount(4, $service->knockoutOptions(2026, 'QF')['candidates']);
        $this->rejected(fn () => $service->knockoutOptions(2026, 'PO'));
        $this->rejected(fn () => $this->schedule($teams[0]->id, $teams[1]->id, 'PO', str_repeat('a', 64)));
        $this->schedule($teams[0]->id, $teams[1]->id, 'QF');
        $this->get('/tournament')->assertOk()->assertDontSee('Playoffs')->assertDontSee('value="PO"', false);
        $this->actAs($this->scorer);
        Livewire::withCookie('company_token', (new JWTService)->generateToken($this->scorer));
        Volt::test('scorer.matches-manager')->assertDontSee('value="PO"', false);
        Volt::test('scorer.knockouts-manager')->assertDontSee('Playoffs');
    }

    public function test_admin_dashboard_toggle_is_persisted_year_specific_and_rejects_stale_or_scorer_writes(): void
    {
        try {
            app(PlayoffFeature::class)->save(2026, true, 0);
            $this->fail('Scorer changed an admin-only setting.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->actAs($this->admin);
        Livewire::withCookie('company_token', (new JWTService)->generateToken($this->admin));
        Volt::test('admin.playoff-settings')->assertSet('enabled', false)
            ->set('enabled', true)->call('save')->assertHasNoErrors()->assertSet('version', 1);
        $this->assertTrue(app(PlayoffFeature::class)->enabled(2026));
        $this->assertFalse(app(PlayoffFeature::class)->enabled(2027));
        $this->rejected(fn () => app(PlayoffFeature::class)->save(2026, false, 0));
        $this->signedIn($this->admin)->get(route('admin.dashboard'))->assertOk()->assertSee('Enable Playoffs');
    }

    public function test_playoffs_allow_more_than_four_matches_repeated_teams_and_only_saved_qualifiers(): void
    {
        $teams = $this->teams();
        $this->toggle(true);
        for ($i = 0; $i < 6; $i++) {
            $match = $this->schedule($teams[$i % 2]->id, $teams[1 - $i % 2]->id);
            $this->assertNull($match->group_id);
            $this->assertNull($match->bracket_position);
        }
        $this->assertSame(6, TournamentMatch::where('stage', 'PO')->count());
        $this->assertCount(4, app(MatchSchedulingService::class)->knockoutOptions(2026, 'PO')['candidates']);
        $this->rejected(fn () => $this->schedule($teams[0]->id, $teams[4]->id));
        $this->rejected(fn () => $this->schedule($teams[0]->id, $teams[0]->id));
        Livewire::withCookie('company_token', (new JWTService)->generateToken($this->scorer));
        Volt::test('scorer.knockouts-manager')->call('chooseRound', 'PO')->assertSee('Playoffs')
            ->set('fixture.team1_id', (string) $teams[0]->id)->set('fixture.team2_id', (string) $teams[1]->id)
            ->call('createMatch')->assertHasNoErrors();
        $this->assertSame(7, TournamentMatch::where('stage', 'PO')->count());
    }

    public function test_enabled_quarters_accept_only_published_playoff_winners_including_repeat_winners(): void
    {
        $teams = $this->teams();
        $this->toggle(true);
        $service = app(MatchSchedulingService::class);
        $this->assertSame([], $service->knockoutOptions(2026, 'QF')['candidates']);
        $first = $this->schedule($teams[0]->id, $teams[1]->id);
        $this->assertSame([], $service->knockoutOptions(2026, 'QF')['candidates']);
        $this->publish($first);
        $this->publish($this->schedule($teams[0]->id, $teams[1]->id));
        $this->publish($this->schedule($teams[2]->id, $teams[3]->id));
        $this->assertSame([$teams[0]->id, $teams[2]->id], array_column($service->knockoutOptions(2026, 'QF')['candidates'], 'id'));
        $this->rejected(fn () => $this->schedule($teams[1]->id, $teams[2]->id, 'QF'));
        $quarter = $this->schedule($teams[0]->id, $teams[2]->id, 'QF');
        $this->publish($quarter);
        $this->assertSame([$teams[0]->id], array_column($service->knockoutOptions(2026, 'SF')['candidates'], 'id'));
    }

    public function test_public_matches_filters_and_stats_include_playoffs_but_tables_and_bracket_do_not(): void
    {
        $teams = $this->teams();
        $this->toggle(true);
        $public = app(PublicTournamentService::class);
        $table = $public->standings(2026);
        $bracket = $public->bracket(2026);
        $match = $this->publish($this->schedule($teams[0]->id, $teams[1]->id));
        $member = TeamMember::factory()->create(['team_id' => $teams[0]->id]);
        app(PlayerStatsService::class)->saveDraft(2026, $match->id, $member->team_id, $member->id,
            ['draft' => null, 'published' => null], ['runs_scored' => 30, 'dot_balls' => 4]);
        $players = app(PlayerPublicationService::class);
        $review = $players->review(2026, $match->id);
        $players->publish(2026, $match->id, $review['version'], $review['player_stats_revision'], $review['receipt']);
        $this->assertSame(30, PlayerTournamentStat::where('team_member_id', $member->id)->value('runs_scored'));
        $this->assertSame($table, $public->standings(2026));
        $this->assertSame($bracket, $public->bracket(2026));
        $this->assertSame([$match->id], array_column($public->matches(2026, ['stage' => 'PO'])['matches'], 'id'));
        $this->assertSame(30, $public->rankings(2026)['entries'][0]['value']);
        $this->get('/tournament?stage=PO')->assertOk()->assertSee('Playoffs')->assertSee($teams[0]->team_name);
        $this->get('/tournament?tab=table')->assertOk()->assertDontSee('Playoffs');
        $this->get('/tournament?tab=bracket')->assertOk()->assertDontSee('Playoffs');
        $official = $match->fresh()->getAttributes();
        $performances = PlayerMatchStat::orderBy('id')->get()->toJson();
        $this->toggle(false);
        $this->assertSame([], $public->matches(2026)['matches']);
        $this->assertSame([], $public->rankings(2026)['entries']);
        $this->assertFalse(PlayerTournamentStat::where('team_member_id', $member->id)->exists());
        $this->get('/tournament')->assertOk()->assertDontSee('Playoffs');
        $this->get('/tournament?stage=PO')->assertStatus(422);
        $this->assertSame($official, $match->fresh()->getAttributes());
        $this->assertSame($performances, PlayerMatchStat::orderBy('id')->get()->toJson());
        $this->toggle(true);
        $this->assertSame(30, $public->rankings(2026)['entries'][0]['value']);
    }

    public function test_switching_off_blocks_old_scorer_reads_mutations_and_pending_quarter_selections(): void
    {
        $teams = $this->teams();
        $this->toggle(true);
        $match = $this->schedule($teams[0]->id, $teams[1]->id);
        $service = app(MatchSchedulingService::class);
        $this->publish($this->schedule($teams[0]->id, $teams[1]->id));
        $this->publish($this->schedule($teams[2]->id, $teams[3]->id));
        $revision = $service->knockoutOptions(2026, 'QF')['revision'];
        Livewire::withCookie('company_token', (new JWTService)->generateToken($this->scorer));
        $screen = Volt::test('scorer.knockouts-manager')->call('chooseRound', 'PO');
        $this->toggle(false);
        $screen->call('reloadSelection')->assertSet('stage', 'QF')->assertDontSee('Playoffs');
        $this->assertSame(0, $service->listing(2026)->total());
        $this->rejected(fn () => $service->findForScheduling(2026, $match->id));
        $this->rejected(fn () => $service->updateSchedule(2026, $match->id, $match->version, ['venue' => 'Hidden edit']));
        $this->rejected(fn () => $service->deleteFixture(2026, $match->id, $match->version));
        $this->rejected(fn () => app(MatchResultService::class)->review(2026, $match->id));
        $this->rejected(fn () => app(PlayerEntryService::class)->read(2026, $match->id));
        $this->rejected(fn () => $this->schedule($teams[0]->id, $teams[2]->id, 'QF', $revision));
        $this->assertCount(4, $service->knockoutOptions(2026, 'QF')['candidates']);
    }

    public function test_last_playoff_win_cannot_be_reversed_after_quarter_scheduling_but_another_win_preserves_eligibility(): void
    {
        $teams = $this->teams();
        $this->toggle(true);
        $first = $this->publish($this->schedule($teams[0]->id, $teams[1]->id));
        $second = $this->publish($this->schedule($teams[0]->id, $teams[1]->id));
        $this->publish($this->schedule($teams[2]->id, $teams[3]->id));
        $this->schedule($teams[0]->id, $teams[2]->id, 'QF');
        $this->publish($first, true);
        $this->rejected(fn () => $this->publish($second, true));
        $this->assertSame($teams[0]->id, $second->fresh()->winner_id);
    }

    public function test_disabling_removes_only_playoff_figures_from_shared_player_totals(): void
    {
        $teams = $this->teams();
        $group = DB::table('group_teams')->where('team_id', $teams[0]->id)->value('group_id');
        $service = app(MatchSchedulingService::class);
        $groupMatch = $service->create(2026, 1, ['stage' => 'G', 'group_id' => $group,
            'team1_id' => $teams[0]->id, 'team2_id' => $teams[1]->id, 'balls_per_over' => 4]);
        $this->publish($groupMatch);
        $member = TeamMember::factory()->create(['team_id' => $teams[0]->id]);
        $this->toggle(true);
        $playoff = $this->publish($this->schedule($teams[0]->id, $teams[1]->id));
        foreach ([[$groupMatch, 10], [$playoff, 30]] as [$match, $runs]) {
            app(PlayerStatsService::class)->saveDraft(2026, $match->id, $member->team_id, $member->id,
                ['draft' => null, 'published' => null], ['runs_scored' => $runs]);
            $publisher = app(PlayerPublicationService::class);
            $review = $publisher->review(2026, $match->id);
            $publisher->publish(2026, $match->id, $review['version'], $review['player_stats_revision'], $review['receipt']);
        }
        $this->assertSame(40, PlayerTournamentStat::where('team_member_id', $member->id)->value('runs_scored'));
        $this->toggle(false);
        $summary = PlayerTournamentStat::where('team_member_id', $member->id)->firstOrFail();
        $this->assertSame(10, $summary->runs_scored);
        $this->assertSame(10, $summary->highest_score);
        $this->assertSame($groupMatch->id, $summary->highest_score_match_id);
        $this->assertSame([$groupMatch->id], array_column(app(PublicTournamentService::class)->matches(2026)['matches'], 'id'));
        $this->toggle(true);
        $this->assertSame(40, $summary->fresh()->runs_scored);
        $this->assertSame(30, $summary->fresh()->highest_score);
    }

    public function test_playoff_fixture_can_be_scheduled_edited_and_deleted_without_a_bracket_position(): void
    {
        $teams = $this->teams();
        $this->toggle(true);
        $match = $this->schedule($teams[0]->id, $teams[1]->id);
        $service = app(MatchSchedulingService::class);
        $updated = $service->updateFixture(2026, $match->id, $match->version, 0,
            ['stage' => 'PO', 'group_id' => null, 'bracket_position' => null, 'team1_id' => $teams[0]->id,
                'team2_id' => $teams[2]->id, 'balls_per_over' => 6],
            ['scheduled_at' => '2026-11-20T10:00', 'venue' => 'Playoff ground'], $service->knockoutOptions(2026, 'PO')['revision']);
        $this->assertSame($teams[2]->id, $updated->team2_id);
        $this->assertSame(6, $updated->balls_per_over);
        $this->assertNull($updated->bracket_position);
        $this->assertSame('2026-11-20 04:30:00', $updated->scheduled_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame(1, $service->listing(2026, ['stage' => 'PO'])->total());
        $service->deleteFixture(2026, $updated->id, $updated->version);
        $this->assertDatabaseMissing('matches', ['id' => $updated->id]);
    }

    public function test_upgrade_preserves_existing_fixtures_and_rollback_protects_playoff_history(): void
    {
        $teams = $this->teams();
        $match = $this->schedule($teams[0]->id, $teams[1]->id, 'QF');
        $before = $match->getAttributes();
        $migration = require database_path('migrations/2026_11_20_000001_add_optional_tournament_playoffs.php');
        $migration->down();
        $migration->up();
        $this->assertSame($before, $match->fresh()->getAttributes());
        $this->assertFalse(app(PlayoffFeature::class)->enabled(2026));
        $group = DB::table('group_teams')->where('team_id', $teams[0]->id)->value('group_id');
        $service = app(MatchSchedulingService::class);
        foreach ([1, 2] as $opponent) {
            $service->create(2026, 1, ['stage' => 'G', 'group_id' => $group,
                'team1_id' => $teams[0]->id, 'team2_id' => $teams[$opponent]->id, 'balls_per_over' => 4]);
        }
        $this->assertSame(2, TournamentMatch::where('stage', 'G')->count());
        $this->toggle(true);
        $playoff = $this->schedule($teams[0]->id, $teams[1]->id);
        try {
            $migration->down();
            $this->fail('Rollback discarded Playoff history.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Playoff match history', $exception->getMessage());
        }
        $this->assertDatabaseHas('matches', ['id' => $playoff->id, 'stage' => 'PO']);
        $this->assertTrue(app(PlayoffFeature::class)->enabled(2026));
    }
}
