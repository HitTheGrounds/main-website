<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\TournamentMatch;
use App\Models\User;
use App\Services\Tournament\MatchResultService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ScorerScreenUpdatesTest extends TestCase
{
    // Commit real transactions so the revision's afterCommit callback runs.
    use DatabaseMigrations;

    public function test_publishing_a_result_updates_open_standings_and_the_revision_used_by_all_led_screens(): void
    {
        config([
            'tournament.enabled' => true,
            'tournament.year' => 2026,
            'tournament.venues' => ['Ground 1'],
        ]);
        Route::middleware('web')->group(base_path('routes/tournament.php'));
        Route::getRoutes()->refreshNameLookups();

        $scorer = User::factory()->create(['is_admin' => false, 'company_id' => null, 'is_scorer' => true]);
        $this->signedIn($scorer);
        Livewire::withCookie('company_token', $scorer->fresh()->jwt_token);
        $teams = Team::factory()->count(2)->create(['approved' => true]);
        $groupId = DB::table('tournament_groups')->insertGetId(['tournament_year' => 2026, 'name' => 'Group A']);
        foreach ($teams as $team) {
            DB::table('group_teams')->insert(['tournament_year' => 2026, 'group_id' => $groupId, 'team_id' => $team->id]);
        }
        $matchId = DB::table('matches')->insertGetId([
            'tournament_year' => 2026, 'group_id' => $groupId, 'stage' => 'G',
            'team1_id' => $teams[0]->id, 'team2_id' => $teams[1]->id,
            'balls_per_over' => 4, 'status' => 'upcoming', 'venue' => 'Ground 1',
        ]);

        $screens = ['bracket', 'grounds', 'cap_leaderboard', 'stats_leaderboard'];
        $initialRevision = $this->get('/tournament/rev')->assertOk()->json('rev');
        foreach ($screens as $screen) {
            $this->get('/scorer/screen/'.$screen)->assertOk()
                ->assertViewHas('rev', $initialRevision)
                ->assertSee('tournament\/rev', false)
                ->assertSee('location.reload()', false);
        }
        $standings = Volt::test('scorer.group-screen')
            ->assertSee('wire:poll.15s', false)
            ->assertViewHas('groups', fn ($groups) => $groups[0]['teams'][0]['played'] === 0);

        $this->actAs($scorer);
        $results = app(MatchResultService::class);
        $results->saveDraft(2026, $matchId, 1, [
            'result_type' => 'win', 'result_method' => 'normal', 'winner_id' => $teams[0]->id,
            'batting_first_id' => $teams[0]->id,
            'team1_runs' => 100, 'team1_wickets' => 3, 'team1_overs' => 5, 'team1_balls' => 0,
            'team2_runs' => 90, 'team2_wickets' => 3, 'team2_overs' => 5, 'team2_balls' => 0,
        ]);
        // Draft scores must not change the standings on the wall.
        $standings->call('$refresh')
            ->assertViewHas('groups', fn ($groups) => $groups[0]['teams'][0]['played'] === 0);
        $draftRevision = $this->get('/tournament/rev')->assertOk()->json('rev');

        $this->actAs($scorer);
        $review = $results->review(2026, $matchId);
        $results->publish(2026, $matchId, $review['version'], $review['draft_hash']);
        $publishedRevision = $this->get('/tournament/rev')->assertOk()->json('rev');
        $this->assertGreaterThan($draftRevision, $publishedRevision);

        // This is the same refresh action Livewire's poll sends for the mounted component.
        $standings->call('$refresh')->assertViewHas('groups', function ($groups) use ($teams) {
            $winner = collect($groups[0]['teams'])->firstWhere('id', $teams[0]->id);

            return $winner['played'] === 1 && $winner['wins'] === 1 && $winner['points'] === 2;
        });
        foreach ($screens as $screen) {
            $this->get('/scorer/screen/'.$screen)->assertOk()->assertViewHas('rev', $publishedRevision);
        }
        $this->get('/scorer/screen/grounds')->assertOk()
            ->assertSee('Latest ground result')
            ->assertViewHas('groundsData', fn ($data) => $data['grounds'][0]['latest_finished']['id'] === $matchId);
        $this->assertSame('finished', TournamentMatch::findOrFail($matchId)->status);
    }
}
