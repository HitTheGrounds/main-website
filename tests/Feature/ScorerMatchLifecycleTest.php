<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Team;
use App\Models\TournamentGroup;
use App\Models\TournamentMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ScorerMatchLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $scorer;
    protected TournamentGroup $group;
    protected Team $team1;
    protected Team $team2;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->scorer = User::factory()->create(['role' => 'scorer']);
        $this->group = TournamentGroup::create(['name' => 'Group A']);
        $company = Company::factory()->create();
        
        $this->team1 = Team::factory()->create(['company_id' => $company->id]);
        $this->team2 = Team::factory()->create(['company_id' => $company->id]);
        
        $this->group->teams()->attach([$this->team1->id, $this->team2->id]);
    }

    public function test_match_can_be_created_as_upcoming()
    {
        $this->actingAs($this->scorer);
        
        Volt::test('scorer.match-create-modal')
            ->set('stage', 'G')
            ->set('group_id', $this->group->id)
            ->set('team1_id', $this->team1->id)
            ->set('team2_id', $this->team2->id)
            ->set('status', 'upcoming')
            ->call('saveMatch')
            ->assertHasNoErrors();
            
        $this->assertDatabaseHas('matches', [
            'team1_id' => $this->team1->id,
            'status' => 'upcoming'
        ]);
    }

    public function test_matches_list_can_transition_status()
    {
        $match = TournamentMatch::create([
            'stage' => 'G',
            'group_id' => $this->group->id,
            'team1_id' => $this->team1->id,
            'team2_id' => $this->team2->id,
            'status' => 'upcoming'
        ]);
        
        $this->actingAs($this->scorer);
        
        Volt::test('scorer.matches-list', ['stage' => 'G'])
            ->call('updateStatus', $match->id, 'live');
            
        $this->assertDatabaseHas('matches', [
            'id' => $match->id,
            'status' => 'live'
        ]);
    }
}
