<?php

namespace Tests\Feature;

use App\Models\GroupTeam;
use App\Models\TournamentGroup;
use App\Models\TournamentMatch;
use App\Services\Tournament\GroupFixtureCoverage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GroupFixtureCoverageTest extends TestCase
{
    public static function sizes(): array
    {
        return [[0, 0], [1, 0], [4, 6], [5, 10], [6, 15]];
    }

    #[DataProvider('sizes')]
    public function test_expected_pairs_are_dynamic_and_empty_groups_are_not_complete(int $size, int $expected): void
    {
        $group = (new TournamentGroup)->forceFill(['id' => 9, 'tournament_year' => 2026]);
        $rows = [];
        for ($id = 1; $id <= $size; $id++) {
            $rows[] = (new GroupTeam)->forceFill(['group_id' => 9, 'tournament_year' => 2026, 'team_id' => $id]);
        }
        $coverage = app(GroupFixtureCoverage::class)->calculate($group, $rows, []);
        $this->assertSame($expected, $coverage['expected']);
        $this->assertSame($expected, $coverage['missing']);
        $this->assertFalse($coverage['complete']);
    }

    public function test_reversed_duplicates_foreign_pairs_and_unpublished_finished_rows_cannot_fill_missing_pairs(): void
    {
        $group = (new TournamentGroup)->forceFill(['id' => 9, 'tournament_year' => 2026]);
        $rows = array_map(fn ($id) => (new GroupTeam)->forceFill(['group_id' => 9, 'tournament_year' => 2026, 'team_id' => $id]), [1, 2, 3]);
        $base = ['group_id' => 9, 'tournament_year' => 2026, 'stage' => 'G', 'team1_id' => 1, 'team2_id' => 2, 'status' => 'finished', 'result_published_at' => now()];
        $changes = [[], ['team1_id' => 2, 'team2_id' => 1], ['team2_id' => 99], ['team2_id' => 1],
            ['stage' => 'QF'], ['group_id' => 8], ['tournament_year' => 2027], ['team2_id' => 3, 'result_published_at' => null]];
        $matches = array_map(fn ($change) => (new TournamentMatch)->forceFill(array_replace($base, $change)), $changes);
        $this->assertSame(['expected' => 3, 'created' => 2, 'published' => 1, 'missing' => 1, 'unpublished' => 1, 'complete' => false],
            app(GroupFixtureCoverage::class)->calculate($group, $rows, $matches));
    }
}
