<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TournamentAccessibilityTest extends TestCase
{
    use DatabaseMigrations;

    public function test_public_pages_have_resolvable_focus_targets_labels_and_table_semantics(): void
    {
        config(['tournament.enabled' => true]);
        Route::middleware('web')->group(base_path('routes/tournament.php'));
        Route::getRoutes()->refreshNameLookups();
        $teams = Team::factory()->count(2)->create(['approved' => true, 'team_name' => str_repeat('LongTeam', 25)]);
        $group = DB::table('tournament_groups')->insertGetId(['name' => str_repeat('LongGroup', 25), 'tournament_year' => 2026]);
        foreach ($teams as $team) {
            DB::table('group_teams')->insert(['group_id' => $group, 'team_id' => $team->id, 'tournament_year' => 2026, 'qualified' => true]);
        }
        DB::table('matches')->insert(['stage' => 'QF', 'bracket_position' => 4, 'tournament_year' => 2026,
            'team1_id' => $teams[0]->id, 'team2_id' => $teams[1]->id, 'balls_per_over' => 4]);
        $member = TeamMember::factory()->create(['team_id' => $teams[0]->id]);
        DB::table('player_tournament_stats')->insert(['tournament_year' => 2026, 'team_id' => $member->team_id,
            'team_member_id' => $member->id, 'player_name' => str_repeat('LongPlayer', 24), 'player_gender' => 'Female',
            'runs_scored' => 20, 'highest_score' => 20, 'runs_scored_recorded_count' => 1]);

        foreach (['matches', 'table', 'bracket', 'stats'] as $tab) {
            $response = $this->get('/tournament?tab='.$tab)->assertOk();
            $document = new \DOMDocument;
            @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
            $xpath = new \DOMXPath($document);
            $this->assertSame(1, $xpath->query('//main//h1')->length);
            $this->assertSame(1, $xpath->query('//nav[@aria-label="Tournament sections"]/a[@aria-current="page"]')->length);
            foreach ($xpath->query('//main//a[starts-with(@href,"#")] | //a[@href="#public-page-content"]') as $link) {
                $id = substr($link->getAttribute('href'), 1);
                $targets = $xpath->query('//*[@id="'.$id.'"]');
                $this->assertSame(1, $targets->length, 'Fragment link must identify exactly one target.');
                $this->assertSame('-1', $targets->item(0)->getAttribute('tabindex'));
            }
            foreach ($xpath->query('//main//select') as $select) {
                $this->assertSame(1, $xpath->query('//label[@for="'.$select->getAttribute('id').'"]')->length);
            }
            foreach ($xpath->query('//main//table') as $table) {
                $this->assertSame(1, $xpath->query('./caption', $table)->length);
                $this->assertGreaterThan(0, $xpath->query('./thead/tr/th[@scope="col"]', $table)->length);
                $region = $table->parentNode;
                $this->assertSame('region', $region->getAttribute('role'));
                $this->assertSame('0', $region->getAttribute('tabindex'));
                $this->assertNotSame('', $region->getAttribute('aria-label'));
            }
            foreach ($xpath->query('//main//form') as $form) {
                $this->assertSame('get', strtolower($form->getAttribute('method')));
            }
            if ($tab === 'bracket') {
                $this->assertSame(1, $xpath->query('//article/h4')->length);
            }
        }
    }
}
