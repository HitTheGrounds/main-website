<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Team;
use App\Models\TournamentGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ScorerGroupMatchEntryTest extends TestCase
{
    use RefreshDatabase;

    protected User $scorer;
    protected TournamentGroup $group;
    protected Team $team1;
    protected Team $team2;

    protected function setUp(): void
    {
        parent::setUp();
        
        Config::set('tournament.balls_per_over.G', 4);
        Config::set('tournament.max_wickets', 11);
        Config::set('tournament.overs_per_match', 5);
        
        $this->scorer = User::factory()->create(['role' => 'scorer']);
        $this->group = TournamentGroup::create(['name' => 'Group A']);
        $company = Company::factory()->create();
        
        $this->team1 = Team::factory()->create(['company_id' => $company->id]);
        $this->team2 = Team::factory()->create(['company_id' => $company->id]);
        
        $this->group->teams()->attach([$this->team1->id, $this->team2->id]);
    }

    public function test_validates_overs_balls_and_wickets()
    {
        $this->actingAs($this->scorer);
        
        Volt::test('scorer.match-create-modal')
            ->set('stage', 'G')
            ->set('group_id', $this->group->id)
            ->set('team1_id', $this->team1->id)
            ->set('team2_id', $this->team2->id)
            ->set('status', 'finished')
            ->set('team1_score', 100)
            ->set('team1_overs', 6) // Invalid (max 5)
            ->set('team1_balls', 4) // Invalid (max 3 for group stage)
            ->set('team1_wickets', 12) // Invalid (max 11)
            ->set('outcome', 'team1')
            ->call('validateMatch')
            ->assertHasErrors(['team1_overs', 'team1_balls', 'team1_wickets']);
    }

    public function test_prevents_same_team_pairing()
    {
        $this->actingAs($this->scorer);
        
        Volt::test('scorer.match-create-modal')
            ->set('stage', 'G')
            ->set('team1_id', $this->team1->id)
            ->set('team2_id', $this->team1->id) // Same team
            ->call('validateMatch')
            ->assertHasErrors(['team1_id']);
    }
}
