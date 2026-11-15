<?php

use App\Http\Middleware\EnsureTournamentEnabled;
use Illuminate\Support\Facades\Route;

if (config('tournament.enabled') !== true) {
    return;
}

Route::middleware([EnsureTournamentEnabled::class, 'auth.company', 'scorer'])
    ->prefix('scorer')->name('scorer.')->group(function () {
        Route::view('/dashboard', 'scorer.dashboard')->name('dashboard');
        Route::view('/groups', 'scorer.groups.index')->name('groups.index');
        Route::view('/standings', 'scorer.standings')->name('standings');
        Route::view('/matches/{matchId}/result', 'scorer.result')->whereNumber('matchId')->name('matches.result');
        Route::view('/matches/{matchId}/players', 'scorer.players')->whereNumber('matchId')->name('matches.players');
    });

Route::middleware([EnsureTournamentEnabled::class, 'auth.company', 'admin'])
    ->prefix('admin/tournament')->name('admin.tournament.')->group(function () {
        Route::view('/scorers', 'admin.tournament.scorers.index')->name('scorers.index');
    });
