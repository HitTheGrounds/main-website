<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TournamentMatch;
use App\Models\User;
use App\Services\Tournament\PlayerStatsContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PlayerStatsContextTest extends TestCase
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

    private function enter(TournamentMatch $match, TeamMember $member, bool $publication = false): array
    {
        return app(PlayerStatsContext::class)->withMember(2026, $match->id, (int) $member->team_id, $member->id,
            fn ($locked, $player, $actor) => [$locked->id, $player->name, $player->gender, $actor->id, DB::transactionLevel()], $publication);
    }

    public function test_context_uses_current_locked_registration_identity_and_never_stores_figures(): void
    {
        [$actor, $match, $member] = $this->fixture();
        DB::table('team_members')->where('id', $member->id)->update(['name' => 'Current name']);
        $revision = $match->player_stats_revision;
        $context = $this->enter($match, $member);
        $this->assertSame([$match->id, 'Current name', 'Female', $actor->id], array_slice($context, 0, 4));
        $this->assertGreaterThan(1, $context[4]);
        $this->assertDatabaseCount('player_match_stats', 0);
        $this->assertDatabaseCount('player_tournament_stats', 0);
        $this->assertSame($revision, $match->fresh()->player_stats_revision);
    }

    public static function publicationStates(): array
    {
        return [['upcoming', null, null, false], ['live', null, null, false],
            ['finished', 'win', null, false], ['finished', 'no_result', 'now', false],
            ['finished', 'abandoned', 'now', false], ['finished', 'win', 'now', true], ['finished', 'tie', 'now', true]];
    }

    #[DataProvider('publicationStates')]
    public function test_drafts_remain_separate_from_publication_eligibility(string $status, ?string $type, ?string $published, bool $eligible): void
    {
        [, $match, $member] = $this->fixture();
        DB::table('matches')->where('id', $match->id)->update(['status' => $status, 'result_type' => $type,
            'result_published_at' => $published ? now() : null, 'winner_id' => $type === 'win' ? $match->team1_id : null]);
        $this->assertSame($match->id, $this->enter($match, $member)[0]);
        if (! $eligible) {
            $this->expectException(ValidationException::class);
        }
        $this->assertSame($match->id, $this->enter($match, $member, true)[0]);
    }

    public function test_wrong_year_team_player_and_changed_assignment_are_rejected_before_callback(): void
    {
        [, $match, $member] = $this->fixture();
        $other = Team::factory()->create(['approved' => true]);
        $called = false;
        foreach ([[2027, (int) $member->team_id, $member->id], [2026, $other->id, $member->id],
            [2026, $match->team2_id, $member->id], [2026, (int) $member->team_id, 999999]] as [$year, $teamId, $id]) {
            try {
                app(PlayerStatsContext::class)->withMember($year, $match->id, $teamId, $id, function () use (&$called) {
                    $called = true;
                });
                $this->fail('Expected invalid membership rejection.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
        DB::table('group_teams')->where('team_id', $member->team_id)->delete();
        try {
            $this->enter($match, $member);
            $this->fail('Expected removed assignment rejection.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('player', $exception->errors());
        }
        $this->assertFalse($called);
    }

    public function test_unapproved_team_moved_player_and_unavailable_knockout_are_rejected(): void
    {
        [, $match, $member] = $this->fixture();
        $other = Team::factory()->create(['approved' => true]);
        foreach (['unapproved', 'moved', 'knockout'] as $scenario) {
            if ($scenario === 'unapproved') {
                DB::table('teams')->where('id', $member->team_id)->update(['approved' => false]);
            } elseif ($scenario === 'moved') {
                DB::table('teams')->where('id', $member->team_id)->update(['approved' => true]);
                DB::table('team_members')->where('id', $member->id)->update(['team_id' => $other->id]);
            } else {
                DB::table('matches')->where('id', $match->id)->update(['stage' => 'QF', 'group_id' => null, 'bracket_position' => 1]);
            }
            try {
                $this->enter($match, $member);
                $this->fail('Expected eligibility rejection.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
        $this->assertDatabaseCount('player_match_stats', 0);
    }

    public static function roles(): array
    {
        return [['guest'], ['admin'], ['representative'], ['pending'], ['revoked'], ['disabled']];
    }

    #[DataProvider('roles')]
    public function test_context_rechecks_current_feature_and_scorer_permission(string $role): void
    {
        [$actor, $match, $member] = $this->fixture();
        if ($role === 'guest') {
            request()->attributes->remove('user');
        } elseif ($role === 'revoked') {
            DB::table('users')->where('id', $actor->id)->update(['is_scorer' => false]);
        } elseif ($role === 'disabled') {
            config(['tournament.enabled' => false]);
        } else {
            request()->attributes->set('user', User::factory()->create(['is_admin' => $role === 'admin',
                'company_id' => $role === 'representative' ? Company::factory()->create()->id : null]));
        }
        try {
            $this->enter($match, $member);
            $this->fail('Expected authorization denial.');
        } catch (HttpException $exception) {
            $this->assertSame($role === 'disabled' ? 404 : 403, $exception->getStatusCode());
        }
    }
}
