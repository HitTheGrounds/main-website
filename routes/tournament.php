<?php

use App\Http\Controllers\PublicTournamentController;
use App\Http\Middleware\EnsureTournamentEnabled;
use App\Services\Tournament\TournamentWriteService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

if (config('tournament.enabled') !== true) {
    return;
}

Route::get('/tournament', PublicTournamentController::class)
    ->middleware(EnsureTournamentEnabled::class)->name('tournament.index');

Route::get('/tournament/matches/{matchId}/scorecard', [PublicTournamentController::class, 'scorecard'])
    ->whereNumber('matchId')->middleware(EnsureTournamentEnabled::class)->name('tournament.scorecard');

// Cheap change counter the public page polls (one cache read per request).
Route::get('/tournament/rev', fn () => response()
    ->json(['rev' => (int) Cache::get(TournamentWriteService::revisionKey((int) config('tournament.year')), 0)]))
    ->middleware(EnsureTournamentEnabled::class)->name('tournament.rev');

Route::middleware([EnsureTournamentEnabled::class, 'auth.company', 'scorer'])
    ->prefix('scorer')->name('scorer.')->group(function () {
        Route::view('/dashboard', 'scorer.dashboard')->name('dashboard');
        Route::view('/groups', 'scorer.groups.index')->name('groups.index');
        Route::view('/standings', 'scorer.standings')->name('standings');
        Route::view('/screen/group_stage', 'scorer.screen.group_stage')->name('screen.group_stage');
        Route::view('/knockouts', 'scorer.knockouts')->name('knockouts');
        Route::view('/matches/{matchId}/result', 'scorer.result')->whereNumber('matchId')->name('matches.result');
        Route::view('/matches/{matchId}/players', 'scorer.players')->whereNumber('matchId')->name('matches.players');
    });

Route::middleware([EnsureTournamentEnabled::class, 'auth.company', 'admin'])
    ->prefix('admin/tournament')->name('admin.tournament.')->group(function () {
        Route::view('/scorers', 'admin.tournament.scorers.index')->name('scorers.index');
    });
