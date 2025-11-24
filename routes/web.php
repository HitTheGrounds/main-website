<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('home');
})->name('home');

Route::get('/awards', function () {
    return view('awards');
})->name('awards');

Route::get('/partners', function () {
    return view('partners');
})->name('partners');

Route::get('/timeline', function () {
    return view('timeine');
})->name('timeline');

Route::get('/gallery', function () {
    return view('gallery');
})->name('gallery');

Route::get('/committee', function () {
    return view('committee');
})->name('committee');

Route::get('/registrations', function () {
    return view('registrations');
})->name('registrations');

Route::get('/rules', function () {
    return view('rules');
})->name('rules');

Route::get('/teams/industry', function () {
    return view('teams-industry');
})->name('teams.industry');

Route::middleware('guest.company')->group(function () {
    Route::get('/register', function () {
        return view('register');
    })->name('register'); // Primary name for Laravel compatibility

    Route::get('/login', function () {
        return view('login');
    })->name('login'); // Primary name for Laravel compatibility
});

// Hidden admin registration route (for users without company)
Route::get('/admin/register', function () {
    return view('admin.register');
})->name('admin.register');

// Admin routes (protected by admin middleware)
Route::middleware(['auth.company', 'admin'])->group(function () {
    Route::get('/admin/dashboard', function () {
        return view('admin.dashboard');
    })->name('admin.dashboard');

    Route::get('/admin/companies', function () {
        return view('admin.companies.index');
    })->name('admin.companies');

    Route::get('/admin/teams', function () {
        return view('admin.teams.index');
    })->name('admin.teams');

    Route::get('/admin/teams/export', [App\Http\Controllers\Admin\TeamExportController::class, 'export'])
        ->name('admin.teams.export');

    Route::get('/admin/teams/{team}', function (App\Models\Team $team) {
        return view('admin.teams.show', ['team' => $team]);
    })->name('admin.teams.show');

    Route::get('/admin/users', function () {
        return view('admin.users.index');
    })->name('admin.users');

    Route::get('/teams/university', function () {
        return view('teams-university');
    })->name('teams.university');

    Route::get('/company/{company}/profile', function (App\Models\Company $company) {
        return view('company-profile', ['company' => $company]);
    })->name('company.public-profile');
});

Route::middleware('auth.company')->group(function () {
    Route::get('/company/dashboard', function () {
        return view('company.dashboard');
    })->name('company.dashboard');

    Route::get('/company/profile', function () {
        return view('company.profile');
    })->name('company.profile');

    Route::get('/company/teams', function () {
        return view('company.teams.index');
    })->name('company.teams');

    Route::get('/company/teams/create', function () {
        return view('company.teams.create');
    })->name('company.teams.create');

    Route::get('/company/teams/{team}', function (App\Models\Team $team) {
        if ($team->user_id !== request()->attributes->get('user')->id) {
            abort(403);
        }
        return view('company.teams.show', ['team' => $team]);
    })->name('company.teams.show');

    Route::post('/company/logout', function (Illuminate\Http\Request $request) {
        $token = $request->cookie('company_token');

        if ($token) {
            $jwtService = new App\Services\JWTService();
            $user = $jwtService->getUserFromToken($token);

            if ($user) {
                $jwtService->revokeToken($user);
            }
        }

        // Clear the cookie
        cookie()->queue(cookie()->forget('company_token'));

        return redirect()->route('home');
    })->name('company.logout');
});

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

require __DIR__.'/auth.php';
