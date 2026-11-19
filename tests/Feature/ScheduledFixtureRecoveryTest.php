<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TournamentGroup;
use App\Models\TournamentMatch;
use App\Models\User;
use App\Services\JWTService;
use App\Services\Tournament\MatchResultService;
use App\Services\Tournament\MatchSchedulingService;
use App\Services\Tournament\PlayerStatsService;
use App\Services\Tournament\TournamentGroupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ScheduledFixtureRecoveryTest extends TestCase
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
        $teams = Team::factory()->count(5)->create(['approved' => true]);
        $groups = [];
        foreach (['A', 'B'] as $name) {
            $groups[] = DB::table('tournament_groups')->insertGetId(['name' => $name, 'tournament_year' => 2026]);
        }
        foreach ($teams as $i => $team) {
            DB::table('group_teams')->insert(['group_id' => $groups[$i < 3 ? 0 : 1], 'tournament_year' => 2026, 'team_id' => $team->id]);
        }
        $match = app(MatchSchedulingService::class)->create(2026, 1, ['stage' => 'G', 'group_id' => $groups[0],
            'team1_id' => $teams[0]->id, 'team2_id' => $teams[1]->id, 'balls_per_over' => 4]);

        return [$match, $teams, $groups];
    }

    private function structure(TournamentMatch $match, array $changes = []): array
    {
        return array_replace($match->only(['stage', 'group_id', 'bracket_position', 'team1_id', 'team2_id', 'balls_per_over', 'overs_per_innings', 'max_wickets']), $changes);
    }

    private function rejected(callable $action): void
    {
        try {
            $action();
            $this->fail('Expected fixture protection.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }

    public function test_dashboard_edits_participants_format_and_all_schedule_details(): void
    {
        [$match, $teams] = $this->fixture();
        Volt::test('scorer.matches-manager')->call('openMatch', $match->id)->assertSet('fixtureEditable', true)
            ->set('fixtureStructure.team2_id', (string) $teams[2]->id)->set('fixtureStructure.balls_per_over', '6')
            ->set('schedule.batting_first_id', (string) $teams[2]->id)->set('schedule.venue', 'Ground Two')
            ->set('schedule.scheduled_at', '2026-11-20T09:30')->set('schedule.display_number', '42')
            ->call('saveSchedule')->assertHasNoErrors()->assertSet('panel', '');
        $changed = $match->fresh();
        $this->assertSame($teams[2]->id, $changed->team2_id);
        $this->assertSame(6, $changed->balls_per_over);
        $this->assertSame($teams[2]->id, $changed->batting_first_id);
        $this->assertSame('Ground Two', $changed->venue);
        $this->assertSame(42, $changed->display_number);
        $this->assertSame('2026-11-20 04:00:00', $changed->scheduled_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame($match->created_by, $changed->created_by);
        $this->assertSame(2, $changed->version);
    }

    public function test_group_change_loads_new_teams_and_updates_both_group_revisions(): void
    {
        [$match, $teams, $groups] = $this->fixture();
        Volt::test('scorer.matches-manager')->call('openMatch', $match->id)
            ->set('fixtureStructure.group_id', (string) $groups[1])
            ->set('fixtureStructure.team1_id', (string) $teams[3]->id)->set('fixtureStructure.team2_id', (string) $teams[4]->id)
            ->call('saveSchedule')->assertHasNoErrors();
        $this->assertSame($groups[1], $match->fresh()->group_id);
        $this->assertSame([2, 2], TournamentGroup::orderBy('id')->pluck('version')->all());
    }

    public function test_knockout_edit_link_opens_the_correct_dashboard_editor(): void
    {
        [$match] = $this->fixture();
        $actor = User::where('is_scorer', true)->firstOrFail();
        $this->signedIn($actor)->get('/scorer/dashboard?edit='.$match->id)->assertOk()
            ->assertSee('Edit scheduled fixture')->assertSee('Delete unused fixture');
    }

    public function test_browser_cannot_unlock_a_protected_fixture_editor(): void
    {
        [$match] = $this->fixture();
        app(MatchSchedulingService::class)->updateSchedule(2026, $match->id, 1, ['status' => 'live']);
        $this->expectException(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);
        Volt::test('scorer.matches-manager')->call('openMatch', $match->id)
            ->assertSet('fixtureEditable', false)->set('fixtureEditable', true);
    }

    public function test_editor_recovers_when_another_scorer_deletes_the_open_fixture(): void
    {
        [$match] = $this->fixture();
        $component = Volt::test('scorer.matches-manager')->call('openMatch', $match->id);
        app(MatchSchedulingService::class)->deleteFixture(2026, $match->id, $match->version);
        $component->call('reloadMatch')->assertHasErrors('tournament')->assertSet('editingId', null)
            ->assertSet('panel', '')->assertSee('removed by another update');
        $this->assertDatabaseMissing('matches', ['id' => $match->id]);
    }

    public function test_deleting_unused_fixture_allows_group_membership_recovery_and_recreation(): void
    {
        [$match, $teams, $groups] = $this->fixture();
        Volt::test('scorer.matches-manager')->call('openMatch', $match->id)->call('deleteFixture')
            ->assertHasNoErrors()->assertSet('editingId', null)->assertSet('panel', '');
        $this->assertDatabaseMissing('matches', ['id' => $match->id]);
        app(TournamentGroupService::class)->remove(2026, $groups[0], 2, $teams[2]->id);
        $replacement = app(MatchSchedulingService::class)->create(2026, 3, $this->structure($match, ['balls_per_over' => 6]));
        $this->assertSame(6, $replacement->balls_per_over);
    }

    public function test_stale_versions_duplicate_pairs_and_stale_group_setup_do_not_mutate_fixture(): void
    {
        [$match, $teams, $groups] = $this->fixture();
        $service = app(MatchSchedulingService::class);
        $other = $service->create(2026, 1, $this->structure($match, ['team2_id' => $teams[2]->id]));
        $before = $match->getAttributes();
        $this->rejected(fn () => $service->updateFixture(2026, $match->id, 1, 1, $this->structure($other), []));
        $this->rejected(fn () => $service->updateFixture(2026, $match->id, 1, 2, $this->structure($match), []));
        $service->updateSchedule(2026, $match->id, 1, ['venue' => 'New venue']);
        $this->rejected(fn () => $service->deleteFixture(2026, $match->id, 1));
        $this->rejected(fn () => $service->updateFixture(2026, $match->id, 1, 1, $this->structure($match), []));
        $this->assertSame($before['team2_id'], $match->fresh()->team2_id);
        $this->assertSame('New venue', $match->fresh()->venue);
    }

    public function test_saved_player_or_result_drafts_and_live_or_published_history_are_protected(): void
    {
        [$match, $teams] = $this->fixture();
        $service = app(MatchSchedulingService::class);
        $results = app(MatchResultService::class);
        $draft = $results->saveDraft(2026, $match->id, 1, ['team1_runs' => 0]);
        $this->rejected(fn () => $service->deleteFixture(2026, $match->id, $draft->version));
        $this->rejected(fn () => $service->updateFixture(2026, $match->id, $draft->version, 1, $this->structure($match), []));
        $results->discardDraft(2026, $match->id, $draft->version);
        $member = TeamMember::factory()->create(['team_id' => $teams[0]->id]);
        app(PlayerStatsService::class)->saveDraft(2026, $match->id, $member->team_id, $member->id,
            ['draft' => null, 'published' => null], ['runs_scored' => 0]);
        $this->rejected(fn () => $service->deleteFixture(2026, $match->id, $match->fresh()->version));
        DB::table('player_match_stats')->where('match_id', $match->id)->delete();
        $live = $service->updateSchedule(2026, $match->id, $match->fresh()->version, ['status' => 'live']);
        $this->rejected(fn () => $service->deleteFixture(2026, $match->id, $live->version));
        DB::table('matches')->where('id', $match->id)->update(['status' => 'upcoming', 'result_published_at' => now()]);
        $this->rejected(fn () => $service->updateFixture(2026, $match->id, $live->version, 1, $this->structure($match), []));
    }

    private function publishWinner(TournamentMatch $match, bool $second = false): TournamentMatch
    {
        $service = app(MatchResultService::class);
        $service->saveDraft(2026, $match->id, $match->fresh()->version, ['result_type' => 'win', 'result_method' => 'normal',
            'winner_id' => $second ? $match->team2_id : $match->team1_id, 'batting_first_id' => $match->team1_id,
            'team1_runs' => $second ? 90 : 100, 'team2_runs' => $second ? 100 : 90,
            'team1_wickets' => $second ? 7 : 2, 'team2_wickets' => $second ? 2 : 7,
            'team1_overs' => 5, 'team1_balls' => 0, 'team2_overs' => 5, 'team2_balls' => 0]);
        $review = $service->review(2026, $match->id);

        return $service->publish(2026, $match->id, $review['version'], $review['draft_hash']);
    }

    private function knockout(string $stage, int $slot, int $a, int $b): TournamentMatch
    {
        $service = app(MatchSchedulingService::class);
        $round = $service->knockoutOptions(2026, $stage);

        return $service->create(2026, 0, ['stage' => $stage, 'bracket_position' => $slot, 'team1_id' => $a, 'team2_id' => $b, 'balls_per_over' => 4], [], $round['revision']);
    }

    public function test_knockout_edit_checks_eligibility_positions_and_recovers_wrong_upstream_winner(): void
    {
        [, $teams] = $this->fixture();
        DB::table('group_teams')->update(['qualified' => true]);
        $first = $this->knockout('QF', 1, $teams[0]->id, $teams[1]->id);
        $second = $this->knockout('QF', 2, $teams[2]->id, $teams[3]->id);
        $service = app(MatchSchedulingService::class);
        $round = $service->knockoutOptions(2026, 'QF');
        $first = $service->updateFixture(2026, $first->id, 1, 0, $this->structure($first, ['balls_per_over' => 6, 'bracket_position' => 3]), [], $round['revision']);
        $this->assertSame(3, $first->bracket_position);
        $this->rejected(fn () => $service->updateFixture(2026, $first->id, $first->version, 0,
            $this->structure($first, ['team1_id' => $teams[2]->id]), [], $service->knockoutOptions(2026, 'QF')['revision']));
        $first = $this->publishWinner($first);
        $second = $this->publishWinner($second);
        $semi = $this->knockout('SF', 1, $teams[0]->id, $teams[2]->id);
        $this->rejected(fn () => $this->publishWinner($first, true));
        $service->deleteFixture(2026, $semi->id, $semi->version);
        $corrected = $this->publishWinner($first, true);
        $replacement = $this->knockout('SF', 1, $teams[1]->id, $teams[2]->id);
        $this->assertSame($teams[1]->id, $corrected->winner_id);
        $this->assertSame($teams[1]->id, $replacement->team1_id);
        $this->assertDatabaseHas('matches', ['id' => $second->id, 'winner_id' => $teams[2]->id]);
    }
}
