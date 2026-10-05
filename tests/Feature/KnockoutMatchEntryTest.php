<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Team;
use App\Models\TournamentMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Volt\Volt;
use Tests\TestCase;

class KnockoutMatchEntryTest extends TestCase
{
    use RefreshDatabase;

    protected User $scorer;
    protected Team $team1;
    protected Team $team2;

    protected function setUp(): void
    {
        parent::setUp();
        
        Config::set('tournament.balls_per_over', [
            'G' => 4,
            'QF' => 4,
            'SF' => 4,
            'F' => 6
        ]);
        Config::set('tournament.max_wickets', 11);
        Config::set('tournament.overs_per_match', 5);
        
        $this->scorer = User::factory()->create(['role' => 'scorer']);
        $company = Company::factory()->create();
        
        $this->team1 = Team::factory()->create(['company_id' => $company->id]);
        $this->team2 = Team::factory()->create(['company_id' => $company->id]);
    }

    public function test_qf_rejects_draw()
    {
        $this->actingAs($this->scorer);
        
        Volt::test('scorer.match-create-modal')
            ->set('stage', 'QF')
            ->set('team1_id', $this->team1->id)
            ->set('team2_id', $this->team2->id)
            ->set('bracket_position', 1)
            ->set('status', 'finished')
            ->set('team1_score', 100)
            ->set('team1_overs', 5)
            ->set('team1_balls', 0)
            ->set('team1_wickets', 0)
            ->set('team2_score', 100)
            ->set('team2_overs', 5)
            ->set('team2_balls', 0)
            ->set('team2_wickets', 0)
            ->set('outcome', 'tie')
            ->call('validateMatch')
            ->assertHasErrors(['outcome']);
    }

    public function test_bracket_position_validation()
    {
        $this->actingAs($this->scorer);
        
        Volt::test('scorer.match-create-modal')
            ->set('stage', 'QF')
            ->set('team1_id', $this->team1->id)
            ->set('team2_id', $this->team2->id)
            ->set('bracket_position', 5)
            ->set('status', 'upcoming')
            ->call('validateMatch')
            ->assertHasErrors(['bracket_position']);
            
        Volt::test('scorer.match-create-modal')
            ->set('stage', 'SF')
            ->set('team1_id', $this->team1->id)
            ->set('team2_id', $this->team2->id)
            ->set('bracket_position', 3)
            ->set('status', 'upcoming')
            ->call('validateMatch')
            ->assertHasErrors(['bracket_position']);
    }

    public function test_final_allows_six_balls()
    {
        $this->actingAs($this->scorer);
        
        Volt::test('scorer.match-create-modal')
            ->set('stage', 'F')
            ->set('team1_id', $this->team1->id)
            ->set('team2_id', $this->team2->id)
            ->set('status', 'finished')
            ->set('team1_score', 100)
            ->set('team1_overs', 5)
            ->set('team1_balls', 5) // Allowed (6 balls per over)
            ->set('team1_wickets', 0)
            ->set('team2_score', 100)
            ->set('team2_overs', 5)
            ->set('team2_balls', 5)
            ->set('team2_wickets', 0)
            ->set('outcome', 'team1')
            ->call('validateMatch')
            ->assertHasNoErrors();
    }

    public function test_slot_locking_prevents_swap()
    {
        $qf1 = TournamentMatch::create(['stage' => 'QF', 'bracket_position' => 1, 'team1_id' => $this->team1->id, 'team2_id' => $this->team2->id, 'status' => 'finished']);
        $qf2 = TournamentMatch::create(['stage' => 'QF', 'bracket_position' => 2, 'team1_id' => $this->team1->id, 'team2_id' => $this->team2->id, 'status' => 'finished']);
        
        TournamentMatch::create(['stage' => 'SF', 'bracket_position' => 1, 'team1_id' => $this->team1->id, 'team2_id' => $this->team2->id, 'status' => 'upcoming']);
        
        Volt::test('scorer.bracket-slot-manager')
            ->call('swapSlots', 1, 2)
            ->assertHasErrors(['swap']);
            
        $this->assertEquals(1, $qf1->fresh()->bracket_position);
    }
}
