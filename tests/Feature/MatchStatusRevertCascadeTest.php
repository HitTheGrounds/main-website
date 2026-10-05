<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Team;
use App\Models\TournamentGroup;
use App\Models\TournamentMatch;
use App\Models\User;
use App\Services\TournamentStandingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Volt\Volt;
use Tests\TestCase;

class MatchStatusRevertCascadeTest extends TestCase
{
    use RefreshDatabase;

    public function test_reverting_match_status_removes_from_standings()
    {
        Config::set('tournament.balls_per_over', ['G' => 4]);
        Config::set('tournament.max_wickets', 11);
        Config::set('tournament.overs_per_match', 5);
        $scorer = User::factory()->create(['role' => 'scorer']);
        $company = Company::factory()->create();
        $group = TournamentGroup::create(['name' => 'Group A']);
        
        $team1 = Team::factory()->create(['company_id' => $company->id]);
        $team2 = Team::factory()->create(['company_id' => $company->id]);
        
        $group->teams()->attach($team1->id);
        $group->teams()->attach($team2->id);

        $match = TournamentMatch::create([
            'stage' => 'G',
            'group_id' => $group->id,
            'status' => 'finished',
            'team1_id' => $team1->id,
            'team2_id' => $team2->id,
            'team1_score' => 10,
            'team1_overs' => 5,
            'team1_balls' => 0,
            'team1_wickets' => 0,
            'team2_score' => 5,
            'team2_overs' => 5,
            'team2_balls' => 0,
            'team2_wickets' => 0,
            'winner_id' => $team1->id,
            'is_draw' => false,
        ]);
        
        app(TournamentStandingsService::class)->recalculateForGroup($group);
        
        $standingsTeam1 = $group->teams()->where('teams.id', $team1->id)->first()->pivot;
        $this->assertEquals(1, $standingsTeam1->matches_played);
        $this->assertEquals(2, $standingsTeam1->points); 

        // Revert to upcoming
        $this->actingAs($scorer);
        
        Volt::test('scorer.match-create-modal')
            ->call('loadMatch', $match->id)
            ->set('status', 'upcoming')
            ->call('saveMatch');
            
        $standingsTeam1New = $group->teams()->where('teams.id', $team1->id)->first()->pivot;
        
        $this->assertEquals(0, $standingsTeam1New->matches_played);
        $this->assertEquals(0, $standingsTeam1New->points);
    }
}
