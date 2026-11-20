<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TournamentMatch;
use App\Models\User;
use App\Services\JWTService;
use App\Services\Tournament\MatchResultService;
use App\Services\Tournament\PublicTournamentService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicTournamentPrivacyTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        config(['tournament.enabled' => true, 'tournament.year' => 2026]);
        Route::middleware('web')->group(base_path('routes/tournament.php'));
        Route::getRoutes()->refreshNameLookups();
    }

    private function fixture(): array
    {
        $teams = Team::factory()->count(2)->create(['approved' => true, 'captain_email' => 'PRIVATE-CONTACT@example.test']);
        $group = DB::table('tournament_groups')->insertGetId(['name' => 'Public group', 'tournament_year' => 2026]);
        foreach ($teams as $team) {
            DB::table('group_teams')->insert(['group_id' => $group, 'team_id' => $team->id, 'tournament_year' => 2026,
                'point_adjustments' => json_encode([['points' => 0, 'reason' => 'PRIVATE-ADJUSTMENT']])]);
        }
        $id = DB::table('matches')->insertGetId(['group_id' => $group, 'tournament_year' => 2026, 'stage' => 'G',
            'team1_id' => $teams[0]->id, 'team2_id' => $teams[1]->id, 'balls_per_over' => 4,
            'result_note' => 'PRIVATE-NOTE', 'draft_result' => json_encode(['result_note' => 'PRIVATE-RESULT-DRAFT'])]);
        $member = TeamMember::factory()->create(['team_id' => $teams[0]->id, 'name' => 'PRIVATE-ROSTER-NAME']);
        DB::table('player_match_stats')->insert(['match_id' => $id, 'team_id' => $teams[0]->id, 'team_member_id' => $member->id,
            'player_name' => 'PRIVATE-PLAYER-DRAFT', 'player_gender' => 'Male', 'runs_scored' => 876543]);

        return TournamentMatch::findOrFail($id)->only(['id', 'team1_id', 'team2_id']);
    }

    private function sourceState(): array
    {
        $state = [];
        foreach (['matches', 'tournament_groups', 'group_teams', 'player_match_stats', 'player_tournament_stats', 'teams', 'team_members'] as $table) {
            $state[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }

        return $state;
    }

    public function test_guest_gets_select_safe_fields_and_never_mutate_or_embed_private_state(): void
    {
        $this->fixture();
        $before = $this->sourceState();
        DB::enableQueryLog();
        DB::flushQueryLog();
        foreach (['matches', 'table', 'bracket', 'stats'] as $tab) {
            $response = $this->get('/tournament?tab='.$tab)->assertOk()->assertDontSee('PRIVATE-')->assertDontSee('876543');
            foreach (['draft_result', 'result_note', 'nrr_inputs', 'point_adjustments', 'updated_by', 'published_by',
                'player_stats_revision', 'draft_hash', 'wire:snapshot', 'wire:submit', 'Publish result', 'Save draft'] as $private) {
                $response->assertDontSee($private, false);
            }
            $document = new \DOMDocument;
            @$document->loadHTML($response->getContent());
            foreach ($document->getElementsByTagName('form') as $form) {
                $this->assertContains(strtolower($form->getAttribute('method')), ['', 'get']);
            }
            $data = $response->viewData(match ($tab) {
                'matches' => 'matchData', 'table' => 'tableData', 'bracket' => 'bracketData', 'stats' => 'rankingData',
            });
            $this->assertStringNotContainsString('PRIVATE-', json_encode($data, JSON_THROW_ON_ERROR));
        }
        $queries = strtolower(implode("\n", array_column(DB::getQueryLog(), 'query')));
        DB::disableQueryLog();
        foreach (['insert ', 'update ', 'delete ', 'for update', 'advisory', 'draft_result', 'result_note', 'nrr_inputs',
            'point_adjustments', 'captain_email', 'team_members', 'player_match_stats'] as $privateOrWrite) {
            $this->assertStringNotContainsString($privateOrWrite, $queries);
        }
        $this->assertSame($before, $this->sourceState());
    }

    public function test_public_pages_change_after_real_result_publication_but_not_draft_corrections(): void
    {
        $match = $this->fixture();
        $actor = User::factory()->create(['company_id' => null, 'is_admin' => false]);
        $actor->is_scorer = true;
        $actor->save();
        $results = app(MatchResultService::class);
        $input = ['result_type' => 'win', 'result_method' => 'normal', 'winner_id' => $match['team1_id'],
            'batting_first_id' => $match['team1_id'], 'team1_runs' => 100, 'team1_wickets' => 3, 'team1_overs' => 5,
            'team1_balls' => 0, 'team2_runs' => 90, 'team2_wickets' => 7, 'team2_overs' => 3, 'team2_balls' => 0];
        request()->attributes->set('user', $actor);
        $results->saveDraft(2026, $match['id'], 1, $input);
        request()->attributes->remove('user');
        $this->get('/tournament')->assertOk()->assertDontSee('won by 10 runs.');

        request()->attributes->set('user', $actor);
        $review = $results->review(2026, $match['id']);
        $official = $results->publish(2026, $match['id'], $review['version'], $review['draft_hash']);
        request()->attributes->remove('user');
        $this->get('/tournament')->assertOk()->assertSee('won by 10 runs.')->assertDontSee('PRIVATE-');
        $tableBefore = $this->get('/tournament?tab=table')->assertOk()->viewData('tableData');
        $this->assertSame(1, $tableBefore['groups'][0]['coverage']['published']);

        request()->attributes->set('user', $actor);
        $results->saveDraft(2026, $match['id'], $official->version, [...$input, 'team1_runs' => 120]);
        request()->attributes->remove('user');
        $this->get('/tournament')->assertOk()->assertSee('won by 10 runs.')->assertDontSee('won by 30 runs.');
        $this->assertSame($tableBefore, $this->get('/tournament?tab=table')->assertOk()->viewData('tableData'));

        request()->attributes->set('user', $actor);
        $review = $results->review(2026, $match['id']);
        $results->publish(2026, $match['id'], $review['version'], $review['draft_hash']);
        request()->attributes->remove('user');
        $this->get('/tournament')->assertOk()->assertSee('won by 30 runs.')->assertDontSee('won by 10 runs.');
        $this->assertNotSame($tableBefore, $this->get('/tournament?tab=table')->assertOk()->viewData('tableData'));
    }

    public function test_all_public_sections_remain_blocked_when_feature_is_switched_off(): void
    {
        $this->fixture();
        config(['tournament.enabled' => false]);
        foreach (['matches', 'table', 'bracket', 'stats'] as $tab) {
            $this->get('/tournament?tab='.$tab)->assertNotFound()->assertDontSee('PRIVATE-');
        }
    }

    public function test_forged_query_values_and_unsupported_verbs_cannot_change_records(): void
    {
        $match = $this->fixture();
        $before = $this->sourceState();
        $input = ['year' => 2027, 'winner_id' => $match['team1_id'], 'result_published_at' => now()->toIso8601String(),
            'is_scorer' => true, 'draft_result' => ['result_note' => 'PRIVATE-FORGED']];
        $this->get('/tournament?status=upcoming&'.http_build_query($input))->assertOk()->assertViewHas('year', 2026)
            ->assertDontSee('PRIVATE-FORGED')->assertSee('Scores appear after the result is published.');
        foreach (['post', 'put', 'patch', 'delete'] as $verb) {
            $this->{$verb}('/tournament', $input)->assertStatus(405);
        }
        $this->assertSame($before, $this->sourceState());
    }

    public static function invalidStoredStates(): array
    {
        return [['table', 'standings'], ['bracket', 'bracket']];
    }

    #[DataProvider('invalidStoredStates')]
    public function test_private_validation_failures_return_generic_public_errors(string $tab, string $method): void
    {
        $this->mock(PublicTournamentService::class)->shouldReceive($method)->once()->with(2026)
            ->andThrow(ValidationException::withMessages(['tournament' => 'PRIVATE-SCORER-VALIDATION']));
        $this->get('/tournament?tab='.$tab)->assertStatus(503)->assertSee('Service Unavailable')->assertDontSee('PRIVATE-');
    }

    public function test_rankings_and_public_errors_do_not_embed_full_stats_unlisted_players_or_account_tokens(): void
    {
        $this->fixture();
        $account = User::factory()->create(['email' => 'PRIVATE-ACCOUNT@example.test']);
        $token = (new JWTService)->generateToken($account);
        $names = [];
        foreach (range(1, 7) as $index) {
            $member = TeamMember::factory()->create(['name' => 'PRIVATE-REGISTERED-PLAYER-'.$index]);
            $names[] = 'Published player '.$index;
            DB::table('player_tournament_stats')->insert([
                'tournament_year' => 2026, 'team_id' => $member->team_id, 'team_member_id' => $member->id,
                'player_name' => $names[$index - 1], 'player_gender' => 'Male',
                'runs_scored' => 100 - $index, 'runs_scored_recorded_count' => 1,
                'highest_score' => 100 - $index,
                'balls_faced' => 987654321, 'balls_faced_recorded_count' => 1,
                'runs_conceded' => 987654322, 'runs_conceded_recorded_count' => 1,
                'dot_balls' => 987654323, 'dot_balls_recorded_count' => 1,
            ]);
        }
        $before = $this->sourceState();
        foreach (['matches', 'table', 'bracket', 'stats'] as $tab) {
            $response = $this->get('/tournament?tab='.$tab)->assertOk()->assertDontSee('PRIVATE-')->assertDontSee($token);
            foreach (['987654321', '987654322', '987654323', 'jwt_token', 'jwt_expires_at', 'captain_phone', 'wire:snapshot'] as $private) {
                $response->assertDontSee($private, false);
            }
        }
        $response = $this->get('/tournament?tab=stats&category=runs')->assertOk();
        $entries = $response->viewData('rankingData')['entries'];
        $this->assertSame(array_slice($names, 0, 5), array_column($entries, 'player_name'));
        $this->assertSame([99, 98, 97, 96, 95], array_column($entries, 'value'));
        foreach ($entries as $entry) {
            $this->assertSame(['rank', 'player_id', 'player_name', 'team_id', 'team_name', 'value', 'recorded_count', 'rate'], array_keys($entry));
            $this->assertNull($entry['rate']); // Legacy/incomplete inputs expose no invented rate.
        }
        foreach (array_slice($names, 5) as $unlisted) {
            $response->assertDontSee($unlisted);
            $this->assertStringNotContainsString($unlisted, json_encode($response->viewData('rankingData'), JSON_THROW_ON_ERROR));
        }
        foreach (['/tournament?tab=stats&category[]=PRIVATE-FILTER', '/tournament?stage[]=PRIVATE-FILTER', '/tournament?tab=PRIVATE-TAB'] as $url) {
            $response = $this->get($url);
            $this->assertContains($response->status(), [404, 422]);
            $response->assertDontSee('PRIVATE-')->assertDontSee($token)->assertDontSee('987654321');
        }
        $this->assertSame($before, $this->sourceState());
    }
}
