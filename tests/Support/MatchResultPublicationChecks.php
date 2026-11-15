<?php

namespace Tests\Support;

use App\Models\Company;
use App\Models\GroupTeam;
use App\Models\PlayerMatchStat;
use App\Models\PlayerTournamentStat;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TournamentGroup;
use App\Models\TournamentMatch;
use App\Models\User;
use App\Services\Tournament\GroupStandingsCalculator;
use App\Services\Tournament\MatchResultService;
use App\Services\Tournament\MatchSchedulingService;
use App\Services\Tournament\PlayerPublicationService;
use App\Services\Tournament\PlayerStatsService;
use App\Services\Tournament\TournamentGroupService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

trait MatchResultPublicationChecks
{
    private User $resultActor;

    private function resultFixture(int $count = 2): TournamentMatch
    {
        config(['tournament.enabled' => true, 'tournament.writes_per_minute' => 1000]);
        $this->resultActor = User::factory()->create(['is_admin' => false, 'company_id' => null]);
        $this->resultActor->is_scorer = true;
        $this->resultActor->save();
        request()->attributes->set('user', $this->resultActor);
        $groups = app(TournamentGroupService::class);
        $group = $groups->create(2026, 'A');
        $teams = Team::factory()->count($count)->create(['approved' => true]);
        foreach ($teams as $index => $team) {
            $team->team_name = 'Side '.($index + 1);
            $team->save();
            $groups->assign(2026, $group->id, $group->fresh()->version, $team->id);
        }

        return app(MatchSchedulingService::class)->create(2026, $group->fresh()->version, [
            'stage' => 'G', 'group_id' => $group->id, 'team1_id' => $teams[0]->id,
            'team2_id' => $teams[1]->id, 'balls_per_over' => 4,
        ]);
    }

    private function resultInput(TournamentMatch $match): array
    {
        return ['result_type' => 'win', 'result_method' => 'normal', 'winner_id' => $match->team1_id,
            'batting_first_id' => $match->team1_id, 'team1_runs' => 100, 'team1_wickets' => 3,
            'team1_overs' => 5, 'team1_balls' => 0, 'team2_runs' => 90, 'team2_wickets' => 11,
            'team2_overs' => 3, 'team2_balls' => 0];
    }

    private function resultService(): MatchResultService
    {
        return app(MatchResultService::class);
    }

