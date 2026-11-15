<?php

namespace Tests\PostgreSQL;

use App\Models\GroupTeam;
use App\Models\PlayerMatchStat;
use App\Models\PlayerTournamentStat;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TournamentMatch;
use App\Models\User;
use App\Services\Tournament\BracketSlotService;
use App\Services\Tournament\MatchResultService;
use App\Services\Tournament\MatchSchedulingService;
use App\Services\Tournament\PlayerPublicationService;
use App\Services\Tournament\PlayerStatsService;
use App\Services\Tournament\QualificationService;
use App\Services\Tournament\TournamentWriteService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Support\PostgreSqlTestDatabaseSafety;

class KnockoutConcurrencyTest extends PostgreSqlTestCase
{
    use DatabaseMigrations;

    private function scorer(bool $resolve = false): User
    {
        $actor = User::factory()->create(['company_id' => null, 'is_admin' => false]);
        $actor->is_scorer = true;
        $actor->save();
        if ($resolve) {
            $this->actAs($actor);
        }

        return $actor;
    }

    private function fixture(): \Illuminate\Database\Eloquent\Collection
    {
        config(['tournament.enabled' => true, 'tournament.writes_per_minute' => 1000]);
        $this->scorer(true);
        $group = DB::table('tournament_groups')->insertGetId(['name' => 'Race group', 'tournament_year' => 2026]);
        $teams = Team::factory()->count(8)->create(['approved' => true]);
        foreach ($teams as $team) {
            DB::table('group_teams')->insert(['group_id' => $group, 'tournament_year' => 2026, 'team_id' => $team->id, 'qualified' => true]);
        }

        return $teams;
    }

    private function proposal(string $stage, int $position, int $a, int $b): array
    {
        return ['revision' => app(MatchSchedulingService::class)->knockoutOptions(2026, $stage)['revision'],
            'structure' => ['stage' => $stage, 'bracket_position' => $position, 'team1_id' => $a, 'team2_id' => $b, 'balls_per_over' => 4]];
    }

    private function create(array $proposal): TournamentMatch
    {
        return app(MatchSchedulingService::class)->create(2026, 0, $proposal['structure'], [], $proposal['revision']);
    }

    private function review(TournamentMatch $match, bool $reverse = false): array
    {
        $service = app(MatchResultService::class);
        $service->saveDraft(2026, $match->id, $match->fresh()->version, ['result_type' => 'win', 'result_method' => 'normal',
            'winner_id' => $reverse ? $match->team2_id : $match->team1_id, 'batting_first_id' => $match->team1_id,
            'team1_runs' => $reverse ? 90 : 100, 'team1_wickets' => 3, 'team1_overs' => 5, 'team1_balls' => 0,
            'team2_runs' => $reverse ? 100 : 90, 'team2_wickets' => 3, 'team2_overs' => 5, 'team2_balls' => 0]);

        return $service->review(2026, $match->id);
    }

    private function publish(array $review): TournamentMatch
    {
        return app(MatchResultService::class)->publish(2026, $review['match_id'], $review['version'], $review['draft_hash']);
    }

    private function quarters($teams, int $count = 2): array
    {
        $matches = [];
        for ($i = 0; $i < $count; $i++) {
            $match = $this->create($this->proposal('QF', $i + 1, $teams[$i * 2]->id, $teams[$i * 2 + 1]->id));
            $matches[] = $this->publish($this->review($match));
        }

        return $matches;
    }

