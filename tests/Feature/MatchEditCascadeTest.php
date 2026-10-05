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

class MatchEditCascadeTest extends TestCase
{
    use RefreshDatabase;

    public function test_editing_finished_group_match_recalculates_standings()
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
        $this->assertEquals(2, $standingsTeam1->points); // 2 points for win

        // Edit the match so team2 wins
        $this->actingAs($scorer);
        
        Volt::test('scorer.match-create-modal')
            ->call('loadMatch', $match->id)
            ->set('team2_score', 15) // Now Team 2 wins
            ->set('outcome', 'team2')
            ->call('saveMatch');
            
        $standingsTeam1New = $group->teams()->where('teams.id', $team1->id)->first()->pivot;
        $standingsTeam2New = $group->teams()->where('teams.id', $team2->id)->first()->pivot;
        
        $this->assertEquals(0, $standingsTeam1New->points); // Lost now
        $this->assertEquals(2, $standingsTeam2New->points); // Won now
    }
    
    public function test_optimistic_locking_prevents_overwrite()
    {
        $scorer = User::factory()->create(['role' => 'scorer']);
        $company = Company::factory()->create();
        $team1 = Team::factory()->create(['company_id' => $company->id]);
        $team2 = Team::factory()->create(['company_id' => $company->id]);
        
        $match = TournamentMatch::create([
            'stage' => 'QF',
            'bracket_position' => 1,
            'status' => 'upcoming',
            'team1_id' => $team1->id,
            'team2_id' => $team2->id,
        ]);
        
        $this->actingAs($scorer);
        
        $component = Volt::test('scorer.match-create-modal')
            ->call('loadMatch', $match->id);
            
        // Simulate another user editing the match in the meantime (wait 1 second to ensure different timestamp if needed)
        sleep(1);
        $match->update(['status' => 'live']); // this changes updated_at
        
        $component->set('status', 'finished')
            ->set('team1_score', 10)->set('team1_overs', 5)->set('team1_balls', 0)->set('team1_wickets', 0)
            ->set('team2_score', 5)->set('team2_overs', 5)->set('team2_balls', 0)->set('team2_wickets', 0)
            ->set('outcome', 'team1')
            ->call('saveMatch')
            ->assertHasErrors(['conflict']);
    }
    
    public function test_knockout_edit_safety_warning()
    {
        $scorer = User::factory()->create(['role' => 'scorer']);
        $company = Company::factory()->create();
        $team1 = Team::factory()->create(['company_id' => $company->id]);
        $team2 = Team::factory()->create(['company_id' => $company->id]);
        
        $qf = TournamentMatch::create([
            'stage' => 'QF',
            'bracket_position' => 1,
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
        
        TournamentMatch::create([
            'stage' => 'SF',
            'bracket_position' => 1,
            'status' => 'upcoming',
            'team1_id' => $team1->id,
            'team2_id' => $team2->id, // team2 is just a placeholder here
        ]);
        
        $this->actingAs($scorer);
        
        Volt::test('scorer.match-create-modal')
            ->call('loadMatch', $qf->id)
            ->set('outcome', 'team2')
            ->call('validateMatch')
            ->assertHasErrors(['conflict']);
    }
}
