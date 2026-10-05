<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Team;
use App\Models\TournamentGroup;
use App\Models\TournamentMatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PublicScoreboardTest extends TestCase
{
    use RefreshDatabase;

    protected TournamentGroup $group;
    protected Team $team1;
    protected Team $team2;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->group = TournamentGroup::create(['name' => 'Group A']);
        $company = Company::factory()->create();
        
        $this->team1 = Team::factory()->create(['company_id' => $company->id]);
        $this->team2 = Team::factory()->create(['company_id' => $company->id]);
        
        $this->group->teams()->attach([$this->team1->id, $this->team2->id]);
    }

    public function test_guest_can_access_scoreboard()
    {
        $response = $this->get('/scoreboard');
        $response->assertStatus(200);
        $response->assertSee('Tournament Scoreboard');
        $response->assertSeeLivewire('public.match-cards');
        $response->assertSeeLivewire('public.group-standings');
    }

    public function test_match_cards_render_correctly()
    {
        TournamentMatch::create([
            'stage' => 'G',
            'status' => 'upcoming',
            'group_id' => $this->group->id,
            'team1_id' => $this->team1->id,
            'team2_id' => $this->team2->id,
        ]);
        
        Volt::test('public.match-cards')
            ->assertSee($this->team1->team_name)
            ->assertSee($this->team2->team_name)
            ->assertSee('Upcoming', false)
            ->set('statusFilter', 'finished')
            ->assertDontSee($this->team1->team_name);
    }
}