    private function compete(User $actor, string $action, array $arguments, callable $first): string
    {
        $tag = 'htg-knockout-'.bin2hex(random_bytes(8));
        $code = <<<'PHP'
require 'vendor/autoload.php';
try {
    $test = new class('probe') extends \Tests\PostgreSQL\PostgreSqlTestCase {};
    $test->createApplication();
    config(['tournament.enabled' => true, 'tournament.writes_per_minute' => 1000]);
    \Illuminate\Support\Facades\DB::selectOne("SELECT set_config('application_name', ?, false)", [$argv[4]]);
    request()->attributes->set('user', \App\Models\User::findOrFail((int) $argv[1]));
    $args = json_decode($argv[3], true, flags: JSON_THROW_ON_ERROR);
    switch ($argv[2]) {
        case 'create':
            app(\App\Services\Tournament\MatchSchedulingService::class)->create(2026, 0, $args['structure'], [], $args['revision']);
            break;
        case 'qualification':
            app(\App\Services\Tournament\QualificationService::class)->save(2026, $args['selected'], $args['revision']);
            break;
        case 'move':
            app(\App\Services\Tournament\BracketSlotService::class)->move(2026, $args['match_id'], $args['version'], $args['position'], $args['occupant']);
            break;
        case 'status':
            app(\App\Services\Tournament\MatchSchedulingService::class)->updateSchedule(2026, $args['match_id'], $args['version'], ['status' => $args['status']]);
            break;
        case 'player':
            app(\App\Services\Tournament\PlayerPublicationService::class)->publish(2026, $args['match_id'], $args['version'], $args['player_stats_revision'], $args['receipt']);
            break;
        case 'publish':
            app(\App\Services\Tournament\MatchResultService::class)->publish(2026, $args['match_id'], $args['version'], $args['draft_hash']);
            break;
        default:
            throw new \RuntimeException('Unknown test action.');
    }
    echo 'WROTE';
} catch (\Illuminate\Validation\ValidationException $exception) {
    echo 'REJECTED:'.array_key_first($exception->errors());
} catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
    echo 'DENIED:'.$exception->getStatusCode();
} catch (\Throwable) {
    echo 'UNEXPECTED WORKER FAILURE';
    exit(2);
}
PHP;
        $worker = new Process([PHP_BINARY, '-r', $code, (string) $actor->id, $action, json_encode($arguments, JSON_THROW_ON_ERROR), $tag], base_path());
        $worker->setTimeout(15);
        $observer = PostgreSqlTestDatabaseSafety::connect(config('database.connections.pgsql'));
        DB::beginTransaction();
        try {
            app(TournamentWriteService::class)->run(2026, $first);
            $worker->start();
            $statement = $observer->prepare("SELECT count(*) FROM pg_stat_activity WHERE application_name = ? AND wait_event_type = 'Lock' AND wait_event = 'advisory'");
            $deadline = microtime(true) + 5;
            $waiting = false;
            do {
                $statement->execute([$tag]);
                $waiting = (int) $statement->fetchColumn() === 1;
                if ($waiting || ! $worker->isRunning()) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            $this->assertTrue($waiting, 'The competing service must actually wait for the common year lock.');
            DB::commit();
            $worker->wait();
            $this->assertTrue($worker->isSuccessful(), 'Guarded worker failed.');

            return trim($worker->getOutput());
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            if ($worker->isRunning()) {
                $worker->stop(0);
            }
        }
    }

    public static function upstreamStages(): array
    {
        return [['QF'], ['SF']];
    }

    private function upstream($teams, string $stage): array
    {
        $quarters = $this->quarters($teams, $stage === 'QF' ? 2 : 4);
        if ($stage === 'QF') {
            return $quarters;
        }
        $one = $this->create($this->proposal('SF', 2, $quarters[0]->winner_id, $quarters[1]->winner_id));
        $two = $this->create($this->proposal('SF', 1, $quarters[2]->winner_id, $quarters[3]->winner_id));

        return [$this->publish($this->review($one)), $this->publish($this->review($two))];
    }

    public static function conflictingClaims(): array
    {
        return [['slot'], ['participant']];
    }

    #[DataProvider('conflictingClaims')]
    public function test_waiting_creation_rechecks_occupied_positions_and_both_team_columns(string $claim): void
    {
        $teams = $this->fixture();
        $first = $this->proposal('QF', 1, $teams[0]->id, $teams[1]->id);
        $other = $this->proposal('QF', $claim === 'slot' ? 1 : 2, $teams[2]->id, $teams[$claim === 'slot' ? 3 : 0]->id);
        $this->assertSame('REJECTED:tournament', $this->compete($this->scorer(), 'create', $other, fn () => $this->create($first)));
        $this->assertDatabaseCount('matches', 1);
        $this->assertDatabaseHas('matches', ['stage' => 'QF', 'bracket_position' => 1, 'team1_id' => $teams[0]->id, 'team2_id' => $teams[1]->id]);
    }

    public function test_committed_deselection_prevents_waiting_quarter_creation(): void
    {
        $teams = $this->fixture();
        $proposal = $this->proposal('QF', 1, $teams[0]->id, $teams[1]->id);
        $service = app(QualificationService::class);
        $selection = $service->selection(2026);
        $this->assertSame('REJECTED:tournament', $this->compete($this->scorer(), 'create', $proposal,
            fn () => $service->save(2026, $teams->skip(1)->pluck('id')->all(), $selection['revision'])));
        $this->assertDatabaseCount('matches', 0);
        $this->assertFalse(GroupTeam::where('team_id', $teams[0]->id)->firstOrFail()->qualified);
    }

