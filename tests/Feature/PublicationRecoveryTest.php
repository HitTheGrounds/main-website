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
use App\Services\Tournament\PlayerPublicationService;
use App\Services\Tournament\PlayerStatsService;
use App\Services\Tournament\TournamentGroupService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class PublicationRecoveryTest extends TestCase
{
    // Publication must really commit, without an outer test transaction.
    use DatabaseMigrations;

    private function fixture(): array
    {
        config(['tournament.enabled' => true, 'tournament.year' => 2026, 'tournament.writes_per_minute' => 1000]);
        $scorer = User::factory()->create(['is_admin' => false, 'company_id' => null]);
        $scorer->is_scorer = true;
        $scorer->save();
        $this->actAs($scorer);
        $teams = Team::factory()->count(2)->create(['approved' => true]);
        $groups = app(TournamentGroupService::class);
        $group = $groups->create(2026, 'Recovery group');
        foreach ($teams as $team) {
            $groups->assign(2026, $group->id, $group->fresh()->version, $team->id);
        }
        $match = app(MatchSchedulingService::class)->create(2026, $group->fresh()->version, [
            'stage' => 'G', 'group_id' => $group->id, 'team1_id' => $teams[0]->id,
            'team2_id' => $teams[1]->id, 'balls_per_over' => 4,
        ]);
        $member = TeamMember::factory()->create(['team_id' => $teams[0]->id]);

        return [$match, $member];
    }

    private function teamReview(TournamentMatch $match): array
    {
        $results = app(MatchResultService::class);
        $results->saveDraft(2026, $match->id, $match->fresh()->version, [
            'result_type' => 'win', 'result_method' => 'normal', 'winner_id' => $match->team1_id,
            'batting_first_id' => $match->team1_id, 'team1_runs' => 100, 'team1_wickets' => 3,
            'team1_overs' => 5, 'team1_balls' => 0, 'team2_runs' => 90, 'team2_wickets' => 3,
            'team2_overs' => 5, 'team2_balls' => 0,
        ]);

        return $results->review(2026, $match->id);
    }

    private function publishTeam(array $review): void
    {
        app(MatchResultService::class)->publish(2026, $review['match_id'], $review['version'], $review['draft_hash']);
    }

    private function playerReview(TournamentMatch $match, TeamMember $member): array
    {
        app(PlayerStatsService::class)->saveDraft(2026, $match->id, $member->team_id, $member->id,
            ['draft' => null, 'published' => null], ['runs_scored' => 35]);

        return app(PlayerPublicationService::class)->review(2026, $match->id);
    }

    private function publishPlayers(array $review): void
    {
        app(PlayerPublicationService::class)->publish(2026, $review['match_id'], $review['version'],
            $review['player_stats_revision'], $review['receipt']);
    }

    private function snapshot(): array
    {
        $snapshot = [];
        foreach (['users', 'teams', 'team_members', 'tournament_groups', 'group_teams',
            'matches', 'player_match_stats', 'player_tournament_stats'] as $table) {
            $snapshot[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }

        return $snapshot;
    }

    public static function playerFailurePoints(): array
    {
        return [
            'after official performance write' => ['created', PlayerMatchStat::class],
            'before summary insert' => ['creating', PlayerTournamentStat::class],
            'after summary insert' => ['created', PlayerTournamentStat::class],
            'after publication metadata write' => ['updated', TournamentMatch::class],
        ];
    }

    #[DataProvider('playerFailurePoints')]
    public function test_injected_player_failure_restores_all_rows_and_same_review_can_be_retried(string $phase, string $model): void
    {
        [$match, $member] = $this->fixture();
        $this->publishTeam($this->teamReview($match));
        $review = $this->playerReview($match, $member);
        $before = $this->snapshot();
        $event = 'eloquent.'.$phase.': '.$model;
        $injected = 0;
        Event::listen($event, function () use (&$injected) {
            $injected++;
            throw new RuntimeException('Synthetic publication boundary failure');
        });
        try {
            $this->publishPlayers($review);
            $this->fail('Expected the injected publication failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic publication boundary failure', $exception->getMessage());
        } finally {
            Event::forget($event);
        }
        $this->assertSame(1, $injected);
        $this->assertSame(0, DB::transactionLevel());
        $this->assertSame($before, $this->snapshot());
        $this->assertSame(1, PlayerMatchStat::where('record_state', 'draft')->count());
        $this->publishPlayers($review);
        $this->assertSame(0, DB::transactionLevel());
        $this->assertSame(0, PlayerMatchStat::where('record_state', 'draft')->count());
        $this->assertSame(1, PlayerMatchStat::where('record_state', 'published')->count());
        $total = PlayerTournamentStat::where('team_member_id', $member->id)->firstOrFail();
        $this->assertSame(35, $total->runs_scored);
        $this->assertSame(35, $total->highest_score);
        $this->assertSame(1, $total->runs_scored_recorded_count);
    }

    public static function publicationKinds(): array
    {
        return [['team'], ['player']];
    }

    #[DataProvider('publicationKinds')]
    public function test_retry_after_unobserved_commit_does_not_double_count_and_rebuild_agrees(string $kind): void
    {
        [$match, $member] = $this->fixture();
        $review = $this->teamReview($match);
        if ($kind === 'player') {
            $this->publishTeam($review);
            $review = $this->playerReview($match, $member);
        }
        $publish = $kind === 'team' ? fn () => $this->publishTeam($review) : fn () => $this->publishPlayers($review);
        // Model a lost response: allow the real commit, ignore its return value,
        // then resubmit the original request without fetching a fresh review.
        $publish();
        $this->assertSame(0, DB::transactionLevel());
        $committed = $this->snapshot();
        try {
            $publish();
            $this->fail('The already consumed publication review must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($kind === 'team' ? 'tournament' : 'player', $exception->errors());
        }
        $this->assertSame($committed, $this->snapshot());
        $standing = GroupTeam::where('team_id', $match->team1_id)->firstOrFail();
        $this->assertSame(1, $standing->played);
        $this->assertSame(2, $standing->points);
        $this->assertSame('2.000000', $standing->nrr);
        if ($kind === 'player') {
            $this->assertSame(35, PlayerTournamentStat::firstOrFail()->runs_scored);
            $this->assertSame(1, PlayerTournamentStat::firstOrFail()->runs_scored_recorded_count);
        }
        $this->travel(2)->minutes();
        foreach (range(1, 2) as $attempt) {
            $this->artisan('tournament:rebuild-summaries', ['year' => '2026', '--apply' => true])->assertExitCode(0);
            $this->assertSame($committed, $this->snapshot());
        }
    }
}
