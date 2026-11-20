<?php

namespace App\Http\Controllers;

use App\Services\Tournament\PublicTournamentService;
use App\Services\Tournament\TournamentWriteService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class ScorerBracketScreenController extends Controller
{
    public function __invoke(PublicTournamentService $tournament): Response
    {
        $year = config('tournament.year');
        abort_unless(is_int($year) && $year >= 1 && $year <= 9999, 503, 'Tournament information is temporarily unavailable.');
        $rev = (int) Cache::get(TournamentWriteService::revisionKey($year), 0);

        try {
            $bracketData = $tournament->bracket($year);
        } catch (ValidationException) {
            abort(503, 'Tournament information is temporarily unavailable.');
        }

        return response()->view('scorer.screen.bracket', compact('year', 'rev', 'bracketData'));
    }
}
