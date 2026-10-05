<?php

namespace Tests\Unit;

use App\Models\GroupTeam;
use App\Models\Team;
use App\Models\TournamentGroup;
use App\Models\TournamentMatch;
use App\Services\NRRCalculationService;
use App\Services\TournamentStandingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class TournamentStandingsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TournamentStandingsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TournamentStandingsService(new NRRCalculationService());
        
        Config::set('tournament.points_win', 2);
        Config::set('tournament.points_draw', 1);
        Config::set('tournament.points_loss', 0);
        Config::set('tournament.balls_per_over.G', 4);
    }

    public function test_points_accumulation(): void
    {
        $group = TournamentGroup::create(['name' => 'Group A']);
        $team1 = Team::factory()->create();
        $team2 = Team::factory()->create();
        $team3 = Team::factory()->create();
        
        GroupTeam::create(['group_id' => $group->id, 'team_id' => $team1->id]);
        GroupTeam::create(['group_id' => $group->id, 'team_id' => $team2->id]);
        GroupTeam::create(['group_id' => $group->id, 'team_id' => $team3->id]);
        
        // Team 1 vs Team 2: Team 1 wins
        TournamentMatch::create([
            'stage' => 'G',
            'status' => 'finished',
            'group_id' => $group->id,
            'team1_id' => $team1->id,
            'team2_id' => $team2->id,
            'winner_id' => $team1->id,
        ]);
        
        // Team 1 vs Team 3: Draw
        TournamentMatch::create([
            'stage' => 'G',
            'status' => 'finished',
            'group_id' => $group->id,
            'team1_id' => $team1->id,
            'team2_id' => $team3->id,
            'is_draw' => true,
        ]);
        
        $this->service->recalculateForGroup($group);
        
        $gt1 = GroupTeam::where('team_id', $team1->id)->first();
        $this->assertEquals(2, $gt1->matches_played);
        $this->assertEquals(1, $gt1->wins);
        $this->assertEquals(0, $gt1->losses);
        $this->assertEquals(1, $gt1->draws);
        $this->assertEquals(3, $gt1->points); // 2 + 1 = 3
        
        $gt2 = GroupTeam::where('team_id', $team2->id)->first();
        $this->assertEquals(1, $gt2->matches_played);
        $this->assertEquals(0, $gt2->wins);
        $this->assertEquals(1, $gt2->losses);
        $this->assertEquals(0, $gt2->points);
    }

    public function test_upcoming_and_live_matches_produce_zero_impact(): void
    {
        $group = TournamentGroup::create(['name' => 'Group A']);
        $team1 = Team::factory()->create();
        $team2 = Team::factory()->create();
        
        GroupTeam::create(['group_id' => $group->id, 'team_id' => $team1->id]);
        GroupTeam::create(['group_id' => $group->id, 'team_id' => $team2->id]);
        
        // Upcoming match
        TournamentMatch::create([
            'stage' => 'G',
            'status' => 'upcoming',
            'group_id' => $group->id,
            'team1_id' => $team1->id,
            'team2_id' => $team2->id,
            'winner_id' => $team1->id, // Should not matter
        ]);
        
        // Live match
        TournamentMatch::create([
            'stage' => 'G',
            'status' => 'live',
            'group_id' => $group->id,
            'team1_id' => $team1->id,
            'team2_id' => $team2->id,
            'team1_score' => 50, // Should not matter
        ]);
        
        $this->service->recalculateForGroup($group);
        
        $gt1 = GroupTeam::where('team_id', $team1->id)->first();
        $this->assertEquals(0, $gt1->matches_played);
        $this->assertEquals(0, $gt1->points);
        $this->assertEquals(0, $gt1->total_runs_scored);
    }
}
