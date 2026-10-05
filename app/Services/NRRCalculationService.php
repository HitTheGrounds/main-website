<?php

namespace App\Services;

use App\Models\GroupTeam;
use App\Models\TournamentMatch;

class NRRCalculationService
{
    /**
     * Calculate Net Run Rate for a given GroupTeam.
     */
    public function calculateNRR(GroupTeam $groupTeam): float
    {
        $teamId = $groupTeam->team_id;
        $groupId = $groupTeam->group_id;

        $finishedMatches = TournamentMatch::where('status', 'finished')
            ->where('stage', 'G')
            ->where('group_id', $groupId)
            ->where(function ($query) use ($teamId) {
                $query->where('team1_id', $teamId)
                      ->orWhere('team2_id', $teamId);
            })->get();

        $totalRunsScored = 0;
        $totalOversFaced = 0.0;

        $totalRunsConceded = 0;
        $totalOversBowled = 0.0;

        $ballsPerOver = config('tournament.balls_per_over.G', 4);
        $maxWickets = config('tournament.max_wickets', 11);
        $oversPerMatch = config('tournament.overs_per_match', 5);

        foreach ($finishedMatches as $match) {
            $isTeam1 = $match->team1_id === $teamId;

            $scored = $isTeam1 ? $match->team1_score : $match->team2_score;
            $oversFaced = $isTeam1 ? $match->team1_overs : $match->team2_overs;
            $ballsFaced = $isTeam1 ? $match->team1_balls : $match->team2_balls;
            $wicketsLost = $isTeam1 ? $match->team1_wickets : $match->team2_wickets;

            $conceded = $isTeam1 ? $match->team2_score : $match->team1_score;
            $oversBowled = $isTeam1 ? $match->team2_overs : $match->team1_overs;
            $ballsBowled = $isTeam1 ? $match->team2_balls : $match->team1_balls;
            $wicketsTaken = $isTeam1 ? $match->team2_wickets : $match->team1_wickets;

            $totalRunsScored += $scored ?? 0;
            $totalRunsConceded += $conceded ?? 0;

            // Handle All-Out rule for faced
            if ($wicketsLost >= $maxWickets) {
                $totalOversFaced += $oversPerMatch;
            } else {
                $totalOversFaced += ($oversFaced ?? 0) + (($ballsFaced ?? 0) / $ballsPerOver);
            }

            // Handle All-Out rule for bowled
            if ($wicketsTaken >= $maxWickets) {
                $totalOversBowled += $oversPerMatch;
            } else {
                $totalOversBowled += ($oversBowled ?? 0) + (($ballsBowled ?? 0) / $ballsPerOver);
            }
        }

        $runsPerOverScored = $totalOversFaced > 0 ? ($totalRunsScored / $totalOversFaced) : 0;
        $runsPerOverConceded = $totalOversBowled > 0 ? ($totalRunsConceded / $totalOversBowled) : 0;

        return $runsPerOverScored - $runsPerOverConceded;
    }
}
