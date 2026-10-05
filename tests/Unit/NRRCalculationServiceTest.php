<?php

namespace Tests\Unit;

use App\Models\GroupTeam;
use App\Models\Team;
use App\Models\TournamentGroup;
use App\Models\TournamentMatch;
use App\Services\NRRCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class NRRCalculationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected NRRCalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new NRRCalculationService();
        
        Config::set('tournament.balls_per_over.G', 4);
        Config::set('tournament.max_wickets', 11);
        Config::set('tournament.overs_per_match', 5);
    }

    public function test_nrr_decimal_overs_conversion_and_positive_nrr(): void
    {
        $group = TournamentGroup::create(['name' => 'Group A']);
        $team1 = Team::factory()->create();
        $team2 = Team::factory()->create();
        
        $groupTeam = GroupTeam::create(['group_id' => $group->id, 'team_id' => $team1->id]);
        
        // Match 1: Team 1 scores 20 runs in 3 overs 2 balls (3.5 overs). Concedes 10 runs in 4 overs 0 balls.
        TournamentMatch::create([
            'stage' => 'G',
            'status' => 'finished',
            'group_id' => $group->id,
            'team1_id' => $team1->id,
            'team2_id' => $team2->id,
            'team1_score' => 20,
            'team1_overs' => 3,
            'team1_balls' => 2,
            'team1_wickets' => 0,
            'team2_score' => 10,
            'team2_overs' => 4,
            'team2_balls' => 0,
            'team2_wickets' => 0,
            'winner_id' => $team1->id,
        ]);

        $nrr = $this->service->calculateNRR($groupTeam);
        
        // Team 1 Scored: 20 runs in 3.5 overs = 5.714
        // Team 1 Conceded: 10 runs in 4.0 overs = 2.5
        // NRR = 5.714 - 2.5 = 3.214
        $this->assertEqualsWithDelta(3.214, $nrr, 0.01);
    }

    public function test_all_out_rule_is_applied(): void
    {
        $group = TournamentGroup::create(['name' => 'Group A']);
        $team1 = Team::factory()->create();
        $team2 = Team::factory()->create();
        
        $groupTeam = GroupTeam::create(['group_id' => $group->id, 'team_id' => $team1->id]);
        
        // Match 1: Team 1 all out (11 wickets) for 15 runs in 2 overs 1 ball.
        // Even though they batted 2.25 overs, it counts as 5.0 overs.
        // They bowled out Team 2 (11 wickets) for 10 runs in 1 over 1 ball.
        // Counts as 5.0 overs for bowled.
        TournamentMatch::create([
            'stage' => 'G',
            'status' => 'finished',
            'group_id' => $group->id,
            'team1_id' => $team1->id,
            'team2_id' => $team2->id,
            'team1_score' => 15,
            'team1_overs' => 2,
            'team1_balls' => 1,
            'team1_wickets' => 11,
            'team2_score' => 10,
            'team2_overs' => 1,
            'team2_balls' => 1,
            'team2_wickets' => 11,
            'winner_id' => $team1->id,
        ]);

        $nrr = $this->service->calculateNRR($groupTeam);
        
        // Scored: 15 / 5.0 = 3.0
        // Conceded: 10 / 5.0 = 2.0
        // NRR = 3.0 - 2.0 = 1.0
        $this->assertEqualsWithDelta(1.0, $nrr, 0.01);
    }

    public function test_zero_overs_division_protection(): void
    {
        $group = TournamentGroup::create(['name' => 'Group A']);
        $team1 = Team::factory()->create();
        $team2 = Team::factory()->create();
        
        $groupTeam = GroupTeam::create(['group_id' => $group->id, 'team_id' => $team1->id]);
        
        // Match with 0 overs 0 balls. Should not throw division by zero.
        TournamentMatch::create([
            'stage' => 'G',
            'status' => 'finished',
            'group_id' => $group->id,
            'team1_id' => $team1->id,
            'team2_id' => $team2->id,
            'team1_score' => 0,
            'team1_overs' => 0,
            'team1_balls' => 0,
            'team1_wickets' => 0,
            'team2_score' => 0,
            'team2_overs' => 0,
            'team2_balls' => 0,
            'team2_wickets' => 0,
            'is_draw' => true,
        ]);

        $nrr = $this->service->calculateNRR($groupTeam);
        $this->assertEquals(0.0, $nrr);
    }
}
