<?php

namespace App\Services;

use App\Models\TournamentGroup;
use App\Models\TournamentMatch;

class TournamentStandingsService
{
    protected NRRCalculationService $nrrService;

    public function __construct(NRRCalculationService $nrrService)
    {
        $this->nrrService = $nrrService;
    }

    /**
     * Recalculate standings for an entire group.
     */
    public function recalculateForGroup(TournamentGroup $group): void
    {
        $groupTeams = $group->groupTeams()->get();
        $ballsPerOver = config('tournament.balls_per_over.G', 4);
        
        $pointsWin = config('tournament.points_win', 2);
        $pointsDraw = config('tournament.points_draw', 1);
        $pointsLoss = config('tournament.points_loss', 0);

        foreach ($groupTeams as $groupTeam) {
            $teamId = $groupTeam->team_id;

            $finishedMatches = TournamentMatch::where('status', 'finished')
                ->where('stage', 'G')
                ->where('group_id', $group->id)
                ->where(function ($query) use ($teamId) {
                    $query->where('team1_id', $teamId)
                          ->orWhere('team2_id', $teamId);
                })->get();

            $matchesPlayed = $finishedMatches->count();
            $wins = 0;
            $losses = 0;
            $draws = 0;

            $totalBallsFaced = 0;
            $totalRunsScored = 0;
            $totalBallsBowled = 0;
            $totalRunsConceded = 0;

            foreach ($finishedMatches as $match) {
                if ($match->is_draw) {
                    $draws++;
                } elseif ($match->winner_id === $teamId) {
                    $wins++;
                } else {
                    $losses++;
                }

                $isTeam1 = $match->team1_id === $teamId;

                $scored = $isTeam1 ? $match->team1_score : $match->team2_score;
                $oversFaced = $isTeam1 ? $match->team1_overs : $match->team2_overs;
                $ballsFaced = $isTeam1 ? $match->team1_balls : $match->team2_balls;

                $conceded = $isTeam1 ? $match->team2_score : $match->team1_score;
                $oversBowled = $isTeam1 ? $match->team2_overs : $match->team1_overs;
                $ballsBowled = $isTeam1 ? $match->team2_balls : $match->team1_balls;

                $totalRunsScored += $scored ?? 0;
                $totalRunsConceded += $conceded ?? 0;

                $totalBallsFaced += (($oversFaced ?? 0) * $ballsPerOver) + ($ballsFaced ?? 0);
                $totalBallsBowled += (($oversBowled ?? 0) * $ballsPerOver) + ($ballsBowled ?? 0);
            }

            $points = ($wins * $pointsWin) + ($draws * $pointsDraw) + ($losses * $pointsLoss);
            $nrr = $this->nrrService->calculateNRR($groupTeam);

            $groupTeam->update([
                'matches_played' => $matchesPlayed,
                'wins' => $wins,
                'losses' => $losses,
                'draws' => $draws,
                'points' => $points,
                'total_balls_faced' => $totalBallsFaced,
                'total_runs_scored' => $totalRunsScored,
                'total_balls_bowled' => $totalBallsBowled,
                'total_runs_conceded' => $totalRunsConceded,
                'nrr' => $nrr,
            ]);
        }
    }
}