    private function resultRejected(callable $action, string $field = 'tournament'): void
    {
        try {
            $action();
            $this->fail('Expected protected result action rejection.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    private function reviewedPublish(TournamentMatch $match, array $input): TournamentMatch
    {
        $this->resultService()->saveDraft(2026, $match->id, $match->fresh()->version, $input);
        $review = $this->resultService()->review(2026, $match->id);

        return $this->resultService()->publish(2026, $match->id, $review['version'], $review['draft_hash']);
    }

    public function test_partial_draft_and_review_do_not_change_official_results_or_standings(): void
    {
        $match = $this->resultFixture();
        $before = GroupTeam::orderBy('id')->get()->map->getAttributes()->all();
        $service = $this->resultService();
        $draft = $service->saveDraft(2026, $match->id, 1, ['team1_runs' => '0', 'team1_overs' => '2']);
        $this->assertSame(['team1_runs' => 0, 'team1_overs' => 2], $draft->draft_result);
        $this->assertSame(2, $draft->version);
        $this->assertNull($draft->team1_runs);
        $this->assertSame('upcoming', $draft->status);
        $this->resultRejected(fn () => $service->review(2026, $match->id), 'result_type');
        $this->assertSame($before, GroupTeam::orderBy('id')->get()->map->getAttributes()->all());
        $this->assertSame($draft->draft_result, $service->editor(2026, $match->id)['form']);
        $budget = RateLimiter::attempts('tournament:scorer-write:'.$this->resultActor->id);
        $draft = $service->saveDraft(2026, $match->id, 2, $this->resultInput($match));
        $beforeReview = $draft->getAttributes();
        $beforeBudget = RateLimiter::attempts('tournament:scorer-write:'.$this->resultActor->id);
        $review = $service->review(2026, $match->id);
        $this->assertSame('Side 1 won by 10 runs.', $review['description']);
        $this->assertSame(20, $review['result']['team1_deliveries']);
        $this->assertSame($beforeReview, $match->fresh()->getAttributes());
        $this->assertSame($beforeBudget, RateLimiter::attempts('tournament:scorer-write:'.$this->resultActor->id));
        $this->assertGreaterThan($budget, $beforeBudget);
    }

    public function test_publication_is_atomic_updates_standings_and_locks_structure_without_changing_existing_accounts(): void
    {
        $match = $this->resultFixture();
        $accounts = DB::table('users')->orderBy('id')->get()->toJson();
        $teams = DB::table('teams')->orderBy('id')->get()->toJson();
        $assignment = GroupTeam::where('team_id', $match->team1_id)->firstOrFail();
        $assignment->point_adjustments = [['points' => -1, 'reason' => 'Committee penalty', 'actor' => $this->resultActor->id]];
        $assignment->qualified = true;
        $assignment->save();
        $adjustmentHistory = $assignment->fresh()->point_adjustments;
        $published = $this->reviewedPublish($match, $this->resultInput($match));
        $this->assertSame('finished', $published->status);
        $this->assertSame(3, $published->version);
        $this->assertNotNull($published->result_published_at);
        $this->assertNotNull($published->structure_locked_at);
        $this->assertSame($this->resultActor->id, $published->published_by);
        $this->assertSame($match->created_by, $published->created_by);
        $this->assertNull($published->draft_result);
        $this->assertSame(12, $published->team2_deliveries);
        $this->assertSame(1, $assignment->fresh()->played);
        $this->assertSame(2, $assignment->fresh()->base_points);
        $this->assertSame(1, $assignment->fresh()->points);
        $this->assertSame('2.000000', $assignment->fresh()->nrr);
        $this->assertSame('5.00000000', $assignment->fresh()->nrr_overs_bowled);
        $this->assertTrue($assignment->fresh()->qualified);
        $this->assertSame($adjustmentHistory, $assignment->fresh()->point_adjustments);
        $this->assertSame($accounts, DB::table('users')->orderBy('id')->get()->toJson());
        $this->assertSame($teams, DB::table('teams')->orderBy('id')->get()->toJson());
    }

    public function test_corrections_keep_official_values_until_publication_and_rebuild_instead_of_adding_points(): void
    {
        $match = $this->resultFixture();
        $published = $this->reviewedPublish($match, $this->resultInput($match));
        $firstLock = $published->structure_locked_at;
        $input = $this->resultInput($match);
        $input['winner_id'] = $match->team2_id;
        $input['team2_runs'] = 101;
        $input['team2_wickets'] = 5;
        $draft = $this->resultService()->saveDraft(2026, $match->id, $published->version, $input);
        $this->assertSame(90, $draft->team2_runs);
        $this->assertSame($match->team1_id, $draft->winner_id);
        $this->assertSame(2, GroupTeam::where('team_id', $match->team1_id)->firstOrFail()->points);
        $review = $this->resultService()->review(2026, $match->id);
        $this->assertSame('Side 2 won by 6 wickets.', $review['description']);
        $corrected = $this->resultService()->publish(2026, $match->id, $review['version'], $review['draft_hash']);
        $this->assertSame(101, $corrected->team2_runs);
        $this->assertEquals($firstLock, $corrected->structure_locked_at);
        $this->assertSame(0, GroupTeam::where('team_id', $match->team1_id)->firstOrFail()->points);
        $this->assertSame(2, GroupTeam::where('team_id', $match->team2_id)->firstOrFail()->points);
        $this->assertSame(1, GroupTeam::where('team_id', $match->team2_id)->firstOrFail()->played);
        $this->resultRejected(fn () => $this->resultService()->publish(2026, $match->id, $corrected->version, $review['draft_hash']));
        $this->assertSame(2, GroupTeam::where('team_id', $match->team2_id)->firstOrFail()->points);
        $again = $this->reviewedPublish($corrected, $input);
        $this->assertSame(1, GroupTeam::where('team_id', $match->team2_id)->firstOrFail()->played);
        $this->assertSame($corrected->winner_id, $again->winner_id);
    }

    public function test_stale_draft_reviews_schedule_edits_and_wrong_receipts_cannot_publish(): void
    {
        $match = $this->resultFixture();
        $input = $this->resultInput($match);
        $this->resultService()->saveDraft(2026, $match->id, 1, $input);
        $old = $this->resultService()->review(2026, $match->id);
        $input['team1_runs'] = 110;
        $this->resultService()->saveDraft(2026, $match->id, $old['version'], $input);
        $this->resultRejected(fn () => $this->resultService()->publish(2026, $match->id, $old['version'], $old['draft_hash']));
        $new = $this->resultService()->review(2026, $match->id);
        $this->resultRejected(fn () => $this->resultService()->publish(2026, $match->id, $new['version'], $old['draft_hash']));
        app(MatchSchedulingService::class)->updateSchedule(2026, $match->id, $new['version'], ['venue' => 'New venue']);
        $this->resultRejected(fn () => $this->resultService()->publish(2026, $match->id, $new['version'], $new['draft_hash']));
        $this->assertNull($match->fresh()->team1_runs);
        $this->assertSame(110, $match->fresh()->draft_result['team1_runs']);
        $this->assertSame(0, GroupTeam::where('team_id', $match->team1_id)->firstOrFail()->points);
    }

    public function test_discard_and_unchanged_saves_do_not_touch_official_result_or_standings(): void
    {
        $match = $this->resultFixture();
        $published = $this->reviewedPublish($match, $this->resultInput($match));
        $input = $this->resultInput($match);
        $input['team1_runs'] = 120;
        $draft = $this->resultService()->saveDraft(2026, $match->id, $published->version, $input);
        $same = $this->resultService()->saveDraft(2026, $match->id, $draft->version, array_reverse($input, true));
        $this->assertSame($draft->version, $same->version);
        $discarded = $this->resultService()->discardDraft(2026, $match->id, $draft->version);
        $this->assertNull($discarded->draft_result);
        $this->assertSame(100, $discarded->team1_runs);
        $this->assertSame($published->published_by, $discarded->published_by);
        $this->assertSame(2, GroupTeam::where('team_id', $match->team1_id)->firstOrFail()->points);
        $form = $this->resultService()->editor(2026, $match->id)['form'];
        $this->assertSame(3, $form['team2_overs']);
        $this->assertSame(0, $form['team2_balls']);
    }

    public function test_json_key_order_does_not_invalidate_an_unchanged_reviewed_draft(): void
    {
        $match = $this->resultFixture();
        $input = $this->resultInput($match);
        $this->resultService()->saveDraft(2026, $match->id, 1, $input);
        $review = $this->resultService()->review(2026, $match->id);
        DB::table('matches')->where('id', $match->id)->update(['draft_result' => json_encode(array_reverse($input, true))]);
        $this->assertSame($review['draft_hash'], $this->resultService()->review(2026, $match->id)['draft_hash']);
        $this->assertSame('finished', $this->resultService()->publish(2026, $match->id, $review['version'], $review['draft_hash'])->status);
    }

    public function test_failed_standings_calculation_rolls_back_the_published_result_and_keeps_the_draft(): void
    {
        $match = $this->resultFixture();
        $this->resultService()->saveDraft(2026, $match->id, 1, $this->resultInput($match));
        $review = $this->resultService()->review(2026, $match->id);
        $before = $match->fresh()->getAttributes();
        $rows = GroupTeam::orderBy('id')->get()->map->getAttributes()->all();
        $group = TournamentGroup::findOrFail($match->group_id)->getAttributes();
        $calculator = Mockery::mock(GroupStandingsCalculator::class);
        $calculator->shouldReceive('calculate')->once()->andThrow(new RuntimeException('Synthetic calculation failure'));
        $this->app->instance(GroupStandingsCalculator::class, $calculator);
        try {
            $this->resultService()->publish(2026, $match->id, $review['version'], $review['draft_hash']);
            $this->fail('Expected the synthetic failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic calculation failure', $exception->getMessage());
        }
        $this->assertSame($before, $match->fresh()->getAttributes());
        $this->assertSame($rows, GroupTeam::orderBy('id')->get()->map->getAttributes()->all());
        $this->assertSame($group, TournamentGroup::findOrFail($match->group_id)->getAttributes());
    }

    public static function badResultDrafts(): array
    {
        return [[['created_by' => 3]], [['status' => 'finished']], [['team1_deliveries' => 3]],
            [['team1_runs' => -1]], [['team1_wickets' => 12]], [['team1_overs' => 5, 'team1_balls' => 1]],
            [['nrr_inputs' => ['team1' => ['runs' => -1]]]], [['nrr_inputs' => ['team2' => ['secret' => 1]]]],
            [['result_note' => str_repeat('x', 8193)]], [[]]];
    }

    #[DataProvider('badResultDrafts')]
    public function test_unsafe_partial_drafts_are_rejected_without_a_write(array $input): void
    {
        $match = $this->resultFixture();
        $before = $match->getAttributes();
        try {
            $this->resultService()->saveDraft(2026, $match->id, 1, $input);
            $this->fail('Expected invalid draft rejection.');
        } catch (ValidationException) {
            $this->assertSame($before, $match->fresh()->getAttributes());
        }
    }

    public static function resultActors(): array
    {
        return [['guest'], ['representative'], ['pending'], ['admin'], ['revoked'], ['disabled']];
    }

    #[DataProvider('resultActors')]
    public function test_every_result_entry_point_checks_current_scorer_permission(string $role): void
    {
        $match = $this->resultFixture();
        $this->resultService()->saveDraft(2026, $match->id, 1, $this->resultInput($match));
        $review = $this->resultService()->review(2026, $match->id);
        $before = $match->fresh()->getAttributes();
        if ($role === 'guest') {
            request()->attributes->remove('user');
        } elseif ($role === 'disabled') {
            config(['tournament.enabled' => false]);
        } elseif ($role === 'revoked') {
            DB::table('users')->where('id', $this->resultActor->id)->update(['is_scorer' => false]);
        } else {
            request()->attributes->set('user', User::factory()->create(['is_admin' => $role === 'admin',
                'company_id' => $role === 'representative' ? Company::factory()->create()->id : null]));
        }
        foreach ([fn () => $this->resultService()->editor(2026, $match->id),
            fn () => $this->resultService()->saveDraft(2026, $match->id, $review['version'], $this->resultInput($match)),
            fn () => $this->resultService()->discardDraft(2026, $match->id, $review['version']),
            fn () => $this->resultService()->review(2026, $match->id),
            fn () => $this->resultService()->publish(2026, $match->id, $review['version'], $review['draft_hash'])] as $action) {
            try {
                $action();
                $this->fail('Expected current scorer permission rejection.');
            } catch (HttpException $exception) {
                $this->assertSame($role === 'disabled' ? 404 : 403, $exception->getStatusCode());
            }
        }
        $this->assertSame($before, $match->fresh()->getAttributes());
    }

    public function test_publication_waits_for_unimplemented_bracket_dependencies(): void
    {
        $match = $this->resultFixture();
        $this->resultService()->saveDraft(2026, $match->id, 1, $this->resultInput($match));
        DB::table('matches')->insert(['tournament_year' => 2026, 'stage' => 'QF', 'bracket_position' => 1,
            'team1_id' => $match->team1_id, 'team2_id' => $match->team2_id, 'balls_per_over' => 4]);
        $before = $match->fresh()->getAttributes();
        $review = $this->resultService()->review(2026, $match->id);
        $this->resultRejected(fn () => $this->resultService()->publish(2026, $match->id, $review['version'], $review['draft_hash']));
        $this->assertSame($before, $match->fresh()->getAttributes());
    }

    private function resultWithPublishedPlayers(): array
    {
        $match = $this->resultFixture();
        $match = $this->reviewedPublish($match, $this->resultInput($match));
        $members = [];
        foreach ([$match->team1_id, $match->team2_id] as $teamId) {
            $member = TeamMember::factory()->create(['team_id' => $teamId]);
            $members[] = $member;
            app(PlayerStatsService::class)->saveDraft(2026, $match->id, $teamId, $member->id,
                ['draft' => null, 'published' => null], ['runs_scored' => 30, 'dot_balls' => 0]);
        }
        $publisher = app(PlayerPublicationService::class);
        $review = $publisher->review(2026, $match->id);
        $match = $publisher->publish(2026, $match->id, $review['version'], $review['player_stats_revision'], $review['receipt']);
        // One correction and one withdrawal remain drafts throughout result changes.
        foreach ($members as $index => $member) {
            $official = PlayerMatchStat::where('match_id', $match->id)->where('team_member_id', $member->id)->firstOrFail();
            $expected = ['draft' => null, 'published' => ['id' => $official->id, 'version' => $official->version]];
            if ($index === 0) {
                app(PlayerStatsService::class)->saveDraft(2026, $match->id, $member->team_id, $member->id, $expected, ['runs_scored' => 99]);
            } else {
                app(PlayerStatsService::class)->stageWithdrawal(2026, $match->id, $member->team_id, $member->id, $expected);
            }
        }

        return [$match->fresh(), $members];
    }

    public static function excludedPlayerOutcomes(): array
    {
        return [['abandoned'], ['no_result']];
    }

    #[DataProvider('excludedPlayerOutcomes')]
    public function test_result_exclusion_and_reinstatement_update_both_teams_without_publishing_player_drafts(string $outcome): void
    {
        [$match, $members] = $this->resultWithPublishedPlayers();
        $sources = PlayerMatchStat::orderBy('id')->get()->map->getAttributes()->all();
        $revision = $match->player_stats_revision;
        $playerTime = $match->player_stats_published_at;
        $excluded = ['result_type' => $outcome, 'result_note' => 'Committee correction'];
        $this->resultService()->saveDraft(2026, $match->id, $match->version, $excluded);
        $this->resultService()->review(2026, $match->id);
        $this->assertDatabaseCount('player_tournament_stats', 2);
        $match = $this->reviewedPublish($match, $excluded);
        $this->assertDatabaseCount('player_tournament_stats', 0);
        $this->assertSame(0, GroupTeam::where('team_id', $match->team1_id)->firstOrFail()->wins);
        $this->assertSame($sources, PlayerMatchStat::orderBy('id')->get()->map->getAttributes()->all());
        $match = $this->reviewedPublish($match, $this->resultInput($match));
        foreach ($members as $member) {
            $total = PlayerTournamentStat::where('team_member_id', $member->id)->firstOrFail();
            $this->assertSame(30, $total->runs_scored);
            $this->assertSame(30, $total->highest_score);
            $this->assertSame(0, $total->dot_balls);
            $this->assertNull($total->wickets_taken);
        }
        $this->assertSame(1, GroupTeam::where('team_id', $match->team1_id)->firstOrFail()->wins);
        $this->assertSame($revision, $match->player_stats_revision);
        $this->assertTrue($playerTime->equalTo($match->player_stats_published_at));
        $this->assertSame($sources, PlayerMatchStat::orderBy('id')->get()->map->getAttributes()->all());
        $this->reviewedPublish($match, $this->resultInput($match));
        $this->assertSame(60, (int) PlayerTournamentStat::sum('runs_scored'));
    }

    public static function failedPlayerEligibilityChanges(): array
    {
        return [['exclude'], ['restore']];
    }

    #[DataProvider('failedPlayerEligibilityChanges')]
    public function test_failed_player_recalculation_rolls_back_result_standings_and_drafts(string $direction): void
    {
        [$match] = $this->resultWithPublishedPlayers();
        $excluded = ['result_type' => 'no_result', 'result_note' => 'Committee correction'];
        if ($direction === 'restore') {
            $match = $this->reviewedPublish($match, $excluded);
        }
        $this->resultService()->saveDraft(2026, $match->id, $match->fresh()->version,
            $direction === 'restore' ? $this->resultInput($match) : $excluded);
        $review = $this->resultService()->review(2026, $match->id);
        $tables = ['matches', 'tournament_groups', 'group_teams', 'player_match_stats', 'player_tournament_stats'];
        $before = [];
        foreach ($tables as $table) {
            $before[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }
        $event = 'eloquent.'.($direction === 'restore' ? 'created' : 'deleted').': '.PlayerTournamentStat::class;
        Event::listen($event, fn () => throw new RuntimeException('Synthetic eligibility failure'));
        try {
            $this->resultService()->publish(2026, $match->id, $review['version'], $review['draft_hash']);
            $this->fail('Expected rollback.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic eligibility failure', $exception->getMessage());
        } finally {
            Event::forget($event);
        }
        foreach ($tables as $table) {
            $this->assertSame($before[$table], DB::table($table)->orderBy('id')->get()->toJson(), $table);
        }
        $this->resultService()->publish(2026, $match->id, $review['version'], $review['draft_hash']);
        $this->assertDatabaseCount('player_tournament_stats', $direction === 'restore' ? 2 : 0);
    }

    public function test_finished_status_reversal_with_published_players_is_still_blocked_without_changing_totals(): void
    {
        [$match] = $this->resultWithPublishedPlayers();
        $before = PlayerTournamentStat::orderBy('id')->get()->map->getAttributes()->all();
        foreach (['live', 'upcoming'] as $status) {
            $this->resultRejected(fn () => app(MatchSchedulingService::class)
                ->updateSchedule(2026, $match->id, $match->version, ['status' => $status]), 'status');
        }
        $this->assertSame('finished', $match->fresh()->status);
        $this->assertSame($before, PlayerTournamentStat::orderBy('id')->get()->map->getAttributes()->all());
    }

    public function test_eligible_result_correction_keeps_official_player_totals_and_invalidates_old_player_review(): void
    {
        [$match] = $this->resultWithPublishedPlayers();
        $publisher = app(PlayerPublicationService::class);
        $review = $publisher->review(2026, $match->id);
        $before = PlayerTournamentStat::orderBy('id')->get()->map->getAttributes()->all();
        $match = $this->reviewedPublish($match, array_replace($this->resultInput($match), [
            'result_type' => 'tie', 'winner_id' => null, 'team2_runs' => 100,
            'team2_overs' => 5, 'team2_wickets' => 3,
        ]));
        $this->assertSame($before, PlayerTournamentStat::orderBy('id')->get()->map->getAttributes()->all());
        $this->assertSame(1, GroupTeam::where('team_id', $match->team1_id)->firstOrFail()->ties);
        $this->resultRejected(fn () => $publisher->publish(2026, $match->id,
            $review['version'], $review['player_stats_revision'], $review['receipt']), 'player');
        $this->assertSame(2, PlayerMatchStat::where('record_state', 'draft')->count());
    }

    public function test_result_status_reversal_preserves_official_data_drafts_and_standings(): void
    {
        $match = $this->resultFixture();
        $match = $this->reviewedPublish($match, $this->resultInput($match));
        $match = $this->resultService()->saveDraft(2026, $match->id, $match->version, ['team1_runs' => 101]);
        $before = $match->fresh()->getAttributes();
        $standings = GroupTeam::orderBy('id')->get()->map->getAttributes()->all();
        $group = TournamentGroup::findOrFail($match->group_id)->getAttributes();
        foreach (['upcoming', 'live'] as $status) {
            $this->resultRejected(fn () => app(MatchSchedulingService::class)
                ->updateSchedule(2026, $match->id, $match->version, ['status' => $status, 'venue' => 'Must roll back']), 'status');
            $this->assertSame($before, $match->fresh()->getAttributes());
            $this->assertSame($standings, GroupTeam::orderBy('id')->get()->map->getAttributes()->all());
            $this->assertSame($group, TournamentGroup::findOrFail($match->group_id)->getAttributes());
        }
    }

    public function test_published_player_row_blocks_status_change_even_without_publication_marker(): void
    {
        $match = $this->resultFixture();
        $member = TeamMember::factory()->create(['team_id' => $match->team1_id]);
        DB::table('player_match_stats')->insert(['match_id' => $match->id, 'team_id' => $match->team1_id,
            'team_member_id' => $member->id, 'record_state' => 'published', 'pending_action' => null,
            'published_at' => now(), 'player_name' => $member->name, 'player_gender' => $member->gender]);
        $before = $match->fresh()->getAttributes();
        $players = DB::table('player_match_stats')->get()->all();
        $this->resultRejected(fn () => app(MatchSchedulingService::class)
            ->updateSchedule(2026, $match->id, $match->version, ['status' => 'live']), 'status');
        $this->assertSame($before, $match->fresh()->getAttributes());
        $this->assertEquals($players, DB::table('player_match_stats')->get()->all());
        $updated = app(MatchSchedulingService::class)->updateSchedule(2026, $match->id, $match->version, ['venue' => 'Ground B']);
        $this->assertSame('Ground B', $updated->venue);
    }

    public function test_other_year_knockout_does_not_block_current_year_result_publication(): void
    {
        $match = $this->resultFixture();
        DB::table('matches')->insert(['tournament_year' => 2027, 'stage' => 'QF', 'bracket_position' => 1,
            'team1_id' => $match->team1_id, 'team2_id' => $match->team2_id, 'balls_per_over' => 4]);
        $published = $this->reviewedPublish($match, $this->resultInput($match));
        $this->assertSame('finished', $published->status);
        $this->assertSame(2, GroupTeam::where('team_id', $match->team1_id)->firstOrFail()->points);
    }

    public function test_other_year_missing_ids_and_mutation_throttling_are_rejected(): void
    {
        $match = $this->resultFixture();
        $this->resultRejected(fn () => $this->resultService()->editor(2027, $match->id));
        $this->resultRejected(fn () => $this->resultService()->saveDraft(2027, $match->id, 1, $this->resultInput($match)));
        $this->resultRejected(fn () => $this->resultService()->editor(2026, 999999));
        config(['tournament.writes_per_minute' => 1]);
        RateLimiter::hit('tournament:scorer-write:'.$this->resultActor->id, 60);
        $this->resultRejected(fn () => $this->resultService()->saveDraft(2026, $match->id, 1, $this->resultInput($match)));
        $this->assertNull($match->fresh()->draft_result);
    }

    public function test_revised_target_storage_can_be_reopened_in_form_units_without_changing_nrr(): void
    {
        $match = $this->resultFixture();
        $input = array_replace($this->resultInput($match), ['result_method' => 'revised_target', 'winner_id' => $match->team2_id,
            'revised_target' => 60, 'result_note' => 'Official revised decision', 'nrr_inputs' => [
                'included' => true, 'reason' => 'Official NRR figures', 'team1' => ['runs' => 70, 'overs' => 4, 'balls' => 0],
                'team2' => ['runs' => 61, 'overs' => 3, 'balls' => 2]]]);
        $published = $this->reviewedPublish($match, $input);
        $this->assertSame(14, $published->nrr_inputs['team2']['deliveries']);
        $this->assertSame(3, $this->resultService()->editor(2026, $match->id)['form']['nrr_inputs']['team2']['overs']);
        $this->assertSame(2, $this->resultService()->editor(2026, $match->id)['form']['nrr_inputs']['team2']['balls']);
        $this->assertSame('0.071429', GroupTeam::where('team_id', $match->team1_id)->firstOrFail()->nrr);
    }

    public function test_group_rebuild_includes_other_published_results_but_never_another_match_draft(): void
    {
        $match = $this->resultFixture(3);
        $third = GroupTeam::where('group_id', $match->group_id)->whereNotIn('team_id', [$match->team1_id, $match->team2_id])->firstOrFail();
        $other = app(MatchSchedulingService::class)->create(2026, 4, ['stage' => 'G', 'group_id' => $match->group_id,
            'team1_id' => $match->team1_id, 'team2_id' => $third->team_id, 'balls_per_over' => 6]);
        $otherInput = $this->resultInput($other);
        $otherInput['team1_runs'] = 200;
        $this->resultService()->saveDraft(2026, $other->id, 1, $otherInput);
        $this->reviewedPublish($match, $this->resultInput($match));
        $a = GroupTeam::where('team_id', $match->team1_id)->firstOrFail();
        $this->assertSame(1, $a->played);
        $this->assertSame(100, $a->runs_for);
        $review = $this->resultService()->review(2026, $other->id);
        $this->resultService()->publish(2026, $other->id, $review['version'], $review['draft_hash']);
        $this->assertSame(2, $a->fresh()->played);
        $this->assertSame(4, $a->fresh()->points);
        $this->assertSame(300, $a->fresh()->runs_for);
    }

    public static function officialOutcomes(): array
    {
        return [
            [['result_type' => 'tie', 'winner_id' => null, 'team2_runs' => 100], 'Match tied.', 1],
            [['result_type' => 'no_result', 'result_method' => null, 'winner_id' => null], 'No result.', 1],
            [['result_type' => 'abandoned', 'result_method' => null, 'winner_id' => null], 'Match abandoned.', 1],
            [['result_method' => 'super_over', 'team2_runs' => 100, 'result_note' => 'Official Super Over winner'], 'Side 1 won after a Super Over.', 2],
            [['result_method' => 'coin_toss', 'result_note' => 'Official toss decision'], 'Side 1 won by coin toss.', 2],
            [['result_method' => 'official_decision', 'result_note' => 'Committee decision'], 'Side 1 won by official decision.', 2],
            [['batting_first_id' => null], 'Side 1 won.', 2],
            [['team2_runs' => 99], 'Side 1 won by 1 run.', 2],
        ];
    }

    #[DataProvider('officialOutcomes')]
    public function test_published_outcome_descriptions_and_points_follow_the_recorded_method(array $changes, string $description, int $points): void
    {
        $match = $this->resultFixture();
        $this->resultService()->saveDraft(2026, $match->id, 1, array_replace($this->resultInput($match), $changes));
        $review = $this->resultService()->review(2026, $match->id);
        $this->assertSame($description, $review['description']);
        $this->resultService()->publish(2026, $match->id, $review['version'], $review['draft_hash']);
        $this->assertSame($points, GroupTeam::where('team_id', $match->team1_id)->firstOrFail()->points);
    }

    public function test_a_failure_after_a_standings_row_write_rolls_back_every_official_change(): void
    {
        $match = $this->resultFixture();
        $this->resultService()->saveDraft(2026, $match->id, 1, $this->resultInput($match));
        $review = $this->resultService()->review(2026, $match->id);
        $before = $match->fresh()->getAttributes();
        $rows = GroupTeam::orderBy('id')->get()->map->getAttributes()->all();
        $event = 'eloquent.updated: '.GroupTeam::class;
        Event::listen($event, function () {
            throw new RuntimeException('Synthetic failure after standings write');
        });
        try {
            $this->resultService()->publish(2026, $match->id, $review['version'], $review['draft_hash']);
            $this->fail('Expected rollback after the first standings write.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic failure after standings write', $exception->getMessage());
        } finally {
            Event::forget($event);
        }
        $this->assertSame($before, $match->fresh()->getAttributes());
        $this->assertSame($rows, GroupTeam::orderBy('id')->get()->map->getAttributes()->all());
    }

    public function test_locked_batting_order_cannot_change_in_a_correction_draft(): void
    {
        $match = $this->resultFixture();
        $published = $this->reviewedPublish($match, $this->resultInput($match));
        $this->resultRejected(fn () => $this->resultService()->saveDraft(2026, $match->id, $published->version,
            array_replace($this->resultInput($match), ['batting_first_id' => null])), 'batting_first_id');
        $this->assertNull($match->fresh()->draft_result);
    }

    public function test_seven_four_team_groups_publish_42_fixtures_with_real_pair_coverage(): void
    {
        $first = $this->resultFixture(4);
        $groups = app(TournamentGroupService::class);
        foreach (range('A', 'G') as $name) {
            $group = $name === 'A' ? TournamentGroup::findOrFail($first->group_id) : $groups->create(2026, $name);
            if ($name !== 'A') {
                foreach (Team::factory()->count(4)->create(['approved' => true]) as $team) {
                    $groups->assign(2026, $group->id, $group->fresh()->version, $team->id);
                }
            }
            $ids = GroupTeam::where('group_id', $group->id)->orderBy('team_id')->pluck('team_id');
            foreach (range(0, 2) as $a) {
                foreach (range($a + 1, 3) as $b) {
                    if ($name === 'A' && $a === 2 && $b === 3) {
                        $coverage = collect(app(\App\Services\Tournament\ScorerStandingsService::class)->listing(2026))->firstWhere('id', $group->id)['coverage'];
                        $this->assertSame(['expected' => 6, 'created' => 5, 'published' => 5, 'missing' => 1, 'unpublished' => 0, 'complete' => false], $coverage);
                    }
                    $match = $name === 'A' && $a === 0 && $b === 1 ? $first : app(MatchSchedulingService::class)->create(2026, $group->fresh()->version, [
                        'stage' => 'G', 'group_id' => $group->id, 'team1_id' => $ids[$a], 'team2_id' => $ids[$b], 'balls_per_over' => $b % 2 === 0 ? 6 : 4,
                    ]);
                    $this->reviewedPublish($match, $this->resultInput($match));
                }
            }
            foreach ($ids as $index => $id) {
                $row = GroupTeam::where('team_id', $id)->firstOrFail();
                $this->assertSame(3, $row->played);
                $this->assertSame((3 - $index) * 2, $row->points);
            }
        }
        $listing = app(\App\Services\Tournament\ScorerStandingsService::class)->listing(2026);
        $this->assertCount(7, $listing);
        $this->assertSame(42, array_sum(array_column(array_column($listing, 'coverage'), 'published')));
        foreach ($listing as $group) {
            $this->assertTrue($group['coverage']['complete']);
        }
        $this->assertDatabaseCount('matches', 42);
        $this->assertDatabaseCount('group_teams', 28);
    }
}
