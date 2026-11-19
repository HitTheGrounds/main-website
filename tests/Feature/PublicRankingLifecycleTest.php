<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\TeamMember;
use App\Services\Tournament\PlayerTotalsService;
use App\Services\Tournament\PublicTournamentService;
use App\Services\Tournament\TournamentWriteService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicRankingLifecycleTest extends TestCase
{
    use DatabaseMigrations;

    private function source(TeamMember $member, int $runs, array $changes = []): int
    {
        $opponent = Team::factory()->create(['approved' => true]);
        $group = DB::table('tournament_groups')->insertGetId(['name' => 'Pool '.$opponent->id, 'tournament_year' => 2026]);
        $match = DB::table('matches')->insertGetId(array_replace(['tournament_year' => 2026, 'stage' => 'G', 'group_id' => $group,
            'team1_id' => $member->team_id, 'team2_id' => $opponent->id, 'balls_per_over' => 4,
            'status' => 'finished', 'result_type' => 'win', 'result_method' => 'normal',
            'winner_id' => $member->team_id, 'result_published_at' => now()], $changes));

        return DB::table('player_match_stats')->insertGetId(['match_id' => $match, 'team_id' => $member->team_id,
            'team_member_id' => $member->id, 'player_name' => 'Published '.$member->id, 'player_gender' => $member->gender,
            'record_state' => 'published', 'pending_action' => null, 'published_at' => now(), 'runs_scored' => $runs]);
    }

    private function recalculate(array $ids): void
    {
        app(TournamentWriteService::class)->run(2026, fn () => app(PlayerTotalsService::class)->recalculate(2026, $ids));
    }

    public function test_corrections_and_removal_replace_totals_and_best_innings_without_draft_duplicates(): void
    {
        config(['tournament.enabled' => true]);
        $member = TeamMember::factory()->create(['gender' => 'Female']);
        $first = $this->source($member, 70);
        $best = $this->source($member, 90);
        $reference = DB::table('player_match_stats')->where('id', $best)->first();
        DB::table('player_match_stats')->insert(['match_id' => $reference->match_id, 'team_id' => $member->team_id,
            'team_member_id' => $member->id, 'player_name' => 'PRIVATE-DRAFT', 'player_gender' => 'Female', 'runs_scored' => 999]);
        $this->recalculate([$member->id]);
        $public = app(PublicTournamentService::class);
        $runs = $public->rankings(2026, ['gender' => 'Female']);
        $this->assertCount(1, $runs['entries']);
        $this->assertSame(160, $runs['entries'][0]['value']);
        $this->assertSame(2, $runs['entries'][0]['recorded_count']);
        $this->assertSame(90, $public->rankings(2026, ['category' => 'highest_innings'])['entries'][0]['value']);
        $this->assertStringNotContainsString('PRIVATE-', json_encode($runs));

        // Controlled source corrections exercise the internal totals-to-ranking boundary.
        // Real scorer save/publish/withdraw HTTP behavior is covered separately.
        DB::table('player_match_stats')->where('id', $best)->update(['runs_scored' => 50]);
        $this->recalculate([$member->id]);
        $this->assertSame(120, $public->rankings(2026)['entries'][0]['value']);
        $this->assertSame(70, $public->rankings(2026, ['category' => 'highest_innings'])['entries'][0]['value']);
        DB::table('player_match_stats')->where('id', $first)->delete();
        $this->recalculate([$member->id]);
        $this->assertSame(50, $public->rankings(2026, ['category' => 'highest_innings'])['entries'][0]['value']);
        DB::table('player_match_stats')->where('id', $best)->delete();
        $this->recalculate([$member->id]);
        $this->assertSame([], $public->rankings(2026)['entries']);
        $this->assertDatabaseHas('player_match_stats', ['team_member_id' => $member->id, 'record_state' => 'draft', 'runs_scored' => 999]);
    }

    public function test_only_normal_innings_figures_from_eligible_results_contribute(): void
    {
        config(['tournament.enabled' => true]);
        $member = TeamMember::factory()->create(['gender' => 'Male']);
        $this->source($member, 20, ['result_method' => 'super_over']);
        $this->source($member, 10, ['result_type' => 'tie']);
        $this->source($member, 500, ['result_type' => 'no_result']);
        $this->source($member, 500, ['result_type' => 'abandoned']);
        $this->source($member, 500, ['status' => 'live']);
        $this->source($member, 500, ['result_published_at' => null]);
        $this->recalculate([$member->id]);
        $result = app(PublicTournamentService::class)->rankings(2026);
        $this->assertSame(30, $result['entries'][0]['value']);
        $this->assertSame(2, $result['entries'][0]['recorded_count']);
        $this->assertSame(20, app(PublicTournamentService::class)->rankings(2026, ['category' => 'highest_innings'])['entries'][0]['value']);
        // Source rows contain normal-innings figures only. A Super Over decision
        // keeps those figures; the website has no Super Over figures to aggregate.
    }
}
