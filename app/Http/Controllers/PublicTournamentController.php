<?php

namespace App\Http\Controllers;

use App\Services\Tournament\PublicTournamentService;
use App\Services\Tournament\TournamentWriteService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class PublicTournamentController extends Controller
{
    public function scorecard(int $matchId, PublicTournamentService $tournament): Response
    {
        $data = $tournament->scorecard(config('tournament.year'), $matchId);

        return response()->view('tournament.partials.scorecard', $data);
    }

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
        // Read before the data so a write that lands mid-request still triggers the page's next refresh.
        $rev = (int) Cache::get(TournamentWriteService::revisionKey($year), 0);

        $matchData = null;
        $tableData = null;
        $bracketData = null;
        $rankingData = null;
        $filterErrors = [];
        if ($tab === 'matches') {
            // The Matches tab is split into status tabs; with no status chosen it opens on Finished.
            $matchFilters = array_intersect_key($request->query(), array_flip(['stage', 'status', 'group_id', 'venue', 'sort', 'page']));
            if (($matchFilters['status'] ?? '') === '') {
                $matchFilters['status'] = 'finished';
            }
            try {
                $matchData = $tournament->matches($year, $matchFilters);
            } catch (ValidationException $exception) {
                $filterErrors = $exception->errors();
            }
        }

        if ($tab === 'stats') {
            try {
                $rankingFilters = array_intersect_key($request->query(), ['category' => true]);
                if (($rankingFilters['category'] ?? null) === 'wickets') {
                    $rankingFilters['gender'] = 'Male';
                }
                $rankingData = $tournament->rankings($year, $rankingFilters);
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

        return response()->view('tournament.index', compact('tabs', 'tab', 'year', 'rev', 'matchData', 'tableData', 'bracketData', 'rankingData', 'filterErrors'), $filterErrors ? 422 : 200);
    }
}
