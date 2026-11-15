<?php

namespace App\Services\Tournament;

use App\Models\TournamentGroup;

class GroupFixtureCoverage
{
    /** Count distinct assigned-team pairs, never raw fixture rows or drafts. */
    public function calculate(TournamentGroup $group, iterable $assignments, iterable $matches): array
    {
        $teams = [];
        foreach ($assignments as $row) {
            if ($row->group_id === $group->id && $row->tournament_year === $group->tournament_year) {
                $teams[$row->team_id] = true;
            }
        }
        $created = $published = [];
        foreach ($matches as $match) {
            if ($match->stage !== 'G' || $match->group_id !== $group->id || $match->tournament_year !== $group->tournament_year
                || $match->team1_id === $match->team2_id || ! isset($teams[$match->team1_id], $teams[$match->team2_id])) {
                continue;
            }
            $pair = min($match->team1_id, $match->team2_id).':'.max($match->team1_id, $match->team2_id);
            $created[$pair] = true;
            if ($match->status === 'finished' && $match->result_published_at !== null) {
                $published[$pair] = true;
            }
        }
        $count = count($teams);
        $expected = intdiv($count * ($count - 1), 2);

        return ['expected' => $expected, 'created' => count($created), 'published' => count($published),
            'missing' => $expected - count($created), 'unpublished' => count($created) - count($published),
            'complete' => $expected > 0 && count($published) === $expected];
    }
}
