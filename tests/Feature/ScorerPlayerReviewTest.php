<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\PlayerMatchStat;
use App\Models\PlayerTournamentStat;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TournamentMatch;
use App\Models\User;
use App\Services\JWTService;
use App\Services\Tournament\MatchResultService;
use App\Services\Tournament\PlayerStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ScorerPlayerReviewTest extends TestCase
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
        $groupId = DB::table('tournament_groups')->insertGetId(['name' => 'Group '.(DB::table('tournament_groups')->count() + 1), 'tournament_year' => 2026]);
        $teams = Team::factory()->count(2)->create(['approved' => true]);
        foreach ($teams as $team) {
            DB::table('group_teams')->insert(['group_id' => $groupId, 'tournament_year' => 2026, 'team_id' => $team->id]);
        }
        $id = DB::table('matches')->insertGetId(['stage' => 'G', 'group_id' => $groupId, 'tournament_year' => 2026,
            'team1_id' => $teams[0]->id, 'team2_id' => $teams[1]->id, 'balls_per_over' => 4]);

        return [$actor, TournamentMatch::findOrFail($id)];
    }

    private function ready(): array
    {
        [$actor, $match] = $this->fixture();
        $results = app(MatchResultService::class);
        $results->saveDraft(2026, $match->id, 1, ['result_type' => 'win', 'result_method' => 'normal', 'winner_id' => $match->team1_id,
            'batting_first_id' => $match->team1_id, 'team1_runs' => 100, 'team1_wickets' => 3, 'team1_overs' => 5,
            'team1_balls' => 0, 'team2_runs' => 90, 'team2_wickets' => 7, 'team2_overs' => 3, 'team2_balls' => 0]);
        $review = $results->review(2026, $match->id);
        $results->publish(2026, $match->id, $review['version'], $review['draft_hash']);
        $one = TeamMember::factory()->create(['team_id' => $match->team1_id, 'name' => 'First batter']);
        $two = TeamMember::factory()->create(['team_id' => $match->team2_id, 'name' => 'Second bowler']);

        return [$match->fresh(), $one, $two];
    }

    private function draft(TournamentMatch $match, TeamMember $member, array $figures): void
    {
        $rows = PlayerMatchStat::where('match_id', $match->id)->where('team_member_id', $member->id)->get();
        $expected = [];
        foreach (['draft', 'published'] as $state) {
            $row = $rows->firstWhere('record_state', $state);
            $expected[$state] = $row ? ['id' => $row->id, 'version' => $row->version] : null;
        }
        app(PlayerStatsService::class)->saveDraft(2026, $match->id, $member->team_id, $member->id, $expected, $figures);
    }

    public function test_both_team_review_shows_exact_partial_values_and_publishes_once(): void
    {
        [$match, $one, $two] = $this->ready();
        $this->draft($match, $one, ['runs_scored' => 0]);
        $this->draft($match, $two, ['wickets_taken' => 2]);
        $component = Volt::test('scorer.player-editor', ['matchId' => $match->id])
            ->call('reviewPlayerUpdates')->assertHasNoErrors()->assertSee('Review player updates')
            ->assertSee('First batter')->assertSee('Second bowler')->assertSee('Incomplete figures')
            ->assertSee('Not recorded')->assertSee('No entry')
            ->assertSet('review.changes.0.draft.runs_scored', 0)
            ->assertSet('review.changes.0.draft.balls_faced', null)
            ->assertSet('review.changes.1.draft.wickets_taken', 2);
        $this->assertDatabaseCount('player_tournament_stats', 0);
        $component->call('publishPlayerUpdates')->assertHasNoErrors()->assertSet('review', null)
            ->assertSee('Player updates published')->assertSee('load or refresh')->assertSee('No pending player updates');
        $this->assertSame(2, PlayerMatchStat::where('record_state', 'published')->count());
        $this->assertSame(0, PlayerTournamentStat::where('team_member_id', $one->id)->firstOrFail()->runs_scored);
        $this->assertSame(2, PlayerTournamentStat::where('team_member_id', $two->id)->firstOrFail()->wickets_taken);
        $component->call('publishPlayerUpdates')->assertHasErrors('player');
        $this->assertDatabaseCount('player_tournament_stats', 2);
    }

    public function test_review_displays_correction_and_withdrawal_before_applying_them_together(): void
    {
        [$match, $one, $two] = $this->ready();
        $this->draft($match, $one, ['runs_scored' => 30]);
        $this->draft($match, $two, ['runs_scored' => 10]);
        $component = Volt::test('scorer.player-editor', ['matchId' => $match->id])
            ->call('reviewPlayerUpdates')->call('publishPlayerUpdates')->assertHasNoErrors();
        $component->call('selectPlayer', $one->id)->set('form.runs_scored', 20)->call('savePlayer')
            ->call('selectTeam', $two->team_id)->call('selectPlayer', $two->id)->call('stageWithdrawal')
            ->call('returnToList')->call('reviewPlayerUpdates')->assertHasNoErrors()
            ->assertSee('Correct performance')->assertSee('Withdraw official performance')->assertSee('Removed')
            ->assertSet('review.changes.0.published.runs_scored', 30)
            ->assertSet('review.changes.0.draft.runs_scored', 20);
        $this->assertSame(40, (int) PlayerTournamentStat::sum('runs_scored'));
        $component->call('publishPlayerUpdates')->assertHasNoErrors()->assertSet('teamId', $two->team_id);
        $this->assertSame(20, (int) PlayerTournamentStat::sum('runs_scored'));
        $this->assertDatabaseCount('player_match_stats', 1);
        $this->assertDatabaseHas('team_members', ['id' => $two->id]);
    }

    public function test_stale_review_rejects_added_draft_then_requires_explicit_review_again(): void
    {
        [$match, $one, $two] = $this->ready();
        $this->draft($match, $one, ['runs_scored' => 10]);
        $component = Volt::test('scorer.player-editor', ['matchId' => $match->id])->call('reviewPlayerUpdates');
        $this->draft($match, $two, ['runs_scored' => 20]);
        $component->call('publishPlayerUpdates')->assertHasErrors('player')->assertSet('review', null);
        $this->assertDatabaseCount('player_tournament_stats', 0);
        $this->assertSame(2, PlayerMatchStat::where('record_state', 'draft')->count());
        $component->call('reviewPlayerUpdates')->assertHasNoErrors()->call('publishPlayerUpdates')->assertHasNoErrors();
        $this->assertSame(30, (int) PlayerTournamentStat::sum('runs_scored'));
    }

    public function test_ineligible_match_and_unsaved_editor_cannot_be_published(): void
    {
        [, $match] = $this->fixture();
        $one = TeamMember::factory()->create(['team_id' => $match->team1_id]);
        $this->draft($match, $one, ['runs_scored' => 5]);
        Volt::test('scorer.player-editor', ['matchId' => $match->id])
            ->call('publishPlayerUpdates')->assertHasErrors('player')
            ->call('reviewPlayerUpdates')->assertHasErrors('player')->assertSet('review', null);
        $this->assertDatabaseCount('player_tournament_stats', 0);
        [$eligible, $member] = $this->ready();
        Volt::test('scorer.player-editor', ['matchId' => $eligible->id])->call('selectPlayer', $member->id)
            ->set('form.runs_scored', 9)->call('reviewPlayerUpdates')->assertHasErrors('player')
            ->assertSet('form.runs_scored', 9)->assertSet('memberId', $member->id)->assertSet('review', null);
        $this->assertSame(0, PlayerMatchStat::where('match_id', $eligible->id)->count());
    }

    public function test_publication_failure_shows_recovery_message_and_keeps_saved_drafts(): void
    {
        [$match, $one] = $this->ready();
        $this->draft($match, $one, ['runs_scored' => 10]);
        $component = Volt::test('scorer.player-editor', ['matchId' => $match->id])->call('reviewPlayerUpdates');
        $event = 'eloquent.created: '.PlayerTournamentStat::class;
        Event::listen($event, fn () => throw new RuntimeException('Synthetic review failure'));
        try {
            $component->call('publishPlayerUpdates')->assertHasErrors('player')->assertSet('review', null)
                ->assertSee('Publication failed. Your saved drafts remain.');
        } finally {
            Event::forget($event);
        }
        $this->assertDatabaseCount('player_tournament_stats', 0);
        $this->assertSame(1, PlayerMatchStat::where('record_state', 'draft')->count());
        $component->call('reviewPlayerUpdates')->call('publishPlayerUpdates')->assertHasNoErrors();
        $this->assertSame(10, PlayerTournamentStat::firstOrFail()->runs_scored);
    }

    public function test_full_entry_journey_preserves_official_values_until_reviewed_correction(): void
    {
        [$match, $one, $two] = $this->ready();
        $component = Volt::test('scorer.player-editor', ['matchId' => $match->id])
            ->call('selectPlayer', $one->id)->set('form.runs_scored', 35)->call('savePlayer')
            ->call('selectTeam', $two->team_id)->call('selectPlayer', $two->id)->set('form.dot_balls', 0)->call('savePlayer')
            ->call('reviewPlayerUpdates')->assertHasNoErrors()->call('cancelReview')->assertSet('review', null)
            ->call('reviewPlayerUpdates')->call('publishPlayerUpdates')->assertHasNoErrors();
        $component->call('selectTeam', $one->team_id)->call('selectPlayer', $one->id)
            ->set('form.runs_scored', 30)->call('savePlayer')->assertHasNoErrors();
        $this->assertSame(35, PlayerTournamentStat::where('team_member_id', $one->id)->firstOrFail()->runs_scored);
        $this->assertSame(35, PlayerMatchStat::where('team_member_id', $one->id)->where('record_state', 'published')->firstOrFail()->runs_scored);
        $component->call('reviewPlayerUpdates')->call('publishPlayerUpdates')->assertHasNoErrors();
        $this->assertSame(30, PlayerTournamentStat::where('team_member_id', $one->id)->firstOrFail()->highest_score);
        $this->assertNull(PlayerTournamentStat::where('team_member_id', $two->id)->firstOrFail()->runs_scored);
        $this->assertSame(0, PlayerTournamentStat::where('team_member_id', $two->id)->firstOrFail()->dot_balls);
    }

    public static function changedReviewedEntries(): array
    {
        return [['edit'], ['discard'], ['recreate']];
    }

    #[DataProvider('changedReviewedEntries')]
    public function test_changed_or_recreated_draft_invalidates_ui_review(string $change): void
    {
        [$match, $one] = $this->ready();
        $this->draft($match, $one, ['runs_scored' => 10]);
        $component = Volt::test('scorer.player-editor', ['matchId' => $match->id])->call('reviewPlayerUpdates');
        $draft = PlayerMatchStat::where('match_id', $match->id)->firstOrFail();
        if ($change === 'edit') {
            $this->draft($match, $one, ['runs_scored' => 12]);
        } else {
            app(PlayerStatsService::class)->discardDraft(2026, $match->id, $one->team_id, $one->id,
                ['draft' => ['id' => $draft->id, 'version' => $draft->version], 'published' => null]);
            if ($change === 'recreate') {
                $this->draft($match, $one, ['runs_scored' => 10]);
                $this->assertNotSame($draft->id, PlayerMatchStat::where('match_id', $match->id)->firstOrFail()->id);
            }
        }
        $component->call('publishPlayerUpdates')->assertHasErrors('player')->assertSet('review', null);
        $this->assertDatabaseCount('player_tournament_stats', 0);
        $this->assertSame(0, PlayerMatchStat::where('record_state', 'published')->count());
    }

    public static function ineligiblePublicationStates(): array
    {
        return [[['status' => 'live']], [['result_published_at' => null]],
            [['result_type' => 'no_result', 'winner_id' => null, 'result_method' => null]],
            [['result_type' => 'abandoned', 'winner_id' => null, 'result_method' => null]]];
    }

    #[DataProvider('ineligiblePublicationStates')]
    public function test_changed_result_eligibility_rejects_open_review(array $changes): void
    {
        [$match, $one] = $this->ready();
        $this->draft($match, $one, ['runs_scored' => 10]);
        $component = Volt::test('scorer.player-editor', ['matchId' => $match->id])->call('reviewPlayerUpdates');
        DB::table('matches')->where('id', $match->id)->update($changes);
        $component->call('publishPlayerUpdates')->assertHasErrors('player')->assertSet('review', null);
        $this->assertSame(1, PlayerMatchStat::where('record_state', 'draft')->count());
        $this->assertDatabaseCount('player_tournament_stats', 0);
    }

    public static function lockedEditorProperties(): array
    {
        return [['matchId'], ['teamId'], ['memberId'], ['expected.draft.id'], ['official.runs_scored'], ['pendingAction'], ['review.receipt']];
    }

    #[DataProvider('lockedEditorProperties')]
    public function test_browser_cannot_replace_trusted_editor_or_review_state(string $property): void
    {
        [$match, $one] = $this->ready();
        $this->draft($match, $one, ['runs_scored' => 10]);
        $component = Volt::test('scorer.player-editor', ['matchId' => $match->id]);
        if ($property === 'review.receipt') {
            $component->call('reviewPlayerUpdates');
        } else {
            $component->call('selectPlayer', $one->id);
        }
        $this->expectException(CannotUpdateLockedPropertyException::class);
        $component->set($property, $property === 'pendingAction' ? 'withdraw' : 999999);
    }

    public function test_player_route_preserves_role_and_feature_boundaries(): void
    {
        [$scorer, $match] = $this->fixture();
        $url = '/scorer/matches/'.$match->id.'/players';
        $this->withCookie('company_token', '')->get($url)->assertRedirect(route('login'));
        foreach ([User::factory()->create(['is_admin' => true]),
            User::factory()->create(['is_admin' => false, 'company_id' => Company::factory()->create()->id]),
            User::factory()->create(['is_admin' => false, 'company_id' => null])] as $user) {
            $this->signedIn($user)->get($url)->assertForbidden();
        }
        $this->signedIn($scorer)->get($url)->assertOk();
        config(['tournament.enabled' => false]);
        $this->get($url)->assertNotFound();
    }

    public static function unauthorizedUpdates(): array
    {
        return [['revoked'], ['disabled'], ['representative'], ['admin']];
    }

    #[DataProvider('unauthorizedUpdates')]
    public function test_real_signed_component_actions_reject_changed_access(string $change): void
    {
        [$actor, $match] = $this->fixture();
        $html = $this->signedIn($actor)->get('/scorer/matches/'.$match->id.'/players')->assertOk()->getContent();
        preg_match('/wire:snapshot="([^"]+)"/', $html, $snapshots);
        $snapshot = html_entity_decode($snapshots[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($change === 'disabled') {
            config(['tournament.enabled' => false]);
        } elseif ($change === 'revoked') {
            DB::table('users')->where('id', $actor->id)->update(['is_scorer' => false]);
        } else {
            $other = User::factory()->create(['is_admin' => $change === 'admin',
                'company_id' => $change === 'representative' ? Company::factory()->create()->id : null]);
            $this->signedIn($other);
        }
        foreach (['selectTeam', 'selectPlayer', 'savePlayer', 'discardDraft', 'stageWithdrawal', 'reviewPlayerUpdates', 'publishPlayerUpdates'] as $method) {
            $response = $this->withCredentials()->postJson('/livewire/update', ['components' => [[
                'snapshot' => $snapshot, 'updates' => [], 'calls' => [['path' => '', 'method' => $method,
                    'params' => in_array($method, ['selectTeam', 'selectPlayer'], true) ? [$match->team1_id] : []]],
            ]]], ['X-Livewire' => 'true']);
            $change === 'disabled' ? $response->assertNotFound() : $response->assertForbidden();
        }
        $this->assertDatabaseCount('player_match_stats', 0);
        $this->assertDatabaseCount('player_tournament_stats', 0);
    }

    public function test_private_read_model_and_component_do_not_expose_contact_or_audit_fields(): void
    {
        [$match, $one] = $this->ready();
        $this->draft($match, $one, ['runs_scored' => 0]);
        $data = app(\App\Services\Tournament\PlayerEntryService::class)->read(2026, $match->id);
        foreach (['email', 'password', 'company_id', 'picture', 'updated_by', 'published_by'] as $field) {
            $this->assertStringNotContainsString('"'.$field.'":', json_encode($data));
        }
        Volt::test('scorer.player-editor', ['matchId' => $match->id])->call('reviewPlayerUpdates')
            ->call('selectPlayer', $one->id)->assertSet('review', null)
            ->set('form.runs_scored', 15)->call('reviewPlayerUpdates')->assertHasErrors('player')
            ->assertSet('form.runs_scored', 15)->assertSet('memberId', $one->id);
        $this->assertSame(0, PlayerMatchStat::where('match_id', $match->id)->firstOrFail()->runs_scored);
    }
}
