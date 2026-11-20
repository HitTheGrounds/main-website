<?php

namespace App\Http\Controllers;

use App\Services\Tournament\PublicTournamentService;
use App\Services\Tournament\TournamentWriteService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class ScorerLeaderboardScreenController extends Controller
{
    public function stats(PublicTournamentService $tournament): Response
    {
        $year = config('tournament.year');
        abort_unless(is_int($year) && $year >= 1 && $year <= 9999, 503, 'Tournament information is temporarily unavailable.');
        $rev = (int) Cache::get(TournamentWriteService::revisionKey($year), 0);

        try {
            $leaderboards = [];
            foreach (['sixes' => 'Most sixes', 'fours' => 'Most fours', 'dot_balls' => 'Dot ball dominators'] as $category => $title) {
                $ranking = $tournament->rankings($year, ['category' => $category]);
                $leaderboards[] = [
                    'category' => $category,
                    'title' => $title,
                    'metric' => $category === 'dot_balls' ? 'Dot balls' : ucfirst($category),
                    'entries' => array_slice($ranking['entries'], 0, 5),
                ];
            }
        } catch (ValidationException) {
            abort(503, 'Tournament information is temporarily unavailable.');
        }

        return response()->view('scorer.screen.stats_leaderboard', compact('year', 'rev', 'leaderboards'));
    }

    public function __invoke(PublicTournamentService $tournament): Response
    {
        $year = config('tournament.year');
        abort_unless(is_int($year) && $year >= 1 && $year <= 9999, 503, 'Tournament information is temporarily unavailable.');
        $rev = (int) Cache::get(TournamentWriteService::revisionKey($year), 0);

        try {
            $leaderboards = [];
            foreach (['runs' => 'Orange', 'wickets' => 'Purple'] as $category => $cap) {
                $ranking = $tournament->rankings($year, ['category' => $category]);
                $leaderboards[] = [
                    'category' => $category,
                    'cap' => $cap,
                    'entries' => array_slice($ranking['entries'], 0, 5),
                    'leader_ids' => $ranking['leader_ids'],
                ];
            }
        } catch (ValidationException) {
            abort(503, 'Tournament information is temporarily unavailable.');
        }

        return response()->view('scorer.screen.cap_leaderboard', compact('year', 'rev', 'leaderboards'));
    }
}
