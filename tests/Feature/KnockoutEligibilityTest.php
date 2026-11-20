<?php

namespace Tests\Feature;

use App\Models\GroupTeam;
use App\Models\Team;
use App\Models\TournamentMatch;
use App\Services\Tournament\KnockoutEligibility;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class KnockoutEligibilityTest extends TestCase
{
    private function teams(): array
    {
        $teams = $assignments = [];
        for ($id = 1; $id <= 8; $id++) {
            $teams[] = (new Team)->forceFill(['id' => $id, 'team_name' => 'Team '.$id, 'approved' => true]);
            $assignments[] = (new GroupTeam)->forceFill(['id' => $id, 'team_id' => $id, 'group_id' => $id <= 4 ? 1 : 2,
                'tournament_year' => 2026, 'qualified' => true]);
        }

        return [$teams, $assignments];
    }

    private function match(int $id, string $stage, int $a, int $b, int $winner, int $slot = 1, array $changes = []): TournamentMatch
    {
        return (new TournamentMatch)->forceFill(array_replace(['id' => $id, 'tournament_year' => 2026, 'stage' => $stage,
            'group_id' => null, 'bracket_position' => $slot, 'team1_id' => $a, 'team2_id' => $b,
            'status' => 'finished', 'result_type' => 'win', 'winner_id' => $winner, 'result_published_at' => now()], $changes));
    }

    private function quarters(): array
    {
        return [$this->match(11, 'QF', 1, 2, 1, 1), $this->match(12, 'QF', 3, 4, 3, 2),
            $this->match(13, 'QF', 5, 6, 5, 3), $this->match(14, 'QF', 7, 8, 7, 4)];
    }

    public function test_playoff_quarters_combine_group_qualifiers_and_published_winners_while_disabled_rounds_keep_group_rules(): void
    {
        [$teams, $assignments] = $this->teams();
        foreach (array_slice($assignments, 2) as $row) {
            $row->qualified = false;
        }
        $matches = [$this->match(11, 'PO', 3, 4, 3),
            $this->match(12, 'PO', 5, 6, 5, 1, ['result_published_at' => null]),
            $this->match(13, 'PO', 7, 8, 7, 1, ['tournament_year' => 2025])];
        $engine = app(KnockoutEligibility::class);
        $this->assertSame([1, 2, 3], array_column($engine->candidates(2026, 'QF', $teams, $assignments, $matches, true), 'id'));
        $this->assertSame([1, 2], array_column($engine->candidates(2026, 'QF', $teams, $assignments, $matches, false), 'id'));
        $this->assertSame([1, 2], array_column($engine->candidates(2026, 'PO', $teams, $assignments, $matches, true), 'id'));
    }

    private function rejected(callable $action): void
    {
        try {
            $action();
            $this->fail('Expected ineligible matchup rejection.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }

    public function test_quarter_pairings_are_manual_and_allow_same_group_rematches_with_partial_qualification(): void
    {
        [$teams, $assignments] = $this->teams();
        foreach (array_slice($assignments, 2) as $row) {
            $row->qualified = false;
        }
        $engine = app(KnockoutEligibility::class);
        $pair = $engine->pair(2026, 'QF', 2, 1, $teams, $assignments, []);
        $this->assertSame(2, $pair['team1']['id']);
        $this->assertSame(1, $pair['team2']['id']);
        $this->assertNull($pair['team1']['source_match_id']);
        $this->assertCount(2, $engine->candidates(2026, 'QF', $teams, $assignments, []));
        $this->rejected(fn () => $engine->pair(2026, 'QF', 1, 3, $teams, $assignments, []));
        $this->rejected(fn () => $engine->pair(2026, 'QF', 1, 1, $teams, $assignments, []));
    }

    public function test_semis_and_final_follow_actual_selected_winners_without_a_feeder_map(): void
    {
        [$teams, $assignments] = $this->teams();
        $engine = app(KnockoutEligibility::class);
        $matches = $this->quarters();
        // QF4 winner plays QF1 winner; no fixed first/second-QF semifinal rule.
        $pair = $engine->pair(2026, 'SF', 7, 1, $teams, $assignments, $matches);
        $this->assertSame(14, $pair['team1']['source_match_id']);
        $this->assertSame(11, $pair['team2']['source_match_id']);
        $matches[] = $this->match(21, 'SF', 7, 1, 7, 2);
        $matches[] = $this->match(22, 'SF', 3, 5, 5, 1);
        $final = $engine->pair(2026, 'F', 5, 7, $teams, $assignments, $matches);
        $this->assertSame(22, $final['team1']['source_match_id']);
        $this->assertSame(21, $final['team2']['source_match_id']);
        $round = $engine->round(2026, 'SF', $teams, $assignments, $matches);
        $this->assertSame(12, $round[0]['team1']['source_match_id']);
        $this->assertSame(13, $round[0]['team2']['source_match_id']);
        $this->assertSame(14, $round[1]['team1']['source_match_id']);
        $this->assertSame(11, $round[1]['team2']['source_match_id']);
    }

    public static function unfinishedSources(): array
    {
        return [[['status' => 'upcoming']], [['status' => 'live']], [['result_published_at' => null]],
            [['result_type' => 'tie', 'winner_id' => null]], [['result_type' => 'no_result', 'winner_id' => null]],
            [['result_type' => 'abandoned', 'winner_id' => null]], [['tournament_year' => 2025]], [['stage' => 'G']]];
    }

    #[DataProvider('unfinishedSources')]
    public function test_only_same_year_published_finished_preceding_round_wins_advance(array $changes): void
    {
        [$teams, $assignments] = $this->teams();
        $matches = [$this->match(11, 'QF', 1, 2, 1, 1, $changes)];
        // Draft winner fields never count, even if supplied by a loaded model.
        $matches[0]->draft_result = ['result_type' => 'win', 'winner_id' => 2];
        $engine = app(KnockoutEligibility::class);
        $this->assertSame([], $engine->candidates(2026, 'SF', $teams, $assignments, $matches));
        $this->rejected(fn () => $engine->pair(2026, 'SF', 1, 2, $teams, $assignments, $matches));
        $this->assertSame([], $engine->candidates(2026, 'F', $teams, $assignments, $this->quarters()));
    }

    public function test_unapproved_unassigned_and_other_year_teams_are_not_candidates(): void
    {
        [$teams, $assignments] = $this->teams();
        $teams[0]->approved = false;
        $assignments[1]->tournament_year = 2025;
        unset($assignments[2]);
        $engine = app(KnockoutEligibility::class);
        $this->assertSame([4, 5, 6, 7, 8], array_column($engine->candidates(2026, 'QF', $teams, $assignments, []), 'id'));
        $this->assertSame([5, 7], array_column($engine->candidates(2026, 'SF', $teams, $assignments, $this->quarters()), 'id'));
    }

    public function test_empty_round_positions_have_no_invented_participants_or_connections(): void
    {
        [$teams, $assignments] = $this->teams();
        $engine = app(KnockoutEligibility::class);
        foreach (['QF' => 4, 'SF' => 2, 'F' => 1] as $stage => $count) {
            $round = $engine->round(2026, $stage, $teams, $assignments, []);
            $this->assertCount($count, $round);
            foreach ($round as $slot) {
                $this->assertSame('To be confirmed', $slot['label']);
                $this->assertNull($slot['match_id']);
                $this->assertNull($slot['team1']);
                $this->assertNull($slot['team2']);
            }
        }
    }

    public function test_partial_round_has_real_connections_only_for_the_filled_position(): void
    {
        [$teams, $assignments] = $this->teams();
        $matches = $this->quarters();
        $matches[] = $this->match(21, 'SF', 7, 1, 7, 2, ['status' => 'upcoming', 'result_type' => null, 'winner_id' => null, 'result_published_at' => null]);
        $round = app(KnockoutEligibility::class)->round(2026, 'SF', $teams, $assignments, $matches);
        $this->assertSame('To be confirmed', $round[0]['label']);
        $this->assertSame(21, $round[1]['match_id']);
        $this->assertSame(14, $round[1]['team1']['source_match_id']);
        $this->assertSame(11, $round[1]['team2']['source_match_id']);
    }

    public function test_missing_upstream_win_is_reported_as_ineligible_without_inventing_an_edge(): void
    {
        [$teams, $assignments] = $this->teams();
        $matches = [$this->match(21, 'SF', 1, 3, 1)];
        $round = app(KnockoutEligibility::class)->round(2026, 'SF', $teams, $assignments, $matches);
        $this->assertFalse($round[0]['team1']['eligible']);
        $this->assertNull($round[0]['team1']['source_match_id']);
    }

    public function test_current_approval_change_does_not_erase_an_actual_historical_connection(): void
    {
        [$teams, $assignments] = $this->teams();
        $matches = $this->quarters();
        $matches[] = $this->match(21, 'SF', 7, 1, 7);
        $teams[6]->approved = false;
        $round = app(KnockoutEligibility::class)->round(2026, 'SF', $teams, $assignments, $matches);
        $this->assertFalse($round[0]['team1']['eligible']);
        $this->assertSame(14, $round[0]['team1']['source_match_id']);
    }

    public function test_ambiguous_winners_and_invalid_published_winners_fail_closed(): void
    {
        [$teams, $assignments] = $this->teams();
        $engine = app(KnockoutEligibility::class);
        $this->rejected(fn () => $engine->candidates(2026, 'SF', $teams, $assignments,
            [$this->match(11, 'QF', 1, 2, 1), $this->match(12, 'QF', 1, 3, 1, 2)]));
        $this->rejected(fn () => $engine->candidates(2026, 'SF', $teams, $assignments,
            [$this->match(11, 'QF', 1, 2, 7)]));
        $this->rejected(fn () => $engine->candidates(2026, 'G', $teams, $assignments, []));
        $this->rejected(fn () => $engine->candidates(0, 'QF', $teams, $assignments, []));
    }

    public function test_engine_uses_only_supplied_models_without_queries_or_source_changes(): void
    {
        [$teams, $assignments] = $this->teams();
        $matches = $this->quarters();
        $before = array_map(fn ($row) => $row->getAttributes(), [...$teams, ...$assignments, ...$matches]);
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });
        $engine = app(KnockoutEligibility::class);
        $engine->pair(2026, 'SF', 7, 3, $teams, $assignments, $matches);
        $engine->round(2026, 'SF', (function () use ($teams) {
            yield from $teams;
        })(), $assignments, $matches);
        $this->assertSame([], $queries);
        $this->assertSame($before, array_map(fn ($row) => $row->getAttributes(), [...$teams, ...$assignments, ...$matches]));
    }
}
