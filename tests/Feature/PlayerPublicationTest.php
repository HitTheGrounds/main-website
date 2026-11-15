<?php

namespace Tests\Feature;

use App\Models\PlayerMatchStat;
use App\Models\PlayerTournamentStat;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TournamentMatch;
use App\Models\User;
use App\Services\Tournament\MatchResultService;
use App\Services\Tournament\PlayerPublicationService;
use App\Services\Tournament\PlayerStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PlayerPublicationTest extends TestCase
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

    private function ready(): array
    {
        [$actor, $match, $member] = $this->fixture();
        $results = app(MatchResultService::class);
        $results->saveDraft(2026, $match->id, 1, ['result_type' => 'win', 'result_method' => 'normal', 'winner_id' => $match->team1_id,
            'batting_first_id' => $match->team1_id, 'team1_runs' => 100, 'team1_wickets' => 3, 'team1_overs' => 5,
            'team1_balls' => 0, 'team2_runs' => 90, 'team2_wickets' => 11, 'team2_overs' => 3, 'team2_balls' => 0]);
        $review = $results->review(2026, $match->id);
        $results->publish(2026, $match->id, $review['version'], $review['draft_hash']);

        return [$actor, $match->fresh(), $member];
    }

    private function review(TournamentMatch $match): array
    {
        return app(PlayerPublicationService::class)->review(2026, $match->id);
    }

    private function publish(array $review): TournamentMatch
    {
        return app(PlayerPublicationService::class)->publish(2026, $review['match_id'], $review['version'], $review['player_stats_revision'], $review['receipt']);
    }

    private function denied(callable $action): void
    {
        try {
            $action();
            $this->fail('Expected publication rejection.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }

    public function test_review_is_read_only_and_one_publication_updates_both_teams_with_partial_figures(): void
    {
        [$actor, $match, $member] = $this->ready();
        $other = TeamMember::factory()->create(['team_id' => $match->team2_id]);
        $unentered = TeamMember::factory()->create(['team_id' => $member->team_id]);
        $this->save($match, $member, ['runs_scored' => 0]);
        $this->save($match, $other, ['wickets_taken' => 2, 'dot_balls' => 6]);
        $before = PlayerMatchStat::orderBy('id')->get()->map->getAttributes()->all();
        $budget = RateLimiter::attempts('tournament:scorer-write:'.$actor->id);
        $review = $this->review($match);
        $this->assertCount(2, $review['changes']);
        $this->assertContains('balls_faced', $review['changes'][0]['missing_metrics']);
        $this->assertSame($budget, RateLimiter::attempts('tournament:scorer-write:'.$actor->id));
        $this->assertSame($before, PlayerMatchStat::orderBy('id')->get()->map->getAttributes()->all());
        $published = $this->publish($review);
        $this->assertSame($review['player_stats_revision'] + 1, $published->player_stats_revision);
        $this->assertSame($review['version'] + 1, $published->version);
        $this->assertNotNull($published->player_stats_published_at);
        $this->assertSame(0, PlayerMatchStat::where('record_state', 'draft')->count());
        $this->assertSame(2, PlayerMatchStat::where('record_state', 'published')->count());
        $row = PlayerMatchStat::where('team_member_id', $member->id)->firstOrFail();
        $this->assertSame(1, $row->version);
        $this->assertSame($actor->id, $row->published_by);
        $this->assertNull($row->pending_action);
        $this->assertSame(0, PlayerTournamentStat::where('team_member_id', $member->id)->firstOrFail()->runs_scored);
        $this->assertNull(PlayerTournamentStat::where('team_member_id', $other->id)->firstOrFail()->runs_scored);
        $this->assertSame(6, PlayerTournamentStat::where('team_member_id', $other->id)->firstOrFail()->dot_balls);
        $this->assertSame(0, PlayerMatchStat::where('team_member_id', $unentered->id)->count());
        $this->denied(fn () => $this->publish($review));
        $this->assertDatabaseCount('player_tournament_stats', 2);
    }

    public function test_correction_updates_existing_official_row_then_withdrawal_removes_only_feature_performance(): void
    {
        [, $match, $member] = $this->ready();
        $this->save($match, $member, ['runs_scored' => 40]);
        $this->publish($this->review($match));
        $official = PlayerMatchStat::where('team_member_id', $member->id)->firstOrFail();
        $this->save($match, $member, ['runs_scored' => 20]);
        $this->assertSame(40, PlayerTournamentStat::where('team_member_id', $member->id)->firstOrFail()->runs_scored);
        $this->publish($this->review($match));
        $this->assertSame(2, $official->fresh()->version);
        $this->assertSame(20, $official->fresh()->runs_scored);
        $this->assertSame(20, PlayerTournamentStat::where('team_member_id', $member->id)->firstOrFail()->highest_score);
        app(PlayerStatsService::class)->stageWithdrawal(2026, $match->id, (int) $member->team_id, $member->id, $this->expected($match, $member));
        $this->assertNotNull($official->fresh());
        $review = $this->review($match);
        $this->assertSame('withdraw', $review['changes'][0]['draft']['pending_action']);
        $this->publish($review);
        $this->assertDatabaseCount('player_match_stats', 0);
        $this->assertDatabaseCount('player_tournament_stats', 0);
        $this->assertDatabaseHas('team_members', ['id' => $member->id]);
    }

    public static function changedDrafts(): array
    {
        return [['edit'], ['new player'], ['discard'], ['recreate'], ['schedule']];
    }

    #[DataProvider('changedDrafts')]
    public function test_changes_after_review_cannot_be_silently_published(string $change): void
    {
        [, $match, $member] = $this->ready();
        $this->save($match, $member, ['runs_scored' => 10]);
        $review = $this->review($match);
        if ($change === 'edit') {
            $this->save($match, $member, ['runs_scored' => 11]);
        } elseif ($change === 'new player') {
            $other = TeamMember::factory()->create(['team_id' => $member->team_id]);
            $this->save($match, $other, ['runs_scored' => 0]);
        } elseif ($change === 'schedule') {
            DB::table('matches')->where('id', $match->id)->increment('version');
        } else {
            app(PlayerStatsService::class)->discardDraft(2026, $match->id, (int) $member->team_id, $member->id, $this->expected($match, $member));
            if ($change === 'recreate') {
                $this->save($match, $member, ['runs_scored' => 10]);
            }
        }
        $this->denied(fn () => $this->publish($review));
        $this->assertSame(0, PlayerMatchStat::where('record_state', 'published')->count());
        $this->assertDatabaseCount('player_tournament_stats', 0);
    }

    public function test_receipt_rejects_changed_published_row_and_forged_hash(): void
    {
        [, $match, $member] = $this->ready();
        $this->save($match, $member, ['runs_scored' => 10]);
        $this->publish($this->review($match));
        $this->save($match, $member, ['runs_scored' => 12]);
        $review = $this->review($match);
        $forged = array_replace($review, ['receipt' => str_repeat('0', 64)]);
        $this->denied(fn () => $this->publish($forged));
        DB::table('player_match_stats')->where('record_state', 'published')->increment('version');
        $this->denied(fn () => $this->publish($review));
        $this->assertSame(10, PlayerTournamentStat::where('team_member_id', $member->id)->firstOrFail()->runs_scored);
    }

    public function test_failure_during_summary_write_restores_performances_drafts_and_match_metadata(): void
    {
        [, $match, $member] = $this->ready();
        $this->save($match, $member, ['runs_scored' => 10]);
        $review = $this->review($match);
        $before = PlayerMatchStat::orderBy('id')->get()->map->getAttributes()->all();
        $metadata = $match->fresh()->getAttributes();
        $event = 'eloquent.created: '.PlayerTournamentStat::class;
        Event::listen($event, fn () => throw new RuntimeException('Synthetic publication failure'));
        try {
            $this->publish($review);
            $this->fail('Expected rollback.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic publication failure', $exception->getMessage());
        } finally {
            Event::forget($event);
        }
        $this->assertSame($before, PlayerMatchStat::orderBy('id')->get()->map->getAttributes()->all());
        $this->assertSame($metadata, $match->fresh()->getAttributes());
        $this->assertDatabaseCount('player_tournament_stats', 0);
        $this->publish($review); // The retained review/draft remains recoverable after rollback.
        $this->assertDatabaseCount('player_tournament_stats', 1);
    }

    public static function ineligibleResults(): array
    {
        return [[['status' => 'live']], [['result_published_at' => null]],
            [['result_type' => 'abandoned', 'result_method' => null, 'winner_id' => null]],
            [['result_type' => 'no_result', 'result_method' => null, 'winner_id' => null]]];
    }

    #[DataProvider('ineligibleResults')]
    public function test_changed_match_eligibility_blocks_review_and_publication_without_losing_drafts(array $changes): void
    {
        [, $match, $member] = $this->ready();
        $this->save($match, $member, ['runs_scored' => 10]);
        $review = $this->review($match);
        DB::table('matches')->where('id', $match->id)->update($changes);
        $this->denied(fn () => $this->review($match));
        $this->denied(fn () => $this->publish($review));
        $this->assertSame(1, PlayerMatchStat::where('record_state', 'draft')->count());
        $this->assertDatabaseCount('player_tournament_stats', 0);
    }

    public static function correctedActions(): array
    {
        return [['correction'], ['withdrawal']];
    }

    #[DataProvider('correctedActions')]
    public function test_failed_correction_or_withdrawal_restores_official_values_and_pending_changes(string $action): void
    {
        [, $match, $member] = $this->ready();
        $this->save($match, $member, ['runs_scored' => 40]);
        $this->publish($this->review($match));
        if ($action === 'correction') {
            $this->save($match, $member, ['runs_scored' => 20]);
        } else {
            app(PlayerStatsService::class)->stageWithdrawal(2026, $match->id, (int) $member->team_id, $member->id, $this->expected($match, $member));
        }
        $review = $this->review($match);
        $before = PlayerMatchStat::orderBy('id')->get()->map->getAttributes()->all();
        $summary = PlayerTournamentStat::firstOrFail()->getAttributes();
        $event = 'eloquent.'.($action === 'correction' ? 'updated' : 'deleted').': '.PlayerTournamentStat::class;
        Event::listen($event, fn () => throw new RuntimeException('Synthetic later publication failure'));
        try {
            $this->publish($review);
            $this->fail('Expected transactional rollback.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic later publication failure', $exception->getMessage());
        } finally {
            Event::forget($event);
        }
        $this->assertSame($before, PlayerMatchStat::orderBy('id')->get()->map->getAttributes()->all());
        $this->assertSame($summary, PlayerTournamentStat::firstOrFail()->getAttributes());
    }

    public function test_membership_and_edition_gender_changes_are_rechecked_before_publication(): void
    {
        [, $match, $member] = $this->ready();
        $this->save($match, $member, ['runs_scored' => 10]);
        $review = $this->review($match);
        DB::table('team_members')->where('id', $member->id)->update(['team_id' => $match->team2_id]);
        $this->denied(fn () => $this->publish($review));
        DB::table('team_members')->where('id', $member->id)->update(['team_id' => $match->team1_id]);
        $opponent = Team::factory()->create(['approved' => true]);
        $historyMatch = DB::table('matches')->insertGetId(['tournament_year' => 2026, 'stage' => 'G', 'group_id' => $match->group_id,
            'team1_id' => $match->team1_id, 'team2_id' => $opponent->id, 'balls_per_over' => 4]);
        // Even temporarily ineligible published history retains its edition classification.
        DB::table('player_match_stats')->insert(['match_id' => $historyMatch, 'team_id' => $match->team1_id,
            'team_member_id' => $member->id, 'player_name' => 'Historical identity', 'player_gender' => 'Male',
            'record_state' => 'published', 'pending_action' => null, 'published_at' => now(), 'runs_scored' => 5]);
        $this->denied(fn () => $this->review($match));
        $this->denied(fn () => $this->publish($review));
        $this->assertSame(1, PlayerMatchStat::where('record_state', 'draft')->count());
        $this->assertDatabaseCount('player_tournament_stats', 0);
    }

    public static function rejectedRoles(): array
    {
        return [['guest'], ['admin'], ['representative'], ['pending'], ['revoked'], ['disabled']];
    }

    #[DataProvider('rejectedRoles')]
    public function test_review_and_publish_recheck_current_permission(string $role): void
    {
        [$actor, $match, $member] = $this->ready();
        $this->save($match, $member, ['runs_scored' => 10]);
        $review = $this->review($match);
        if ($role === 'guest') {
            request()->attributes->remove('user');
        } elseif ($role === 'revoked') {
            DB::table('users')->where('id', $actor->id)->update(['is_scorer' => false]);
        } elseif ($role === 'disabled') {
            config(['tournament.enabled' => false]);
        } else {
            request()->attributes->set('user', User::factory()->create(['is_admin' => $role === 'admin',
                'company_id' => $role === 'representative' ? \App\Models\Company::factory()->create()->id : null]));
        }
        foreach ([fn () => $this->review($match), fn () => $this->publish($review)] as $action) {
            try {
                $action();
                $this->fail('Expected access denial.');
            } catch (HttpException $exception) {
                $this->assertSame($role === 'disabled' ? 404 : 403, $exception->getStatusCode());
            }
        }
        $this->assertSame(1, PlayerMatchStat::where('record_state', 'draft')->count());
        $this->assertDatabaseCount('player_tournament_stats', 0);
    }
}
