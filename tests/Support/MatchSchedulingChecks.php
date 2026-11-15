<?php

namespace Tests\Support;

use App\Models\Company;
use App\Models\Team;
use App\Models\TournamentGroup;
use App\Models\User;
use App\Services\Tournament\MatchSchedulingService;
use App\Services\Tournament\TournamentGroupService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;

trait MatchSchedulingChecks
{
    private User $actor;

    private function fixture(): array
    {
        config(['tournament.enabled' => true, 'tournament.writes_per_minute' => 1000]);
        $this->actor = User::factory()->create(['is_admin' => false, 'company_id' => null]);
        $this->actor->is_scorer = true;
        $this->actor->save();
        request()->attributes->set('user', $this->actor);
        $groups = app(TournamentGroupService::class);
        $group = $groups->create(2026, 'A');
        $teams = Team::factory()->count(3)->create(['approved' => true]);
        foreach ($teams as $team) {
            $groups->assign(2026, $group->id, $group->fresh()->version, $team->id);
        }

        return ['stage' => 'G', 'group_id' => $group->id, 'team1_id' => $teams[0]->id,
            'team2_id' => $teams[1]->id, 'balls_per_over' => 4];
    }

    private function create(array $input, array $schedule = [])
    {
        return app(MatchSchedulingService::class)->create(2026, 4, $input, $schedule);
    }

