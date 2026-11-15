<?php

namespace Tests\Feature;

use App\Models\PlayerMatchStat;
use App\Models\PlayerTournamentStat;
use App\Models\Team;
use App\Models\TeamMember;
use App\Services\Tournament\PlayerTotalsService;
use App\Services\Tournament\TournamentWriteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class PlayerTotalsTest extends TestCase
{
    use RefreshDatabase;

    private function player(): TeamMember
    {
        return TeamMember::factory()->create(['team_id' => Team::factory()->create(['approved' => true])->id, 'name' => 'Registered name', 'gender' => 'Female']);
    }

    private function source(TeamMember $member, array $figures, array $changes = [], int $year = 2026, string $stage = 'G'): PlayerMatchStat
    {
        $opponent = Team::factory()->create(['approved' => true]);
        $group = null;
        if ($stage === 'G') {
            $group = DB::table('tournament_groups')->where('tournament_year', $year)->value('id');
            $group ??= DB::table('tournament_groups')->insertGetId(['tournament_year' => $year, 'name' => 'A']);
            foreach ([(int) $member->team_id, $opponent->id] as $teamId) {
                if (! DB::table('group_teams')->where('tournament_year', $year)->where('team_id', $teamId)->exists()) {
                    DB::table('group_teams')->insert(['tournament_year' => $year, 'group_id' => $group, 'team_id' => $teamId]);
                }
            }
        }
        $match = DB::table('matches')->insertGetId(['tournament_year' => $year, 'stage' => $stage, 'group_id' => $group,
            'bracket_position' => $stage === 'G' ? null : 1, 'team1_id' => $member->team_id, 'team2_id' => $opponent->id,
            'balls_per_over' => 4, 'status' => 'finished', 'result_type' => 'win', 'result_method' => 'normal',
            'winner_id' => $member->team_id, 'result_published_at' => now()]);
        $id = DB::table('player_match_stats')->insertGetId(array_replace(['match_id' => $match, 'team_id' => $member->team_id,
            'team_member_id' => $member->id, 'player_name' => 'Historical name', 'player_gender' => 'Female',
            'record_state' => 'published', 'pending_action' => null, 'published_at' => now()], $figures, $changes));

        return PlayerMatchStat::findOrFail($id);
    }

    private function rebuild(int $year, array $ids): void
    {
        app(TournamentWriteService::class)->run($year, fn () => app(PlayerTotalsService::class)->recalculate($year, $ids));
    }

    private function summary(TeamMember $member, int $year = 2026): PlayerTournamentStat
    {
        return PlayerTournamentStat::where('tournament_year', $year)->where('team_member_id', $member->id)->firstOrFail();
    }

    public function test_totals_preserve_per_metric_coverage_zero_unknown_and_independent_coverage(): void
    {
        $member = $this->player();
        $this->source($member, ['runs_scored' => 0, 'bowling_balls' => 0]);
        $this->source($member, ['dot_balls' => 8, 'fours' => 2]);
        $this->source($member, ['runs_scored' => 20, 'sixes' => 1]);
        $this->rebuild(2026, [$member->id]);
        $row = $this->summary($member);
        $this->assertSame(20, $row->runs_scored);
        $this->assertSame(2, $row->runs_scored_recorded_count);
        $this->assertSame(0, $row->bowling_balls);
        $this->assertSame(1, $row->bowling_balls_recorded_count);
        $this->assertSame(8, $row->dot_balls);
        $this->assertSame(1, $row->dot_balls_recorded_count);
        $this->assertNull($row->balls_faced);
        $this->assertSame(0, $row->balls_faced_recorded_count);
        $this->assertSame(20, $row->highest_score);
        $this->assertSame('Historical name', $row->player_name);
        $this->assertSame('Female', $row->player_gender);
    }

    public function test_corrections_withdrawals_and_match_eligibility_recalculate_the_best(): void
    {
        $member = $this->player();
        $a = $this->source($member, ['runs_scored' => 50]);
        $b = $this->source($member, ['runs_scored' => 30]);
        $this->rebuild(2026, [$member->id]);
        $this->assertSame($a->match_id, $this->summary($member)->highest_score_match_id);
        DB::table('player_match_stats')->where('id', $a->id)->update(['runs_scored' => 10]);
        $this->rebuild(2026, [$member->id]);
        $this->assertSame(40, $this->summary($member)->runs_scored);
        $this->assertSame(30, $this->summary($member)->highest_score);
        $this->assertSame($b->match_id, $this->summary($member)->highest_score_match_id);
        $b->delete(); // Simulate the future publisher's withdrawal inside feature data.
        $this->rebuild(2026, [$member->id]);
        $this->assertSame(10, $this->summary($member)->highest_score);
        DB::table('matches')->where('id', $a->match_id)->update(['status' => 'live']);
        $this->rebuild(2026, [$member->id]);
        $this->assertSame(0, PlayerTournamentStat::where('team_member_id', $member->id)->count());
        $this->assertNotNull($a->fresh());
        DB::table('matches')->where('id', $a->match_id)->update(['status' => 'finished']);
        $this->rebuild(2026, [$member->id]);
        $this->assertSame(10, $this->summary($member)->runs_scored);
    }

    public static function excludedMatches(): array
    {
        return [[['status' => 'upcoming']], [['status' => 'live']], [['result_published_at' => null]],
            [['result_type' => 'abandoned', 'result_method' => null, 'winner_id' => null]],
            [['result_type' => 'no_result', 'result_method' => null, 'winner_id' => null]]];
    }

    #[DataProvider('excludedMatches')]
    public function test_ineligible_matches_cannot_contribute(array $changes): void
    {
        $member = $this->player();
        $source = $this->source($member, ['runs_scored' => 99]);
        DB::table('matches')->where('id', $source->match_id)->update($changes);
        $this->rebuild(2026, [$member->id]);
        $this->assertDatabaseCount('player_tournament_stats', 0);
    }

    public function test_all_stages_count_normal_figures_from_super_over_decisions_and_ignore_drafts(): void
    {
        $member = $this->player();
        foreach (['G', 'QF', 'SF', 'F'] as $stage) {
            $source = $this->source($member, ['runs_scored' => 10, 'wickets_taken' => 1], [], 2026, $stage);
            DB::table('matches')->where('id', $source->match_id)->update(['result_method' => 'super_over']);
            $draft = $source->getAttributes();
            unset($draft['id']);
            DB::table('player_match_stats')->insert(array_replace($draft, ['record_state' => 'draft', 'pending_action' => 'replace',
                'published_at' => null, 'runs_scored' => 999]));
        }
        $this->rebuild(2026, [$member->id]);
        $this->assertSame(40, $this->summary($member)->runs_scored);
        $this->assertSame(4, $this->summary($member)->wickets_taken);
        $this->assertSame(4, $this->summary($member)->runs_scored_recorded_count);
    }

    public function test_other_years_players_and_source_registration_records_are_preserved(): void
    {
        $member = $this->player();
        $source = $this->source($member, ['runs_scored' => 10]);
        $this->source($member, ['runs_scored' => 90], [], 2027);
        $other = $this->player();
        $this->source($other, ['runs_scored' => 55]);
        $before = PlayerMatchStat::orderBy('id')->get()->map->getAttributes()->all();
        $registration = $member->fresh()->getAttributes();
        $this->rebuild(2027, [$member->id]);
        $this->rebuild(2026, [$other->id]);
        $otherBefore = $this->summary($other)->getAttributes();
        $this->rebuild(2026, [$member->id]);
        $this->assertSame(10, $this->summary($member)->runs_scored);
        $this->assertSame(90, $this->summary($member, 2027)->runs_scored);
        $this->assertSame($otherBefore, $this->summary($other)->getAttributes());
        $this->assertSame($before, PlayerMatchStat::orderBy('id')->get()->map->getAttributes()->all());
        $this->assertSame($registration, $member->fresh()->getAttributes());
        $this->assertSame($source->match_id, $this->summary($member)->highest_score_match_id);
    }

    public function test_gender_conflicts_fail_without_overwriting_existing_summary(): void
    {
        $member = $this->player();
        $this->source($member, ['runs_scored' => 10]);
        $this->rebuild(2026, [$member->id]);
        $before = $this->summary($member)->getAttributes();
        $this->source($member, ['runs_scored' => 20], ['player_gender' => 'Male']);
        try {
            $this->rebuild(2026, [$member->id]);
            $this->fail('Expected conflicting historical gender rejection.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('player', $exception->errors());
        }
        $this->assertSame($before, $this->summary($member)->getAttributes());
    }

    public function test_conflicting_team_snapshots_cannot_move_a_player_between_teams_in_the_summary(): void
    {
        $member = $this->player();
        $this->source($member, ['runs_scored' => 10]);
        $this->rebuild(2026, [$member->id]);
        $before = $this->summary($member)->getAttributes();
        $other = $this->source($member, ['runs_scored' => 20]);
        $opponent = DB::table('matches')->where('id', $other->match_id)->value('team2_id');
        DB::table('player_match_stats')->where('id', $other->id)->update(['team_id' => $opponent]);
        try {
            $this->rebuild(2026, [$member->id]);
            $this->fail('Expected conflicting team rejection.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('player', $exception->errors());
        }
        $this->assertSame($before, $this->summary($member)->getAttributes());
    }

    public function test_repeat_rebuild_is_stable_and_zero_is_a_real_highest_score(): void
    {
        $member = $this->player();
        $first = $this->source($member, ['runs_scored' => 0]);
        $this->source($member, ['runs_scored' => 0]);
        $this->rebuild(2026, [$member->id]);
        $before = $this->summary($member)->getAttributes();
        $this->assertSame(0, $this->summary($member)->highest_score);
        $this->assertSame($first->match_id, $this->summary($member)->highest_score_match_id);
        $this->rebuild(2026, [$member->id]);
        $this->assertSame($before, $this->summary($member)->getAttributes());
    }

    public function test_all_unknown_eligible_figures_keep_unknown_totals_without_invented_appearances(): void
    {
        $member = $this->player();
        $this->source($member, []);
        $this->rebuild(2026, [$member->id]);
        $row = $this->summary($member);
        $this->assertNull($row->highest_score);
        $this->assertNull($row->highest_score_match_id);
        foreach (\App\Services\Tournament\PlayerStatsValidator::METRICS as $metric) {
            $this->assertNull($row->$metric);
            $this->assertSame(0, $row->{$metric.'_recorded_count'});
        }
    }

    public function test_invalid_requested_players_do_not_change_existing_summaries(): void
    {
        $member = $this->player();
        $this->source($member, ['runs_scored' => 10]);
        $this->rebuild(2026, [$member->id]);
        $before = $this->summary($member)->getAttributes();
        foreach ([[0], ['invalid'], [$member->id, $member->id]] as $ids) {
            try {
                $this->rebuild(2026, $ids);
                $this->fail('Expected invalid requested player rejection.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
            $this->assertSame($before, $this->summary($member)->getAttributes());
        }
    }

    public function test_bigint_totals_can_exceed_one_innings_integer_range(): void
    {
        $member = $this->player();
        $this->source($member, ['runs_scored' => 2147483647]);
        $this->source($member, ['runs_scored' => 2147483647]);
        $this->rebuild(2026, [$member->id]);
        $this->assertSame(4294967294, $this->summary($member)->runs_scored);
    }

    public function test_failure_after_summary_write_rolls_back_all_derived_rows(): void
    {
        $a = $this->player();
        $b = $this->player();
        $this->source($a, ['runs_scored' => 1]);
        $this->source($b, ['runs_scored' => 2]);
        $event = 'eloquent.created: '.PlayerTournamentStat::class;
        Event::listen($event, fn () => throw new RuntimeException('Synthetic summary write failure'));
        try {
            $this->rebuild(2026, [$a->id, $b->id]);
            $this->fail('Expected transaction rollback.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic summary write failure', $exception->getMessage());
        } finally {
            Event::forget($event);
        }
        $this->assertDatabaseCount('player_tournament_stats', 0);
        $this->assertDatabaseCount('player_match_stats', 2);
    }
}
