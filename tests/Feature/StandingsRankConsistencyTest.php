<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use App\Services\JWTService;
use App\Services\Tournament\PublicTournamentService;
use App\Services\Tournament\QualificationService;
use App\Services\Tournament\ScorerStandingsService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StandingsRankConsistencyTest extends TestCase
{
    use DatabaseMigrations;

    public static function records(): array
    {
        return [
            'known tie' => [[4, 4, 2], ['1.250000', '1.250000', '0.000000'], [1, 1, 3]],
            'unknown tie' => [[0, 0, 0], [null, null, null], [1, 1, 1]],
            'known ahead of unknown' => [[2, 2, 2], [null, '-1.000000', '-1.000000'], [1, 1, 3]],
            'six decimal precision' => [[2, 2, 2], ['0.000001', '0.000002', '0.000001'], [1, 2, 2]],
        ];
    }

    #[DataProvider('records')]
    public function test_public_qualification_and_scorer_render_the_same_sporting_ranks(array $points, array $nrr, array $expected): void
    {
        config(['tournament.enabled' => true, 'tournament.year' => 2026]);
        Route::middleware('web')->group(base_path('routes/tournament.php'));
        Route::getRoutes()->refreshNameLookups();
        $actor = User::factory()->create(['is_admin' => false, 'company_id' => null]);
        $actor->is_scorer = true;
        $actor->save();
        request()->attributes->set('user', $actor);
        Livewire::withCookie('company_token', (new JWTService)->generateToken($actor));
        $group = DB::table('tournament_groups')->insertGetId(['name' => 'A', 'tournament_year' => 2026]);
        foreach (Team::factory()->count(3)->create(['approved' => true]) as $i => $team) {
            DB::table('group_teams')->insert(['group_id' => $group, 'tournament_year' => 2026, 'team_id' => $team->id,
                'points' => $points[$i], 'nrr' => $nrr[$i]]);
        }
        $public = app(PublicTournamentService::class)->standings(2026)['groups'][0]['teams'];
        $scorer = app(ScorerStandingsService::class)->listing(2026)[0]['teams'];
        $qualification = app(QualificationService::class)->selection(2026)['groups'][0]['teams'];
        foreach ([$public, $scorer, $qualification] as $rows) {
            $this->assertSame($expected, array_column($rows, 'rank'));
            $this->assertSame(array_column($public, 'id'), array_column($rows, 'id'));
        }
        preg_match_all('/class="sst-rank">(\d+)</', Volt::test('scorer.standings-manager')->html(), $ranks);
        $this->assertSame(array_map('strval', $expected), $ranks[1]);
    }
}
