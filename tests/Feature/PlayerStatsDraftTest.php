<?php

namespace Tests\Feature;

use App\Models\PlayerMatchStat;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TournamentMatch;
use App\Models\User;
use App\Services\Tournament\PlayerStatsService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class PlayerStatsDraftTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        config(['tournament.enabled' => true, 'tournament.writes_per_minute' => 1000]);
        $actor = User::factory()->create(['company_id' => null, 'is_admin' => false]);
        $actor->is_scorer = true;
        $actor->save();
        request()->attributes->set('user', $actor);
        $teams = Team::factory()->count(2)->create(['approved' => true]);
        $groupId = DB::table('tournament_groups')->insertGetId(['tournament_year' => 2026, 'name' => 'A']);
        foreach ($teams as $team) {
            DB::table('group_teams')->insert(['group_id' => $groupId, 'team_id' => $team->id, 'tournament_year' => 2026]);
        }
        $id = DB::table('matches')->insertGetId(['group_id' => $groupId, 'stage' => 'G', 'tournament_year' => 2026,
            'team1_id' => $teams[0]->id, 'team2_id' => $teams[1]->id, 'balls_per_over' => 4]);
        $member = TeamMember::factory()->create(['team_id' => $teams[0]->id, 'name' => 'Trusted player', 'gender' => 'Female']);

        return [$actor, TournamentMatch::findOrFail($id), $member];
    }

    private function expected(TournamentMatch $match, TeamMember $member): array
    {
        $rows = PlayerMatchStat::where('match_id', $match->id)->where('team_member_id', $member->id)->get();
        $result = [];
        foreach (['draft', 'published'] as $state) {
            $row = $rows->firstWhere('record_state', $state);
            $result[$state] = $row ? ['id' => $row->id, 'version' => $row->version] : null;
        }

        return $result;
    }

    private function save(TournamentMatch $match, TeamMember $member, array $input, ?array $expected = null): PlayerMatchStat
    {
        return app(PlayerStatsService::class)->saveDraft(2026, $match->id, (int) $member->team_id, $member->id,
            $expected ?? $this->expected($match, $member), $input);
    }

    private function rejected(callable $action): void
    {
        try {
            $action();
            $this->fail('Expected stale or invalid draft rejection.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }

    public function test_draft_creation_update_and_noop_preserve_unknowns_and_official_data(): void
    {
        [$actor, $match, $member] = $this->fixture();
        $draft = $this->save($match, $member, ['runs_scored' => '0', 'bowling_balls' => 6, 'dot_balls' => 6]);
        $this->assertSame('draft', $draft->record_state);
        $this->assertSame('replace', $draft->pending_action);
        $this->assertSame(0, $draft->runs_scored);
        $this->assertNull($draft->balls_faced);
        $this->assertNull($draft->published_at);
        $this->assertSame($actor->id, $draft->updated_by);
        $this->assertSame(1, $match->fresh()->player_stats_revision);
        $this->assertSame(1, $match->fresh()->version);
        $updated = $this->save($match, $member, ['runs_scored' => 12]);
        $this->assertSame($draft->id, $updated->id);
        $this->assertSame(2, $updated->version);
        $this->assertSame(6, $updated->dot_balls);
        $this->assertSame(2, $match->fresh()->player_stats_revision);
        $same = $this->save($match, $member, ['runs_scored' => '12']);
        $this->assertSame(2, $same->version);
        $this->assertSame(2, $match->fresh()->player_stats_revision);
        $this->save($match, $member, ['runs_scored' => null]);
        $this->assertNull($draft->fresh()->runs_scored);
        $this->assertSame(3, $match->fresh()->player_stats_revision);
        $this->assertNull($match->fresh()->result_published_at);
        $this->assertDatabaseCount('player_tournament_stats', 0);
    }

    public function test_correction_copies_official_figures_and_preserves_published_identity(): void
    {
        [, $match, $member] = $this->fixture();
        $id = DB::table('player_match_stats')->insertGetId(['match_id' => $match->id, 'team_id' => $member->team_id,
            'team_member_id' => $member->id, 'record_state' => 'published', 'pending_action' => null, 'published_at' => now(),
            'player_name' => 'Historical name', 'player_gender' => 'Male', 'runs_scored' => 25, 'fours' => 2, 'sixes' => 1]);
        $official = PlayerMatchStat::findOrFail($id)->getAttributes();
        DB::table('team_members')->where('id', $member->id)->update(['name' => 'New profile name', 'gender' => 'Female']);
        $draft = $this->save($match, $member, ['runs_scored' => 30]);
        $this->assertSame('Historical name', $draft->player_name);
        $this->assertSame('Male', $draft->player_gender);
        $this->assertSame(2, $draft->fours);
        $this->assertSame(1, $draft->sixes);
        $this->assertSame($official, PlayerMatchStat::findOrFail($id)->getAttributes());
        $this->assertDatabaseCount('player_match_stats', 2);
    }

    public function test_first_draft_keeps_its_trusted_snapshot_during_later_profile_edits(): void
    {
        [, $match, $member] = $this->fixture();
        $draft = $this->save($match, $member, ['runs_scored' => 0]);
        DB::table('team_members')->where('id', $member->id)->update(['name' => 'New name', 'gender' => 'Male']);
        $next = $this->save($match, $member, ['runs_scored' => 2]);
        $this->assertSame($draft->player_name, $next->player_name);
        $this->assertSame($draft->player_gender, $next->player_gender);
    }

    public function test_stale_versions_absence_and_recreated_draft_identities_are_rejected(): void
    {
        [, $match, $member] = $this->fixture();
        $empty = ['draft' => null, 'published' => null];
        $draft = $this->save($match, $member, ['runs_scored' => 1], $empty);
        $old = $this->expected($match, $member);
        $this->rejected(fn () => $this->save($match, $member, ['runs_scored' => 9], $empty));
        $this->save($match, $member, ['runs_scored' => 2]);
        $this->rejected(fn () => $this->save($match, $member, ['runs_scored' => 9], $old));
        $draft->delete(); // Simulate a discard/recreation, not a production action.
        $new = $this->save($match, $member, ['runs_scored' => 3], $empty);
        $this->assertNotSame($draft->id, $new->id);
        $this->assertSame(1, $new->version);
        $this->rejected(fn () => $this->save($match, $member, ['runs_scored' => 9], $old));
        $this->assertSame(3, $new->fresh()->runs_scored);
    }

    public function test_independent_player_edits_do_not_require_a_shared_old_match_version(): void
    {
        [, $match, $member] = $this->fixture();
        $other = TeamMember::factory()->create(['team_id' => $member->team_id]);
        $this->save($match, $member, ['runs_scored' => 1]);
        $old = $this->expected($match, $member);
        $this->save($match, $other, ['wickets_taken' => 2]);
        $this->save($match, $member, ['runs_scored' => 2], $old);
        $this->assertSame(3, $match->fresh()->player_stats_revision);
        $this->assertSame(2, PlayerMatchStat::where('team_member_id', $other->id)->firstOrFail()->wickets_taken);
    }

    public function test_invalid_merged_figures_and_injected_metadata_leave_draft_and_revision_unchanged(): void
    {
        [, $match, $member] = $this->fixture();
        $draft = $this->save($match, $member, ['runs_scored' => 10, 'fours' => 1, 'sixes' => 1]);
        $before = $draft->getAttributes();
        foreach ([['runs_scored' => 9], ['record_state' => 'published'], ['player_name' => 'Forged'], ['published_by' => 1]] as $input) {
            $this->rejected(fn () => $this->save($match, $member, $input));
            $this->assertSame($before, $draft->fresh()->getAttributes());
            $this->assertSame(1, $match->fresh()->player_stats_revision);
        }
    }

    public function test_failure_after_draft_write_rolls_back_the_source_row_and_match_revision(): void
    {
        [, $match, $member] = $this->fixture();
        $event = 'eloquent.updating: '.TournamentMatch::class;
        Event::listen($event, fn () => throw new RuntimeException('Synthetic revision failure'));
        try {
            $this->save($match, $member, ['runs_scored' => 0]);
            $this->fail('Expected rollback.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic revision failure', $exception->getMessage());
        } finally {
            Event::forget($event);
        }
        $this->assertDatabaseCount('player_match_stats', 0);
        $this->assertSame(0, $match->fresh()->player_stats_revision);
    }

    public function test_stale_published_version_or_identity_cannot_be_used_as_a_correction_base(): void
    {
        [, $match, $member] = $this->fixture();
        $record = ['match_id' => $match->id, 'team_id' => $member->team_id, 'team_member_id' => $member->id,
            'record_state' => 'published', 'pending_action' => null, 'published_at' => now(),
            'player_name' => 'Official name', 'player_gender' => 'Female', 'runs_scored' => 20];
        $id = DB::table('player_match_stats')->insertGetId($record);
        $old = $this->expected($match, $member);
        DB::table('player_match_stats')->where('id', $id)->increment('version');
        $this->rejected(fn () => $this->save($match, $member, ['runs_scored' => 30], $old));
        DB::table('player_match_stats')->where('id', $id)->delete();
        $replacement = DB::table('player_match_stats')->insertGetId($record);
        $this->assertNotSame($id, $replacement);
        $this->rejected(fn () => $this->save($match, $member, ['runs_scored' => 30], $old));
        $this->assertDatabaseCount('player_match_stats', 1);
        $this->assertSame(0, $match->fresh()->player_stats_revision);
    }

    public function test_draft_service_checks_revoked_permission_and_changed_membership_before_writing(): void
    {
        [$actor, $match, $member] = $this->fixture();
        DB::table('users')->where('id', $actor->id)->update(['is_scorer' => false]);
        try {
            $this->save($match, $member, ['runs_scored' => 1]);
            $this->fail('Expected current scorer denial.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        DB::table('users')->where('id', $actor->id)->update(['is_scorer' => true]);
        DB::table('team_members')->where('id', $member->id)->update(['team_id' => $match->team2_id]);
        $this->rejected(fn () => $this->save($match, $member, ['runs_scored' => 1]));
        $this->assertDatabaseCount('player_match_stats', 0);
        $this->assertSame(0, $match->fresh()->player_stats_revision);
    }

    private function publishedFixture(): array
    {
        [$actor, $match, $member] = $this->fixture();
        $id = DB::table('player_match_stats')->insertGetId(['match_id' => $match->id, 'team_id' => $member->team_id,
            'team_member_id' => $member->id, 'record_state' => 'published', 'pending_action' => null, 'published_at' => now(),
            'player_name' => 'Official identity', 'player_gender' => 'Female', 'runs_scored' => 20, 'fours' => 2, 'sixes' => 1]);

        return [$actor, $match, $member, PlayerMatchStat::findOrFail($id)];
    }

    public function test_discard_removes_only_the_selected_draft_and_advances_review_revision(): void
    {
        [, $match, $member, $published] = $this->publishedFixture();
        $official = $published->getAttributes();
        $this->save($match, $member, ['runs_scored' => 30]);
        $other = TeamMember::factory()->create(['team_id' => $member->team_id]);
        $otherDraft = $this->save($match, $other, ['runs_scored' => 0]);
        $otherBefore = $otherDraft->getAttributes();
        app(PlayerStatsService::class)->discardDraft(2026, $match->id, (int) $member->team_id, $member->id, $this->expected($match, $member));
        $this->assertSame(3, $match->fresh()->player_stats_revision);
        $this->assertSame($official, $published->fresh()->getAttributes());
        $this->assertSame($otherBefore, $otherDraft->fresh()->getAttributes());
        $this->assertSame(1, PlayerMatchStat::where('team_member_id', $member->id)->count());
        $this->assertDatabaseHas('team_members', ['id' => $member->id]);
        app(PlayerStatsService::class)->discardDraft(2026, $match->id, (int) $member->team_id, $member->id, $this->expected($match, $member));
        $this->assertSame(3, $match->fresh()->player_stats_revision);
    }

    public function test_discarding_a_first_draft_does_not_delete_the_registered_player(): void
    {
        [, $match, $member] = $this->fixture();
        $this->save($match, $member, ['runs_scored' => 0]);
        app(PlayerStatsService::class)->discardDraft(2026, $match->id, (int) $member->team_id, $member->id, $this->expected($match, $member));
        $this->assertDatabaseCount('player_match_stats', 0);
        $this->assertDatabaseHas('team_members', ['id' => $member->id]);
        $this->assertSame(2, $match->fresh()->player_stats_revision);
    }

    public function test_withdrawal_stays_pending_preserves_official_values_and_is_idempotent_with_current_expectations(): void
    {
        [$actor, $match, $member, $published] = $this->publishedFixture();
        $official = $published->getAttributes();
        $beforeMatch = $match->getAttributes();
        $service = app(PlayerStatsService::class);
        $draft = $service->stageWithdrawal(2026, $match->id, (int) $member->team_id, $member->id, $this->expected($match, $member));
        $this->assertSame('draft', $draft->record_state);
        $this->assertSame('withdraw', $draft->pending_action);
        $this->assertSame(20, $draft->runs_scored);
        $this->assertSame('Official identity', $draft->player_name);
        $this->assertSame($actor->id, $draft->updated_by);
        $this->assertNull($draft->published_at);
        $this->assertNull($draft->published_by);
        $this->assertSame(1, $draft->version);
        $this->assertSame(1, $match->fresh()->player_stats_revision);
        $same = $service->stageWithdrawal(2026, $match->id, (int) $member->team_id, $member->id, $this->expected($match, $member));
        $this->assertSame(1, $same->version);
        $this->assertSame(1, $match->fresh()->player_stats_revision);
        $this->assertSame($official, $published->fresh()->getAttributes());
        foreach (['version', 'status', 'result_published_at', 'player_stats_published_at'] as $field) {
            $this->assertSame($beforeMatch[$field], $match->fresh()->getAttributes()[$field]);
        }
        $this->assertDatabaseHas('team_members', ['id' => $member->id]);
        $service->discardDraft(2026, $match->id, (int) $member->team_id, $member->id, $this->expected($match, $member));
        $this->assertSame($official, $published->fresh()->getAttributes());
        $this->assertSame(2, $match->fresh()->player_stats_revision);
    }

    public function test_withdrawal_can_replace_a_correction_and_editing_it_stages_a_new_replacement(): void
    {
        [, $match, $member] = $this->publishedFixture();
        $draft = $this->save($match, $member, ['runs_scored' => 30, 'fours' => 3]);
        $withdrawal = app(PlayerStatsService::class)->stageWithdrawal(2026, $match->id, (int) $member->team_id, $member->id, $this->expected($match, $member));
        $this->assertSame($draft->id, $withdrawal->id);
        $this->assertSame(2, $withdrawal->version);
        $this->assertSame(20, $withdrawal->runs_scored);
        $replacement = $this->save($match, $member, ['runs_scored' => 25]);
        $this->assertSame('replace', $replacement->pending_action);
        $this->assertSame(2, $replacement->fours);
        $this->assertSame(3, $replacement->version);
        $this->assertSame(3, $match->fresh()->player_stats_revision);
    }

    public function test_unpublished_draft_cannot_be_staged_for_withdrawal(): void
    {
        [, $match, $member] = $this->fixture();
        $draft = $this->save($match, $member, ['runs_scored' => 1]);
        $before = $draft->getAttributes();
        $this->rejected(fn () => app(PlayerStatsService::class)->stageWithdrawal(2026, $match->id, (int) $member->team_id, $member->id, $this->expected($match, $member)));
        $this->assertSame($before, $draft->fresh()->getAttributes());
        $this->assertSame(1, $match->fresh()->player_stats_revision);
    }

    public static function pendingActions(): array
    {
        return [['discardDraft'], ['stageWithdrawal']];
    }

    #[DataProvider('pendingActions')]
    public function test_pending_actions_reject_stale_expectations_wrong_year_and_revoked_access(string $action): void
    {
        [$actor, $match, $member, $published] = $this->publishedFixture();
        $this->save($match, $member, ['runs_scored' => 30]);
        $old = $this->expected($match, $member);
        $this->save($match, $member, ['runs_scored' => 31]);
        $service = app(PlayerStatsService::class);
        $this->rejected(fn () => $service->$action(2026, $match->id, (int) $member->team_id, $member->id, $old));
        $current = $this->expected($match, $member);
        $this->rejected(fn () => $service->$action(2027, $match->id, (int) $member->team_id, $member->id, $current));
        DB::table('player_match_stats')->where('id', $published->id)->increment('version');
        $this->rejected(fn () => $service->$action(2026, $match->id, (int) $member->team_id, $member->id, $current));
        DB::table('users')->where('id', $actor->id)->update(['is_scorer' => false]);
        try {
            $service->$action(2026, $match->id, (int) $member->team_id, $member->id, $this->expected($match, $member));
            $this->fail('Expected permission denial.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertSame(2, $match->fresh()->player_stats_revision);
        $this->assertDatabaseCount('player_match_stats', 2);
    }

    #[DataProvider('pendingActions')]
    public function test_pending_action_rolls_back_if_revision_update_fails(string $action): void
    {
        [, $match, $member, $published] = $this->publishedFixture();
        $draft = $this->save($match, $member, ['runs_scored' => 30]);
        $before = $draft->getAttributes();
        $official = $published->getAttributes();
        $event = 'eloquent.updating: '.TournamentMatch::class;
        Event::listen($event, fn () => throw new RuntimeException('Synthetic pending-action failure'));
        try {
            app(PlayerStatsService::class)->$action(2026, $match->id, (int) $member->team_id, $member->id, $this->expected($match, $member));
            $this->fail('Expected rollback.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic pending-action failure', $exception->getMessage());
        } finally {
            Event::forget($event);
        }
        $this->assertSame($before, $draft->fresh()->getAttributes());
        $this->assertSame($official, $published->fresh()->getAttributes());
        $this->assertSame(1, $match->fresh()->player_stats_revision);
    }

    public static function injectedFields(): array
    {
        return array_map(fn ($field) => [$field], ['id', 'match_id', 'team_id', 'team_member_id', 'record_state',
            'pending_action', 'published_at', 'published_by', 'updated_by', 'version', 'created_at', 'updated_at',
            'player_name', 'player_gender', 'draft_stats', 'super_over_runs', 'unexpected']);
    }

    #[DataProvider('injectedFields')]
    public function test_client_cannot_inject_private_fields_into_a_correction(string $field): void
    {
        [, $match, $member, $published] = $this->publishedFixture();
        $draft = $this->save($match, $member, ['runs_scored' => 30]);
        $before = $draft->getAttributes();
        $official = $published->getAttributes();
        $this->rejected(fn () => $this->save($match, $member, [$field => 'forged']));
        $this->assertSame($before, $draft->fresh()->getAttributes());
        $this->assertSame($official, $published->fresh()->getAttributes());
        $this->assertSame(1, $match->fresh()->player_stats_revision);
    }

    public static function rowStates(): array
    {
        return [['draft'], ['published']];
    }

    #[DataProvider('rowStates')]
    public function test_database_enforces_one_row_per_state_after_lifecycle_operations(string $state): void
    {
        [, $match, $member, $published] = $this->publishedFixture();
        $draft = $this->save($match, $member, ['runs_scored' => 30]);
        $source = $state === 'draft' ? $draft : $published;
        $duplicate = $source->getAttributes();
        unset($duplicate['id']);
        try {
            // A savepoint lets PostgreSQL recover after the expected constraint error.
            DB::transaction(fn () => DB::table('player_match_stats')->insert($duplicate));
            $this->fail('Expected unique-state constraint rejection.');
        } catch (QueryException $exception) {
            $this->assertStringStartsWith('23', (string) $exception->getCode());
        }
        $this->assertSame(1, PlayerMatchStat::where('match_id', $match->id)->where('team_member_id', $member->id)->where('record_state', $state)->count());
        $this->assertDatabaseCount('player_match_stats', 2);
    }

    #[DataProvider('pendingActions')]
    public function test_discard_and_withdrawal_reject_a_recreated_draft_with_the_old_version_number(string $action): void
    {
        [, $match, $member, $published] = $this->publishedFixture();
        $first = $this->save($match, $member, ['runs_scored' => 30]);
        $stale = $this->expected($match, $member);
        $service = app(PlayerStatsService::class);
        $service->discardDraft(2026, $match->id, (int) $member->team_id, $member->id, $stale);
        $second = $this->save($match, $member, ['runs_scored' => 31]);
        $this->assertSame($first->version, $second->version);
        $this->assertNotSame($first->id, $second->id);
        $before = $second->getAttributes();
        $this->rejected(fn () => $service->$action(2026, $match->id, (int) $member->team_id, $member->id, $stale));
        $this->assertSame($before, $second->fresh()->getAttributes());
        $this->assertSame(20, $published->fresh()->runs_scored);
        $this->assertSame(3, $match->fresh()->player_stats_revision);
    }

    public function test_cross_player_row_expectations_cannot_target_a_different_player(): void
    {
        [, $match, $member] = $this->fixture();
        $other = TeamMember::factory()->create(['team_id' => $member->team_id]);
        $draft = $this->save($match, $member, ['runs_scored' => 10]);
        $otherDraft = $this->save($match, $other, ['runs_scored' => 20]);
        $foreign = $this->expected($match, $other);
        foreach (['saveDraft', 'discardDraft', 'stageWithdrawal'] as $action) {
            $service = app(PlayerStatsService::class);
            $this->rejected(fn () => $action === 'saveDraft'
                ? $service->saveDraft(2026, $match->id, (int) $member->team_id, $member->id, $foreign, ['runs_scored' => 99])
                : $service->$action(2026, $match->id, (int) $member->team_id, $member->id, $foreign));
        }
        $this->assertSame(10, $draft->fresh()->runs_scored);
        $this->assertSame(20, $otherDraft->fresh()->runs_scored);
        $this->assertSame(2, $match->fresh()->player_stats_revision);
    }

    public function test_drafts_and_pending_withdrawals_do_not_change_existing_cumulative_totals_or_registration(): void
    {
        [, $match, $member, $published] = $this->publishedFixture();
        DB::table('player_tournament_stats')->insert(['tournament_year' => 2026, 'team_member_id' => $member->id,
            'team_id' => $member->team_id, 'player_name' => $published->player_name, 'player_gender' => $published->player_gender,
            'runs_scored' => 20, 'runs_scored_recorded_count' => 1, 'highest_score' => 20, 'highest_score_match_id' => $match->id]);
        $summary = DB::table('player_tournament_stats')->where('team_member_id', $member->id)->first();
        $registration = $member->fresh()->getAttributes();
        $this->save($match, $member, ['runs_scored' => 30]);
        $service = app(PlayerStatsService::class);
        $service->stageWithdrawal(2026, $match->id, (int) $member->team_id, $member->id, $this->expected($match, $member));
        $this->assertEquals($summary, DB::table('player_tournament_stats')->where('team_member_id', $member->id)->first());
        $service->discardDraft(2026, $match->id, (int) $member->team_id, $member->id, $this->expected($match, $member));
        $this->assertEquals($summary, DB::table('player_tournament_stats')->where('team_member_id', $member->id)->first());
        $this->assertSame($registration, $member->fresh()->getAttributes());
        $this->assertSame(20, $published->fresh()->runs_scored);
    }

    public static function invalidExpectations(): array
    {
        return [[[]], [['draft' => null]], [['draft' => [], 'published' => null]],
            [['draft' => ['id' => 1], 'published' => null]], [['draft' => null, 'published' => null, 'actor' => 1]],
            [['draft' => ['id' => 1, 'version' => 1, 'extra' => 1], 'published' => null]]];
    }

    #[DataProvider('invalidExpectations')]
    public function test_missing_or_malformed_expectations_cannot_create_rows(array $expected): void
    {
        [, $match, $member] = $this->fixture();
        $this->rejected(fn () => $this->save($match, $member, ['runs_scored' => 1], $expected));
        $this->assertDatabaseCount('player_match_stats', 0);
    }
}
