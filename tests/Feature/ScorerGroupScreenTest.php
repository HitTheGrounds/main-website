<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ScorerGroupScreenTest extends TestCase
{
    use RefreshDatabase;

    public function test_led_screen_is_scorer_only_and_lists_every_group_with_played_points_and_nrr(): void
    {
        config(['tournament.enabled' => true, 'tournament.year' => 2026]);
        Route::middleware('web')->group(base_path('routes/tournament.php'));
        Route::getRoutes()->refreshNameLookups();
        foreach (['Group A' => 'Aayu Technologies', 'Group B' => 'BoardOne A'] as $group => $name) {
            $groupId = DB::table('tournament_groups')->insertGetId(['tournament_year' => 2026, 'name' => $group]);
            DB::table('group_teams')->insert(['tournament_year' => 2026, 'group_id' => $groupId,
                'team_id' => Team::factory()->create(['team_name' => $name, 'approved' => true])->id,
                'played' => 3, 'points' => 4, 'nrr' => '1.2345']);
        }

        $company = User::factory()->create(['is_admin' => false]);
        $this->signedIn($company)->get('/scorer/screen')->assertForbidden();

        $scorer = User::factory()->create(['is_admin' => false, 'company_id' => null]);
        $scorer->is_scorer = true;
        $scorer->save();
        $this->signedIn($scorer)->get('/scorer/screen')->assertOk()
            ->assertSee('wire:poll.15s', false)
            ->assertSeeInOrder(['Group A', 'Aayu Technologies', '3', '4', '1.235', 'Group B', 'BoardOne A']);
    }
}
