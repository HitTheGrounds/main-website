<?php

namespace App\Http\Controllers;

use App\Services\Tournament\PublicTournamentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class PublicTournamentController extends Controller
{
    public function __invoke(Request $request, PublicTournamentService $tournament): Response
    {
        $tabs = [
            'matches' => ['label' => 'Matches', 'description' => 'Fixtures and published match results.'],
            'table' => ['label' => 'Table', 'description' => 'Group standings, points and net run rate.'],
            'bracket' => ['label' => 'Bracket', 'description' => 'Quarter-finals, semi-finals and the final.'],
            'stats' => ['label' => 'Stats', 'description' => 'Tournament leaders from published player performances.'],
        ];
        $tab = $request->query('tab', 'matches');
        abort_unless(is_string($tab) && array_key_exists($tab, $tabs), 404);

        $year = config('tournament.year');
        abort_unless(is_int($year) && $year >= 1 && $year <= 9999, 503, 'Tournament information is temporarily unavailable.');

        $matchData = null;
        $tableData = null;
        $bracketData = null;
        $filterErrors = [];
        if ($tab === 'matches') {
            try {
                $matchData = $tournament->matches($year, array_intersect_key($request->query(),
                    array_flip(['stage', 'status', 'group_id', 'sort', 'page'])));
            } catch (ValidationException $exception) {
                $filterErrors = $exception->errors();
            }
        }

        if (in_array($tab, ['table', 'bracket'], true)) {
            try {
                if ($tab === 'table') {
                    $tableData = $tournament->standings($year);
                } else {
                    $bracketData = $tournament->bracket($year);
                }
            } catch (ValidationException) {
                abort(503, 'Tournament information is temporarily unavailable.');
            }
        }

        return response()->view('tournament.index', compact('tabs', 'tab', 'year', 'matchData', 'tableData', 'bracketData', 'filterErrors'), $filterErrors ? 422 : 200);
    }
}
