<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TournamentGroup;
use App\Models\TournamentMatch;
use App\Models\User;
use App\Models\VerificationCode;
use App\Services\JWTService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

class TournamentUserJourneyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_scorer_screen_actions_produce_public_results_from_setup_through_final(): void
    {
        config(['tournament.enabled' => true, 'tournament.year' => 2026, 'tournament.writes_per_minute' => 1000]);
        Route::middleware('web')->group(base_path('routes/tournament.php'));
        Route::getRoutes()->refreshNameLookups();
        Notification::fake();

        // Provision through the admin screen, then use the existing email-code login.
        $admin = User::factory()->create(['is_admin' => true, 'company_id' => null]);
        Livewire::withCookie('company_token', (new JWTService)->generateToken($admin));
        Volt::test('admin.scorer-accounts-manager')->set('name', 'Journey scorer')
            ->set('email', 'journey@example.test')->call('createScorer')->assertHasNoErrors();
        $scorer = User::where('email', 'journey@example.test')->firstOrFail();
        $this->assertTrue($scorer->is_scorer);
        $this->assertFalse($scorer->is_admin);
        $this->assertNull($scorer->company_id);
        Livewire::withCookie('company_token', '');
        $login = Volt::test('company-login-form')->set('contact_email', $scorer->email)
            ->call('sendCode')->assertHasNoErrors();
        $code = VerificationCode::where('user_id', $scorer->id)->firstOrFail();
        $login->set('verification_code', $code->code)->call('verify')
            ->assertHasNoErrors()->assertRedirect('/scorer/dashboard');
        $this->assertTrue($code->fresh()->is_used);
        // Share the token actually issued by login; do not issue a second JWT.
        $token = $scorer->fresh()->jwt_token;
        $this->assertNotNull($token);
        Livewire::withCookie('company_token', $token);
        $this->withCookie('company_token', $token)->get('/scorer/dashboard')->assertOk();
        $this->get('/admin/tournament/scorers')->assertForbidden();

        // Approved teams and their players already exist before scoring starts.
        $teams = Team::factory()->count(8)->create(['approved' => true]);
        $originalTeams = $teams->map(fn ($team) => $team->fresh()->getAttributes())->all();
        $batter = TeamMember::factory()->create(['team_id' => $teams[0]->id, 'name' => 'Journey batter', 'gender' => 'Female']);
        $bowler = TeamMember::factory()->create(['team_id' => $teams[1]->id, 'name' => 'Journey bowler', 'gender' => 'Male']);
        $groupMatches = [];
        foreach (range(0, 3) as $index) {
            $groups = Volt::test('scorer.groups-manager')->set('newGroupName', 'Journey pool '.($index + 1))
                ->call('createGroup')->assertHasNoErrors();
            $group = TournamentGroup::latest('id')->firstOrFail();
            foreach ([$teams[$index * 2], $teams[$index * 2 + 1]] as $team) {
                $groups->set('selectedTeamId', (string) $team->id)->call('assignTeam')->assertHasNoErrors();
            }
            Volt::test('scorer.matches-manager')->set('groupChoice', (string) $group->id)
                ->set('newStructure.team1_id', (string) $teams[$index * 2]->id)
                ->set('newStructure.team2_id', (string) $teams[$index * 2 + 1]->id)
                ->set('newStructure.balls_per_over', $index % 2 === 0 ? '4' : '6')
                ->call('createMatch')->assertHasNoErrors();
            $match = TournamentMatch::latest('id')->firstOrFail();
            $this->assertSame($index % 2 === 0 ? 4 : 6, $match->balls_per_over);
            $groupMatches[] = $match;
        }
        $this->anonymousPage('table')->assertSee('Journey pool 4');
        $this->assertCount(4, $this->anonymousPage('table')->viewData('tableData')['groups']);
        foreach ($groupMatches as $match) {
            $this->publishWin($match);
        }
        $tables = $this->anonymousPage('table')->assertSee('Group results complete')->viewData('tableData')['groups'];
        foreach ($tables as $table) {
            $this->assertSame([2, 0], array_column($table['teams'], 'points'));
            $this->assertSame([false, false], array_column($table['teams'], 'qualified'));
            // 100/5 - 90/5 = 2, independently of four- or six-ball overs.
            $this->assertEquals(2.0, $table['teams'][0]['nrr']);
            $this->assertEquals(-2.0, $table['teams'][1]['nrr']);
        }

        // Optional, incomplete entries stay private until separately published.
        $players = Volt::test('scorer.player-editor', ['matchId' => $groupMatches[0]->id])
            ->call('selectPlayer', $batter->id)->set('form.runs_scored', 35)->call('savePlayer')->assertHasNoErrors()
            ->call('selectTeam', $bowler->team_id)->call('selectPlayer', $bowler->id)
            ->set('form.dot_balls', 0)->call('savePlayer')->assertHasNoErrors();
        $this->assertSame([], $this->anonymousPage('stats')->viewData('rankingData')['entries']);
        $players->call('reviewPlayerUpdates')->assertSee('Incomplete figures')
            ->call('publishPlayerUpdates')->assertHasNoErrors();
        $oldPage = $this->anonymousPage('stats');
        $this->assertSame(35, $oldPage->viewData('rankingData')['entries'][0]['value']);
        $dots = $this->anonymousPage('stats&category=dot_balls')->viewData('rankingData');
        $this->assertSame([$bowler->id], array_column($dots['entries'], 'player_id'));
        $this->assertSame(0, $dots['entries'][0]['value']);
        $this->assertSame([], $dots['leader_ids']);
        $players->call('selectTeam', $batter->team_id)->call('selectPlayer', $batter->id)
            ->set('form.runs_scored', 30)->call('savePlayer')->assertHasNoErrors();
        $this->assertSame(35, $this->anonymousPage('stats')->viewData('rankingData')['entries'][0]['value']);
        $players->call('reviewPlayerUpdates')->call('publishPlayerUpdates')->assertHasNoErrors();
        $this->assertSame(30, $this->anonymousPage('stats')->viewData('rankingData')['entries'][0]['value']);
        $this->assertSame(30, $this->anonymousPage('stats&category=highest_innings&gender=Female')->viewData('rankingData')['entries'][0]['value']);
        $this->assertSame(35, $oldPage->viewData('rankingData')['entries'][0]['value']);

        // Ranking guidance does not select qualifiers or impose match pairings.
        $knockouts = Volt::test('scorer.knockouts-manager')->assertSee('Runner-up guidance (points, then NRR)')
            ->set('selected', $teams->pluck('id')->all())->call('saveSelection')->assertHasNoErrors()->call('reloadRound');
        $quarters = [];
        foreach ([4, 1, 3, 2] as $index => $position) {
            $this->createKnockout($knockouts, $teams[$index * 2]->id, $teams[$index * 2 + 1]->id, $position, 4);
            $quarters[] = TournamentMatch::latest('id')->firstOrFail();
        }
        // No automatic semi-final fixtures appear when quarter-final winners publish.
        foreach ($quarters as $match) {
            $this->publishWin($match);
        }
        $this->assertSame(0, TournamentMatch::where('stage', 'SF')->count());
        $knockouts->call('chooseRound', 'SF');
        $this->createKnockout($knockouts, $teams[6]->id, $teams[0]->id, 2, 6);
        $sf2 = TournamentMatch::latest('id')->firstOrFail();
        $this->createKnockout($knockouts, $teams[2]->id, $teams[4]->id, 1, 6);
        $sf1 = TournamentMatch::latest('id')->firstOrFail();
        $rounds = $this->anonymousPage('bracket')->viewData('bracketData')['rounds'];
        $this->assertSame($quarters[3]->id, $rounds['SF'][1]['team1']['source_match_id']);
        $this->assertSame($quarters[0]->id, $rounds['SF'][1]['team2']['source_match_id']);
        $this->publishWin($sf2);
        $this->publishWin($sf1);
        $this->assertSame(0, TournamentMatch::where('stage', 'F')->count());
        $knockouts->call('chooseRound', 'F');
        $this->createKnockout($knockouts, $teams[6]->id, $teams[2]->id, 1, 6);
        $final = TournamentMatch::latest('id')->firstOrFail();
        $rounds = $this->anonymousPage('bracket')->viewData('bracketData')['rounds'];
        $this->assertSame($sf2->id, $rounds['F'][0]['team1']['source_match_id']);
        $this->assertSame($sf1->id, $rounds['F'][0]['team2']['source_match_id']);
        $this->publishWin($final);
        $publicFinal = $this->anonymousPage('bracket')->assertSee($teams[6]->team_name)->assertSee('100/3')
            ->viewData('bracketData')['rounds']['F'][0]['match'];
        $this->assertSame($teams[6]->id, $publicFinal['result']['winner_id']);
        $this->assertSame('finished', $publicFinal['status']);
        $this->anonymousPage('matches&stage=F')->assertSee($teams[6]->team_name)->assertSee('100/3');
        $this->assertSame($teams[6]->id, $final->fresh()->winner_id);
        $knockouts->call('reloadRound')->assertSee('Champion: '.$teams[6]->team_name);
        $this->assertSame($originalTeams, $teams->map(fn ($team) => $team->fresh()->getAttributes())->all());
    }

    private function anonymousPage(string $query)
    {
        // Livewire's test client takes subsequent cookies from app('request').
        // Keep the spectator request separate from the open scorer's session.
        $scorerRequest = request();
        try {
            $page = $this->withCookie('company_token', '')->get('/tournament?tab='.$query)->assertOk();
            $this->assertNull(\App\Helpers\CompanyAuth::user());

            return $page;
        } finally {
            app()->instance('request', $scorerRequest);
        }
    }

    private function publishWin(TournamentMatch $match): void
    {
        Volt::test('scorer.result-editor', ['matchId' => $match->id])->set('form', [
            'result_type' => 'win', 'result_method' => 'normal', 'winner_id' => $match->team1_id,
            'batting_first_id' => $match->team1_id, 'team1_runs' => 100, 'team1_wickets' => 3,
            'team1_overs' => 5, 'team1_balls' => 0, 'team2_runs' => 90, 'team2_wickets' => 3,
            'team2_overs' => 5, 'team2_balls' => 0,
        ])->call('reviewResult')->assertHasNoErrors()->call('publishResult')->assertHasNoErrors();
        $this->assertSame('finished', $match->fresh()->status);
    }

    private function createKnockout($component, int $one, int $two, int $position, int $balls): void
    {
        $component->set('fixture', ['team1_id' => (string) $one, 'team2_id' => (string) $two,
            'bracket_position' => (string) $position, 'balls_per_over' => (string) $balls])
            ->call('createMatch')->assertHasNoErrors();
    }
}