    public function test_committed_quarter_prevents_waiting_qualification_removal(): void
    {
        $teams = $this->fixture();
        $selection = app(QualificationService::class)->selection(2026);
        $proposal = $this->proposal('QF', 1, $teams[0]->id, $teams[1]->id);
        $arguments = ['selected' => $teams->skip(1)->pluck('id')->all(), 'revision' => $selection['revision']];
        $this->assertSame('REJECTED:tournament', $this->compete($this->scorer(), 'qualification', $arguments, fn () => $this->create($proposal)));
        $this->assertDatabaseCount('matches', 1);
        $this->assertSame(8, GroupTeam::where('qualified', true)->count());
    }

    public function test_waiting_selection_does_not_replace_another_scorers_committed_choices(): void
    {
        $teams = $this->fixture();
        $service = app(QualificationService::class);
        $selection = $service->selection(2026);
        $this->assertSame('REJECTED:tournament', $this->compete($this->scorer(), 'qualification',
            ['selected' => [$teams[1]->id], 'revision' => $selection['revision']],
            fn () => $service->save(2026, [$teams[0]->id], $selection['revision'])));
        $this->assertSame([$teams[0]->id], $service->selection(2026)['selected']);
    }

    #[DataProvider('upstreamStages')]
    public function test_corrected_upstream_winner_prevents_waiting_next_round_creation(string $stage): void
    {
        $teams = $this->fixture();
        [$one, $two] = $this->upstream($teams, $stage);
        $next = $stage === 'QF' ? 'SF' : 'F';
        $correction = $this->review($one, true);
        $proposal = $this->proposal($next, 1, $one->winner_id, $two->winner_id);
        $this->assertSame('REJECTED:tournament', $this->compete($this->scorer(), 'create', $proposal, fn () => $this->publish($correction)));
        $this->assertSame($one->team2_id, $one->fresh()->winner_id);
        $this->assertSame(0, TournamentMatch::where('stage', $next)->count());
    }

    #[DataProvider('upstreamStages')]
    public function test_new_next_round_fixture_blocks_waiting_winner_correction_and_preserves_its_draft(string $stage): void
    {
        $teams = $this->fixture();
        [$one, $two] = $this->upstream($teams, $stage);
        $next = $stage === 'QF' ? 'SF' : 'F';
        $correction = $this->review($one, true);
        $proposal = $this->proposal($next, 1, $two->winner_id, $one->winner_id);
        $this->assertSame('REJECTED:tournament', $this->compete($this->scorer(), 'publish', $correction, fn () => $this->create($proposal)));
        $this->assertSame($one->team1_id, $one->fresh()->winner_id);
        $this->assertSame($one->team2_id, $one->fresh()->draft_result['winner_id']);
        $this->assertSame(1, TournamentMatch::where('stage', $next)->count());
    }

    #[DataProvider('upstreamStages')]
    public function test_new_next_round_fixture_blocks_waiting_status_reversal(string $stage): void
    {
        $teams = $this->fixture();
        [$one, $two] = $this->upstream($teams, $stage);
        $next = $stage === 'QF' ? 'SF' : 'F';
        $proposal = $this->proposal($next, 1, $two->winner_id, $one->winner_id);
        $this->assertSame('REJECTED:tournament', $this->compete($this->scorer(), 'status',
            ['match_id' => $one->id, 'version' => $one->version, 'status' => 'live'], fn () => $this->create($proposal)));
        $this->assertSame('finished', $one->fresh()->status);
        $this->assertNotNull($one->fresh()->result_published_at);
    }

    public function test_overlapping_swaps_reject_stale_target_without_partial_moves(): void
    {
        $teams = $this->fixture();
        [$one, $two, $three] = $this->quarters($teams, 3);
        $this->assertSame('REJECTED:tournament', $this->compete($this->scorer(), 'move',
            ['match_id' => $three->id, 'version' => $three->version, 'position' => 2, 'occupant' => ['id' => $two->id, 'version' => $two->version]],
            fn () => app(BracketSlotService::class)->move(2026, $one->id, $one->version, 2, ['id' => $two->id, 'version' => $two->version])));
        $this->assertSame([2, 1, 3], TournamentMatch::orderBy('id')->pluck('bracket_position')->all());
        $this->assertSame([$one->team1_id, $two->team1_id, $three->team1_id], TournamentMatch::orderBy('id')->pluck('winner_id')->all());
    }

