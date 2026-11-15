<?php

namespace Tests\Feature;

use App\Models\PlayerMatchStat;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TournamentMatch;
use App\Models\User;
use App\Services\JWTService;
use App\Services\Tournament\PlayerStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ScorerPlayerEntryTest extends TestCase
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

    public function test_route_and_save_return_or_next_preserve_team_and_optional_fields(): void
    {
        [$actor, $match] = $this->fixture();
        $one = TeamMember::factory()->create(['team_id' => $match->team1_id]);
        $two = TeamMember::factory()->create(['team_id' => $match->team1_id]);
        $this->signedIn($actor)->get('/scorer/matches/'.$match->id.'/players')->assertOk()
            ->assertSee('Player figures')->assertSee('noindex, nofollow');
        $component = Volt::test('scorer.player-editor', ['matchId' => $match->id])
            ->assertSee('No figures')->call('selectPlayer', $one->id)
            ->set('form.runs_scored', '0')->call('savePlayer', true)->assertHasNoErrors()
            ->assertSet('memberId', $two->id)->assertSet('teamId', $match->team1_id)
            ->set('form.dot_balls', '5')->call('savePlayer')->assertHasNoErrors()->assertSet('memberId', null)
            ->assertSee('Draft changes');
        $row = PlayerMatchStat::where('team_member_id', $one->id)->firstOrFail();
        $this->assertSame(0, $row->runs_scored);
        $this->assertNull($row->balls_faced);
        $this->assertSame('draft', $row->record_state);
        $this->assertDatabaseCount('player_tournament_stats', 0);
        $this->assertNull($match->fresh()->result_published_at);
        $component->call('selectTeam', $match->team2_id)->assertHasNoErrors()->assertSee('No players are registered');
    }

    public function test_invalid_or_stale_save_keeps_selected_player_and_entered_values(): void
    {
        [, $match] = $this->fixture();
        $member = TeamMember::factory()->create(['team_id' => $match->team1_id]);
        $component = Volt::test('scorer.player-editor', ['matchId' => $match->id])->call('selectPlayer', $member->id)
            ->set('form.dot_balls', 5)->set('form.bowling_balls', 2)->call('savePlayer')->assertHasErrors()
            ->assertSet('memberId', $member->id)->assertSet('form.dot_balls', 5);
        app(PlayerStatsService::class)->saveDraft(2026, $match->id, $member->team_id, $member->id,
            ['draft' => null, 'published' => null], ['runs_scored' => 8]);
        $component->set('form.dot_balls', null)->set('form.bowling_balls', null)
            ->set('form.runs_scored', 10)->call('savePlayer')->assertHasErrors('player')
            ->assertSet('memberId', $member->id)->assertSet('form.runs_scored', 10)
            ->call('reloadPlayer')->assertHasNoErrors()->assertSet('form.runs_scored', 8)
            ->call('discardDraft')->assertHasNoErrors()->assertSet('form.runs_scored', null);
        $this->assertDatabaseCount('player_match_stats', 0);
    }

    public function test_player_selection_is_team_scoped_and_names_are_escaped(): void
    {
        [, $match] = $this->fixture();
        TeamMember::factory()->create(['team_id' => $match->team1_id, 'name' => '<script>alert(1)</script>']);
        $other = TeamMember::factory()->create(['team_id' => $match->team2_id]);
        Volt::test('scorer.player-editor', ['matchId' => $match->id])
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)
            ->call('selectPlayer', $other->id)->assertHasErrors('player')->assertSet('memberId', null)
            ->call('selectTeam', 999999)->assertHasErrors('player')->assertSet('teamId', $match->team1_id);
    }

    public function test_revoked_scorer_cannot_save_from_an_open_editor(): void
    {
        [$actor, $match] = $this->fixture();
        $member = TeamMember::factory()->create(['team_id' => $match->team1_id]);
        $component = Volt::test('scorer.player-editor', ['matchId' => $match->id])->call('selectPlayer', $member->id)->set('form.runs_scored', 10);
        DB::table('users')->where('id', $actor->id)->update(['is_scorer' => false]);
        $component->call('savePlayer')->assertForbidden();
        $this->assertDatabaseCount('player_match_stats', 0);
    }

    public function test_withdrawal_and_discard_controls_preserve_the_official_performance(): void
    {
        [, $match] = $this->fixture();
        $member = TeamMember::factory()->create(['team_id' => $match->team1_id]);
        DB::table('player_match_stats')->insert(['match_id' => $match->id, 'team_id' => $member->team_id,
            'team_member_id' => $member->id, 'record_state' => 'published', 'pending_action' => null,
            'published_at' => now(), 'player_name' => $member->name, 'player_gender' => $member->gender, 'runs_scored' => 30]);
        $component = Volt::test('scorer.player-editor', ['matchId' => $match->id])->assertSee('Published')
            ->call('selectPlayer', $member->id)->assertSet('form.runs_scored', 30)
            ->call('stageWithdrawal')->assertHasNoErrors()->assertSet('pendingAction', 'withdraw')
            ->assertSee('Withdrawal pending')->assertSet('official.runs_scored', 30)
            ->call('discardDraft')->assertHasNoErrors()->assertSet('pendingAction', null)
            ->assertSet('form.runs_scored', 30);
        $this->assertSame(30, PlayerMatchStat::where('record_state', 'published')->firstOrFail()->runs_scored);
        $this->assertSame(0, PlayerMatchStat::where('record_state', 'draft')->count());
        $component->set('form.runs_scored', '')->call('savePlayer')->assertHasNoErrors()
            ->call('selectPlayer', $member->id)->assertSet('form.runs_scored', null)->assertSet('official.runs_scored', 30);
    }
}