    private function rejected(string $field, callable $write): void
    {
        try {
            $write();
            $this->fail('Expected scheduling rejection.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    public function test_creation_sets_actor_and_format_without_inventing_results_or_editing_existing_records(): void
    {
        $input = $this->fixture();
        $teams = DB::table('teams')->orderBy('id')->get()->toJson();
        $accounts = DB::table('users')->orderBy('id')->get()->toJson();
        $match = $this->create($input);
        $this->assertSame('upcoming', $match->status);
        $this->assertSame(1, $match->version);
        $this->assertSame($this->actor->id, $match->created_by);
        $this->assertSame($this->actor->id, $match->updated_by);
        $this->assertSame(4, $match->balls_per_over);
        $this->assertSame(5, $match->overs_per_innings);
        $this->assertSame(11, $match->max_wickets);
        foreach (['team1_runs', 'team1_wickets', 'team1_deliveries', 'team2_runs', 'team2_wickets',
            'team2_deliveries', 'winner_id', 'result_type', 'result_method', 'result_published_at',
            'draft_result', 'nrr_inputs', 'structure_locked_at', 'player_stats_published_at', 'published_by'] as $field) {
            $this->assertNull($match->$field, $field);
        }
        $this->assertSame($teams, DB::table('teams')->orderBy('id')->get()->toJson());
        $this->assertSame($accounts, DB::table('users')->orderBy('id')->get()->toJson());
        $this->assertSame(4, TournamentGroup::findOrFail($input['group_id'])->version);
    }

    public function test_live_creation_accepts_optional_schedule_in_tournament_time(): void
    {
        $input = $this->fixture();
        $input['balls_per_over'] = 6;
        $match = $this->create($input, ['status' => 'live', 'scheduled_at' => '2026-11-20T09:30',
            'venue' => ' Main ground ', 'display_number' => '7', 'batting_first_id' => (string) $input['team2_id']]);
        $this->assertSame('live', $match->status);
        $this->assertSame(6, $match->balls_per_over);
        $this->assertSame('2026-11-20 04:00:00', $match->scheduled_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('Main ground', $match->venue);
        $this->assertSame(7, $match->display_number);
        $this->assertSame($input['team2_id'], $match->batting_first_id);
        $this->assertNull($match->team1_runs);
    }

    public function test_partial_schedule_updates_preserve_drafts_and_reject_stale_versions(): void
    {
        $input = $this->fixture();
        $match = $this->create($input);
        $match->draft_result = ['status' => 'finished', 'team1_runs' => 0];
        $match->save();
        $service = app(MatchSchedulingService::class);
        $changed = $service->updateSchedule(2026, $match->id, 1, ['status' => 'live', 'venue' => 'Ground B']);
        $this->assertSame(2, $changed->version);
        $this->assertSame($match->draft_result, $changed->draft_result);
        $this->assertNull($changed->team1_runs);
        $this->rejected('tournament', fn () => $service->updateSchedule(2026, $match->id, 1, ['venue' => 'Stale']));
        $changed = $service->updateSchedule(2026, $match->id, 2, ['status' => 'upcoming']);
        $this->assertSame(3, $changed->version);
        $this->assertSame('Ground B', $changed->venue);
        $this->assertSame($this->actor->id, $changed->updated_by);
        $unchanged = $service->updateSchedule(2026, $match->id, 3, ['venue' => 'Ground B']);
        $this->assertSame(3, $unchanged->version);
    }

    public static function invalidSchedule(): array
    {
        return [
            [['status' => 'finished'], 'status'], [['status' => null], 'status'],
            [['scheduled_at' => '2026-02-30T09:00'], 'scheduled_at'],
            [['scheduled_at' => 'tomorrow'], 'scheduled_at'],
            [['venue' => str_repeat('x', 256)], 'venue'], [['display_number' => 0], 'display_number'],
            [['batting_first_id' => 999999], 'batting_first_id'],
            [['team1_runs' => 0], 'tournament'], [['draft_result' => []], 'tournament'],
            [['created_by' => 999999], 'tournament'], [['balls_per_over' => 6], 'tournament'],
            [['team1_id' => 999999], 'tournament'], [['version' => 99], 'tournament'],
        ];
    }

    #[DataProvider('invalidSchedule')]
    public function test_invalid_schedule_fields_cannot_create_or_update_a_match(array $schedule, string $field): void
    {
        $input = $this->fixture();
        $this->rejected($field, fn () => $this->create($input, $schedule));
        $this->assertDatabaseCount('matches', 0);
        $match = $this->create($input);
        $before = $match->fresh()->getAttributes();
        $this->rejected($field, fn () => app(MatchSchedulingService::class)->updateSchedule(2026, $match->id, 1, $schedule));
        $this->assertSame($before, $match->fresh()->getAttributes());
    }

    public function test_stale_group_versions_duplicates_and_wrong_year_or_missing_matches_are_rejected(): void
    {
        $input = $this->fixture();
        $service = app(MatchSchedulingService::class);
        $this->rejected('tournament', fn () => $service->create(2026, 1, $input));
        $this->rejected('group_id', fn () => $service->create(2027, 4, $input));
        $match = $this->create($input);
        $this->rejected('team2_id', fn () => $this->create(array_replace($input,
            ['team1_id' => $input['team2_id'], 'team2_id' => $input['team1_id']])));
        $this->rejected('tournament', fn () => $service->updateSchedule(2027, $match->id, 1, ['status' => 'live']));
        $this->rejected('tournament', fn () => $service->updateSchedule(2026, 999999, 1, ['status' => 'live']));
        $this->assertDatabaseCount('matches', 1);
    }

    public function test_official_publication_and_locked_batting_first_are_not_changed_through_scheduling(): void
    {
        $input = $this->fixture();
        $match = $this->create($input);
        $match->status = 'finished';
        $match->team1_runs = 0;
        $match->result_type = 'tie';
        $match->structure_locked_at = now();
        $match->result_published_at = now();
        $match->draft_result = ['team1_runs' => 10];
        $match->save();
        $before = $match->fresh()->getAttributes();
        $service = app(MatchSchedulingService::class);
        $this->rejected('status', fn () => $service->updateSchedule(2026, $match->id, 1, ['status' => 'upcoming']));
        $this->rejected('batting_first_id', fn () => $service->updateSchedule(2026, $match->id, 1, ['batting_first_id' => $input['team1_id']]));
        $this->assertSame($before, $match->fresh()->getAttributes());
        $updated = $service->updateSchedule(2026, $match->id, 1, ['venue' => 'Corrected venue']);
        $this->assertSame(0, $updated->team1_runs);
        $this->assertSame('finished', $updated->status);
        $this->assertSame($match->draft_result, $updated->draft_result);
        $this->assertEquals($match->structure_locked_at, $updated->structure_locked_at);
    }

    public static function unauthorizedActors(): array
    {
        return [['guest'], ['representative'], ['pending'], ['admin'], ['revoked scorer'], ['disabled']];
    }

    #[DataProvider('unauthorizedActors')]
    public function test_every_mutation_requires_current_scorer_permission_and_enabled_feature(string $role): void
    {
        $input = $this->fixture();
        $match = $this->create($input);
        if ($role === 'guest') {
            request()->attributes->remove('user');
        } elseif ($role === 'disabled') {
            config(['tournament.enabled' => false]);
        } elseif ($role === 'revoked scorer') {
            // Leave the in-memory request user stale to exercise the DB recheck.
            DB::table('users')->where('id', $this->actor->id)->update(['is_scorer' => false]);
        } else {
            request()->attributes->set('user', User::factory()->create([
                'is_admin' => $role === 'admin',
                'company_id' => $role === 'representative' ? Company::factory()->create()->id : null,
            ]));
        }
        $service = app(MatchSchedulingService::class);
        foreach ([fn () => $this->create($input), fn () => $service->updateSchedule(2026, $match->id, 1, ['status' => 'live'])] as $write) {
            try {
                $write();
                $this->fail('Expected scorer-only scheduling denial.');
            } catch (HttpException $exception) {
                $this->assertSame($role === 'disabled' ? 404 : 403, $exception->getStatusCode());
            }
        }
        $this->assertDatabaseCount('matches', 1);
        $this->assertSame('upcoming', $match->fresh()->status);
    }

    public function test_shared_scorer_throttle_blocks_both_actions_without_changes(): void
    {
        $input = $this->fixture();
        $match = $this->create($input);
        config(['tournament.writes_per_minute' => 1]);
        RateLimiter::hit('tournament:scorer-write:'.$this->actor->id, 60);
        $this->rejected('tournament', fn () => $this->create($input));
        $this->rejected('tournament', fn () => app(MatchSchedulingService::class)->updateSchedule(2026, $match->id, 1, ['status' => 'live']));
        $this->assertSame(1, $match->fresh()->version);
    }

    public function test_another_scorer_can_edit_a_current_version_and_clear_optional_details(): void
    {
        $input = $this->fixture();
        $match = $this->create($input, ['scheduled_at' => '2026-11-20T09:30', 'venue' => 'Ground A',
            'display_number' => 1, 'batting_first_id' => $input['team1_id']]);
        $creator = $this->actor->id;
        $other = User::factory()->create(['is_admin' => false, 'company_id' => null]);
        $other->is_scorer = true;
        $other->save();
        request()->attributes->set('user', $other);
        $updated = app(MatchSchedulingService::class)->updateSchedule(2026, $match->id, 1,
            ['scheduled_at' => null, 'venue' => ' ', 'display_number' => null, 'batting_first_id' => null]);
        foreach (['scheduled_at', 'venue', 'display_number', 'batting_first_id'] as $field) {
            $this->assertNull($updated->$field);
        }
        $this->assertSame($creator, $updated->created_by);
        $this->assertSame($other->id, $updated->updated_by);
        $this->assertSame(2, $updated->version);
    }

    public function test_knockout_schedule_edits_stay_disabled_until_eligibility_is_ready(): void
    {
        $input = $this->fixture();
        $match = $this->create($input);
        // Synthetic future-stage row, not a permitted service creation path.
        $match->stage = 'QF';
        $match->group_id = null;
        $match->bracket_position = 1;
        $match->save();
        $this->rejected('stage', fn () => app(MatchSchedulingService::class)->updateSchedule(2026, $match->id, 1, ['status' => 'live']));
        $this->assertSame('upcoming', $match->fresh()->status);
        $this->assertSame(1, $match->fresh()->version);
    }
}