    public function test_disjoint_position_changes_both_commit_without_losing_results(): void
    {
        $teams = $this->fixture();
        [$one, $two, $three] = $this->quarters($teams, 3);
        $this->assertSame('WROTE', $this->compete($this->scorer(), 'move',
            ['match_id' => $three->id, 'version' => $three->version, 'position' => 4, 'occupant' => null],
            fn () => app(BracketSlotService::class)->move(2026, $one->id, $one->version, 2, ['id' => $two->id, 'version' => $two->version])));
        $this->assertSame([2, 1, 4], TournamentMatch::orderBy('id')->pluck('bracket_position')->all());
        $this->assertSame([$one->winner_id, $two->winner_id, $three->winner_id], TournamentMatch::orderBy('id')->pluck('winner_id')->all());
    }

    public static function revokedWrites(): array
    {
        return [['create'], ['move']];
    }

    #[DataProvider('revokedWrites')]
    public function test_waiting_knockout_write_rechecks_revoked_permission(string $action): void
    {
        $teams = $this->fixture();
        if ($action === 'create') {
            $arguments = $this->proposal('QF', 1, $teams[0]->id, $teams[1]->id);
        } else {
            [$match] = $this->quarters($teams, 1);
            $arguments = ['match_id' => $match->id, 'version' => $match->version, 'position' => 4, 'occupant' => null];
        }
        $actor = $this->scorer();
        $this->assertSame('DENIED:403', $this->compete($actor, $action, $arguments,
            fn () => DB::table('users')->where('id', $actor->id)->update(['is_scorer' => false])));
        $this->assertDatabaseCount('matches', $action === 'create' ? 0 : 1);
        if ($action === 'move') {
            $this->assertSame(1, $match->fresh()->bracket_position);
        }
    }

    public function test_reopening_knockout_invalidates_waiting_player_publication_without_promoting_drafts(): void
    {
        $teams = $this->fixture();
        [$match] = $this->quarters($teams, 1);
        $member = TeamMember::factory()->create(['team_id' => $match->team1_id]);
        app(PlayerStatsService::class)->saveDraft(2026, $match->id, $member->team_id, $member->id,
            ['draft' => null, 'published' => null], ['runs_scored' => 40]);
        $review = app(PlayerPublicationService::class)->review(2026, $match->id);
        $this->assertSame('REJECTED:player', $this->compete($this->scorer(), 'player', $review,
            fn () => app(MatchSchedulingService::class)->updateSchedule(2026, $match->id, $review['version'], ['status' => 'live'])));
        $this->assertSame('live', $match->fresh()->status);
        $this->assertDatabaseCount('player_tournament_stats', 0);
        $this->assertDatabaseHas('player_match_stats', ['match_id' => $match->id, 'record_state' => 'draft', 'runs_scored' => 40]);
        $this->assertSame(0, PlayerMatchStat::where('record_state', 'published')->count());
    }

    public function test_player_publication_invalidates_waiting_reopen_and_keeps_committed_totals(): void
    {
        $teams = $this->fixture();
        [$match] = $this->quarters($teams, 1);
        $member = TeamMember::factory()->create(['team_id' => $match->team1_id]);
        app(PlayerStatsService::class)->saveDraft(2026, $match->id, $member->team_id, $member->id,
            ['draft' => null, 'published' => null], ['runs_scored' => 40]);
        $publisher = app(PlayerPublicationService::class);
        $review = $publisher->review(2026, $match->id);
        $this->assertSame('REJECTED:tournament', $this->compete($this->scorer(), 'status',
            ['match_id' => $match->id, 'version' => $review['version'], 'status' => 'live'],
            fn () => $publisher->publish(2026, $match->id, $review['version'], $review['player_stats_revision'], $review['receipt'])));
        $this->assertSame('finished', $match->fresh()->status);
        $this->assertSame(40, PlayerTournamentStat::firstOrFail()->runs_scored);
        $this->assertSame(0, PlayerMatchStat::where('record_state', 'draft')->count());
    }
}
