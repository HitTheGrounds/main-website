<?php

namespace Tests\Feature;

use App\Models\GroupTeam;
use App\Models\PlayerMatchStat;
use App\Models\PlayerTournamentStat;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TournamentMatch;
use App\Models\User;
use App\Services\Tournament\MatchResultService;
use App\Services\Tournament\MatchSchedulingService;
use App\Services\Tournament\PlayerEntryService;
use App\Services\Tournament\PlayerPublicationService;
use App\Services\Tournament\PlayerStatsService;
use App\Services\Tournament\QualificationService;
use App\Services\Tournament\TournamentGroupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class KnockoutScoringIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        config(['tournament.enabled' => true, 'tournament.writes_per_minute' => 1000]);
        $actor = User::factory()->create(['is_admin' => false, 'company_id' => null]);
        $actor->is_scorer = true;
        $actor->save();
        $this->actAs($actor);
        $groups = app(TournamentGroupService::class);
        $group = $groups->create(2026, 'Flexible group');
        $teams = Team::factory()->count(8)->create(['approved' => true]);
        foreach ($teams as $team) {
            $groups->assign(2026, $group->id, $group->fresh()->version, $team->id);
        }
        $qualification = app(QualificationService::class);
        $qualification->save(2026, $teams->pluck('id')->all(), $qualification->selection(2026)['revision']);

        return [$teams, $group->fresh(), $actor];
    }

    private function schedule(string $stage, int $slot, int $a, int $b): TournamentMatch
    {
        $service = app(MatchSchedulingService::class);
        $options = $service->knockoutOptions(2026, $stage);

        return $service->create(2026, 0, ['stage' => $stage, 'bracket_position' => $slot,
            'team1_id' => $a, 'team2_id' => $b, 'balls_per_over' => $stage === 'QF' ? 4 : 6], [], $options['revision']);
    }

    private function input(TournamentMatch $match, bool $reverse = false): array
    {
        return ['result_type' => 'win', 'result_method' => 'normal',
            'winner_id' => $reverse ? $match->team2_id : $match->team1_id, 'batting_first_id' => $match->team1_id,
            'team1_runs' => $reverse ? 90 : 100, 'team1_wickets' => 3, 'team1_overs' => 5, 'team1_balls' => 0,
            'team2_runs' => $reverse ? 100 : 90, 'team2_wickets' => 3, 'team2_overs' => 5, 'team2_balls' => 0];
    }

    private function publish(TournamentMatch $match, ?array $input = null): TournamentMatch
    {
        $service = app(MatchResultService::class);
        $service->saveDraft(2026, $match->id, $match->fresh()->version, $input ?? $this->input($match));
        $review = $service->review(2026, $match->id);

        return $service->publish(2026, $match->id, $review['version'], $review['draft_hash']);
    }

    private function rejected(callable $action): void
    {
        try {
            $action();
            $this->fail('Expected protected mutation rejection.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }

    private function player(TournamentMatch $match, TeamMember $member): TournamentMatch
    {
        app(PlayerStatsService::class)->saveDraft(2026, $match->id, $member->team_id, $member->id,
            ['draft' => null, 'published' => null], ['runs_scored' => 30, 'dot_balls' => 4]);
        $service = app(PlayerPublicationService::class);
        $review = $service->review(2026, $match->id);

        return $service->publish(2026, $match->id, $review['version'], $review['player_stats_revision'], $review['receipt']);
    }

    public function test_all_rounds_support_manual_pairings_results_and_player_totals_without_group_points(): void
    {
        [$teams] = $this->fixture();
        $standings = DB::table('group_teams')->orderBy('id')->get()->toJson();
        $member = TeamMember::factory()->create(['team_id' => $teams[6]->id]);
        $qfs = [];
        foreach (range(0, 3) as $i) {
            $qfs[] = $this->publish($this->schedule('QF', $i + 1, $teams[$i * 2]->id, $teams[$i * 2 + 1]->id));
        }
        $this->player($qfs[3], $member);
        // QF4 vs QF1, with reversed display positions: no predefined feeder map.
        $sf = $this->publish($this->schedule('SF', 2, $teams[6]->id, $teams[0]->id));
        $this->player($sf, $member);
        $other = $this->publish($this->schedule('SF', 1, $teams[2]->id, $teams[4]->id));
        $final = $this->publish($this->schedule('F', 1, $sf->winner_id, $other->winner_id));
        $this->player($final, $member);
        $total = PlayerTournamentStat::where('team_member_id', $member->id)->firstOrFail();
        $this->assertSame(90, $total->runs_scored);
        $this->assertSame(12, $total->dot_balls);
        $this->assertSame(30, $total->highest_score);
        $this->assertSame($standings, DB::table('group_teams')->orderBy('id')->get()->toJson());
        $this->assertDatabaseCount('matches', 7);
        $this->assertTrue(app(PlayerEntryService::class)->read(2026, $final->id)['eligible']);
    }

    public function test_creation_requires_a_current_round_snapshot_and_rechecks_slot_participation_and_eligibility(): void
    {
        [$teams] = $this->fixture();
        $service = app(MatchSchedulingService::class);
        $options = $service->knockoutOptions(2026, 'QF');
        $input = ['stage' => 'QF', 'bracket_position' => 1, 'team1_id' => $teams[0]->id, 'team2_id' => $teams[1]->id, 'balls_per_over' => 4];
        $this->rejected(fn () => $service->create(2026, 0, $input));
        $other = $this->schedule('QF', 4, $teams[6]->id, $teams[7]->id);
        $this->rejected(fn () => $service->create(2026, 0, $input, [], $options['revision']));
        $qf = $this->schedule('QF', 1, $teams[0]->id, $teams[1]->id);
        $this->rejected(fn () => $this->schedule('QF', 1, $teams[2]->id, $teams[3]->id));
        $this->rejected(fn () => $this->schedule('QF', 2, $teams[2]->id, $teams[0]->id));
        $this->rejected(fn () => $this->schedule('SF', 1, $qf->team1_id, $other->team1_id));
        GroupTeam::where('team_id', $teams[2]->id)->update(['qualified' => false]);
        $this->rejected(fn () => $this->schedule('QF', 2, $teams[2]->id, $teams[3]->id));
        $this->assertDatabaseCount('matches', 2);
    }

    public static function dependencies(): array
    {
        return [['QF', false], ['QF', true], ['SF', false], ['SF', true]];
    }

    private function source(string $stage): array
    {
        [$teams] = $this->fixture();
        $qfs = [];
        foreach (range(0, 3) as $i) {
            $qfs[] = $this->publish($this->schedule('QF', $i + 1, $teams[$i * 2]->id, $teams[$i * 2 + 1]->id));
        }
        if ($stage === 'QF') {
            return [$qfs[0], $qfs[1]->winner_id, 'SF'];
        }
        $sf = $this->publish($this->schedule('SF', 2, $qfs[0]->winner_id, $qfs[1]->winner_id));
        $other = $this->publish($this->schedule('SF', 1, $qfs[2]->winner_id, $qfs[3]->winner_id));

        return [$sf, $other->winner_id, 'F'];
    }

    #[DataProvider('dependencies')]
    public function test_corrected_winner_or_status_is_blocked_by_actual_later_fixture_but_same_winner_scores_are_allowed(string $stage, bool $published): void
    {
        [$source, $opponent, $next] = $this->source($stage);
        $service = app(MatchResultService::class);
        $service->saveDraft(2026, $source->id, $source->version, $this->input($source, true));
        $review = $service->review(2026, $source->id);
        // A new dependency after review must still be checked at publication.
        $dependent = $this->schedule($next, 1, $opponent, $source->winner_id);
        if ($published) {
            $dependent = $this->publish($dependent);
        }
        $before = DB::table('matches')->orderBy('id')->get()->toJson();
        $this->rejected(fn () => $service->publish(2026, $source->id, $review['version'], $review['draft_hash']));
        $this->rejected(fn () => app(MatchSchedulingService::class)->updateSchedule(2026, $source->id, $source->fresh()->version, ['status' => 'live']));
        $this->assertSame($before, DB::table('matches')->orderBy('id')->get()->toJson());
        $source = $this->publish($source, array_replace($this->input($source), ['team1_runs' => 101]));
        $this->assertSame(101, $source->team1_runs);
        $this->assertSame($dependent->getAttributes(), $dependent->fresh()->getAttributes());
    }

    public static function rounds(): array
    {
        return [['QF'], ['SF']];
    }

    #[DataProvider('rounds')]
    public function test_without_later_fixtures_winner_corrections_and_status_reversal_restore_only_official_data(string $stage): void
    {
        [$source] = $this->source($stage);
        $source = $this->publish($source, $this->input($source, true));
        $this->assertSame($source->team2_id, $source->winner_id);
        $member = TeamMember::factory()->create(['team_id' => $source->team1_id]);
        $source = $this->player($source, $member);
        app(MatchResultService::class)->saveDraft(2026, $source->id, $source->version, ['team1_runs' => 0]);
        $source = $source->fresh();
        $draft = $source->draft_result;
        $rows = PlayerMatchStat::orderBy('id')->get()->map->getAttributes()->all();
        $service = app(MatchSchedulingService::class);
        $source = $service->updateSchedule(2026, $source->id, $source->version, ['status' => 'live']);
        $this->assertDatabaseCount('player_tournament_stats', 0);
        $this->rejected(fn () => app(PlayerPublicationService::class)->review(2026, $source->id));
        $source = $service->updateSchedule(2026, $source->id, $source->version, ['status' => 'finished']);
        $this->assertSame(30, PlayerTournamentStat::firstOrFail()->runs_scored);
        $this->assertSame($draft, $source->draft_result);
        $this->assertSame($rows, PlayerMatchStat::orderBy('id')->get()->map->getAttributes()->all());
        $this->assertNotNull($source->structure_locked_at);
        $this->assertSame($source->team2_id, $source->winner_id);
    }

    public function test_failed_status_summary_write_rolls_back_status_metadata_and_sources(): void
    {
        [$teams] = $this->fixture();
        $match = $this->publish($this->schedule('QF', 1, $teams[0]->id, $teams[1]->id));
        $member = TeamMember::factory()->create(['team_id' => $teams[0]->id]);
        $match = $this->player($match, $member);
        $tables = ['matches', 'group_teams', 'player_match_stats', 'player_tournament_stats'];
        $before = [];
        foreach ($tables as $table) {
            $before[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }
        $event = 'eloquent.deleted: '.PlayerTournamentStat::class;
        Event::listen($event, fn () => throw new RuntimeException('Synthetic summary failure'));
        try {
            app(MatchSchedulingService::class)->updateSchedule(2026, $match->id, $match->version, ['status' => 'upcoming', 'venue' => 'Must roll back']);
            $this->fail('Expected atomic rollback.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic summary failure', $exception->getMessage());
        } finally {
            Event::forget($event);
        }
        foreach ($tables as $table) {
            $this->assertSame($before[$table], DB::table($table)->orderBy('id')->get()->toJson(), $table);
        }
    }

    public function test_result_publication_rechecks_approval_and_finishing_requires_a_valid_official_result(): void
    {
        [$teams] = $this->fixture();
        $match = $this->schedule('QF', 1, $teams[0]->id, $teams[1]->id);
        $service = app(MatchSchedulingService::class);
        $this->rejected(fn () => $service->updateSchedule(2026, $match->id, 1, ['status' => 'finished']));
        $results = app(MatchResultService::class);
        $results->saveDraft(2026, $match->id, 1, $this->input($match));
        $review = $results->review(2026, $match->id);
        Team::whereKey($teams[0]->id)->update(['approved' => false]);
        $this->rejected(fn () => $results->publish(2026, $match->id, $review['version'], $review['draft_hash']));
        $this->assertNull($match->fresh()->result_published_at);
        Team::whereKey($teams[0]->id)->update(['approved' => true]);
        $match = $results->publish(2026, $match->id, $review['version'], $review['draft_hash']);
        $match = $service->updateSchedule(2026, $match->id, $match->version, ['status' => 'live']);
        DB::table('matches')->where('id', $match->id)->update(['winner_id' => null]);
        $this->rejected(fn () => $service->updateSchedule(2026, $match->id, $match->version, ['status' => 'finished']));
        $this->assertSame('live', $match->fresh()->status);
    }

    public static function deniedActors(): array
    {
        return [['guest', 403], ['revoked', 403], ['admin', 403], ['disabled', 404]];
    }

    #[DataProvider('deniedActors')]
    public function test_knockout_paths_keep_existing_feature_and_current_scorer_checks(string $mode, int $status): void
    {
        [$teams, , $actor] = $this->fixture();
        $service = app(MatchSchedulingService::class);
        $options = $service->knockoutOptions(2026, 'QF');
        $match = $this->publish($this->schedule('QF', 1, $teams[0]->id, $teams[1]->id));
        $member = TeamMember::factory()->create(['team_id' => $teams[0]->id]);
        if ($mode === 'guest') {
            request()->attributes->remove('user');
        } elseif ($mode === 'disabled') {
            config(['tournament.enabled' => false]);
        } else {
            // Keep the request's scorer snapshot: services must use fresh DB permission.
            DB::table('users')->where('id', $actor->id)->update(['is_scorer' => false, 'is_admin' => $mode === 'admin']);
        }
        $actions = [
            fn () => $service->knockoutOptions(2026, 'QF'),
            fn () => $service->create(2026, 0, ['stage' => 'QF', 'bracket_position' => 2,
                'team1_id' => $teams[2]->id, 'team2_id' => $teams[3]->id, 'balls_per_over' => 4], [], $options['revision']),
            fn () => $service->updateSchedule(2026, $match->id, $match->version, ['status' => 'live']),
            fn () => app(MatchResultService::class)->saveDraft(2026, $match->id, $match->version, ['team1_runs' => 0]),
            fn () => app(PlayerStatsService::class)->saveDraft(2026, $match->id, $member->team_id, $member->id,
                ['draft' => null, 'published' => null], ['runs_scored' => 0]),
            fn () => app(PlayerPublicationService::class)->review(2026, $match->id),
            fn () => app(PlayerEntryService::class)->read(2026, $match->id),
        ];
        $before = DB::table('matches')->get()->toJson();
        foreach ($actions as $action) {
            try {
                $action();
                $this->fail('Expected scorer boundary rejection.');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                $this->assertSame($status, $exception->getStatusCode());
            }
        }
        $this->assertSame($before, DB::table('matches')->get()->toJson());
        $this->assertDatabaseCount('player_match_stats', 0);
    }

    public function test_group_correction_and_reopening_keep_confirmed_qualifiers_and_existing_quarters(): void
    {
        [$teams, $group] = $this->fixture();
        $qf = $this->schedule('QF', 1, $teams[0]->id, $teams[1]->id);
        $match = app(MatchSchedulingService::class)->create(2026, $group->version,
            ['stage' => 'G', 'group_id' => $group->id, 'team1_id' => $teams[0]->id, 'team2_id' => $teams[1]->id, 'balls_per_over' => 4]);
        $match = $this->publish($match);
        $match = $this->publish($match, $this->input($match, true));
        $this->assertSame(2, GroupTeam::where('team_id', $match->team2_id)->firstOrFail()->points);
        $match = app(MatchSchedulingService::class)->updateSchedule(2026, $match->id, $match->version, ['status' => 'live']);
        $this->assertSame(0, GroupTeam::where('team_id', $match->team2_id)->firstOrFail()->points);
        $this->assertSame(8, GroupTeam::where('qualified', true)->count());
        $this->assertSame($qf->getAttributes(), $qf->fresh()->getAttributes());
    }

    public static function unfinishedOutcomes(): array
    {
        return [['tie'], ['abandoned'], ['no_result']];
    }

    #[DataProvider('unfinishedOutcomes')]
    public function test_knockout_corrections_cannot_publish_an_undecided_outcome(string $type): void
    {
        [$teams] = $this->fixture();
        $match = $this->publish($this->schedule('QF', 1, $teams[0]->id, $teams[1]->id));
        $service = app(MatchResultService::class);
        $service->saveDraft(2026, $match->id, $match->version, ['result_type' => $type]);
        $before = $match->fresh()->getAttributes();
        $this->rejected(fn () => $service->review(2026, $match->id));
        $this->assertSame($before, $match->fresh()->getAttributes());
    }

    public function test_super_over_result_counts_only_saved_normal_innings_and_preserves_player_review_on_failure(): void
    {
        [$teams] = $this->fixture();
        $match = $this->schedule('QF', 1, $teams[0]->id, $teams[1]->id);
        $match = $this->publish($match, array_replace($this->input($match), [
            'result_method' => 'super_over', 'team2_runs' => 100, 'result_note' => 'Official Super Over decision',
        ]));
        $member = TeamMember::factory()->create(['team_id' => $teams[0]->id]);
        $match = $this->player($match, $member);
        $this->assertSame(30, PlayerTournamentStat::firstOrFail()->runs_scored);
        $official = PlayerMatchStat::where('record_state', 'published')->firstOrFail();
        app(PlayerStatsService::class)->saveDraft(2026, $match->id, $member->team_id, $member->id,
            ['draft' => null, 'published' => ['id' => $official->id, 'version' => $official->version]], ['runs_scored' => 40]);
        $publisher = app(PlayerPublicationService::class);
        $review = $publisher->review(2026, $match->id);
        $event = 'eloquent.updated: '.PlayerTournamentStat::class;
        Event::listen($event, fn () => throw new RuntimeException('Synthetic player summary failure'));
        try {
            $publisher->publish(2026, $match->id, $review['version'], $review['player_stats_revision'], $review['receipt']);
            $this->fail('Expected atomic player rollback.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic player summary failure', $exception->getMessage());
        } finally {
            Event::forget($event);
        }
        $this->assertSame(30, $official->fresh()->runs_scored);
        $this->assertSame(30, PlayerTournamentStat::firstOrFail()->runs_scored);
        $this->assertSame(40, PlayerMatchStat::where('record_state', 'draft')->firstOrFail()->runs_scored);
        $publisher->publish(2026, $match->id, $review['version'], $review['player_stats_revision'], $review['receipt']);
        $this->assertSame(40, PlayerTournamentStat::firstOrFail()->runs_scored);
    }
}
