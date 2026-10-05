<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Team;
use App\Models\TournamentMatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PublicBracketViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_bracket_renders_correctly()
    {
        $company = Company::factory()->create();
        $team1 = Team::factory()->create(['company_id' => $company->id]);
        $team2 = Team::factory()->create(['company_id' => $company->id]);
        
        // QF Match (Live)
        TournamentMatch::create([
            'stage' => 'QF',
            'bracket_position' => 1,
            'team1_id' => $team1->id,
            'team2_id' => $team2->id,
            'status' => 'live'
        ]);
        
        // Final Match (Finished)
        TournamentMatch::create([
            'stage' => 'F',
            'bracket_position' => 1,
            'team1_id' => $team1->id,
            'team2_id' => $team2->id,
            'status' => 'finished',
            'team1_score' => 10,
            'team1_overs' => 5,
            'team1_balls' => 0,
            'team1_wickets' => 0,
            'team2_score' => 5,
            'team2_overs' => 5,
            'team2_balls' => 0,
            'team2_wickets' => 0,
            'is_draw' => false,
            'winner_id' => $team1->id,
        ]);

        $response = $this->get('/scoreboard');
        $response->assertStatus(200);
        $response->assertSeeLivewire('public.tournament-bracket');
        
        Volt::test('public.tournament-bracket')
            ->assertSee($team1->team_name)
            ->assertSee('LIVE')
            ->assertSee('FINISHED')
            ->assertSee('Champions');
    }
}
