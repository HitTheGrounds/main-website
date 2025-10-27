<x-layouts.admin>
    <div>
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-base-content">Admin Dashboard</h1>
            <p class="mt-2 text-base-content/70">Manage companies, teams, and users</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Companies Card -->
            <a href="{{ route('admin.companies') }}" class="card bg-base-100 shadow-sm border-base-300 border-1 hover:shadow-md transition">
                <div class="card-body">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="card-title text-lg">Companies</h2>
                            <p class="text-3xl font-bold mt-2">{{ App\Models\Company::whereHas('users', function($q) { $q->whereNotNull('email_verified_at'); })->count() }}</p>
                            <p class="text-sm text-success mt-1">Verified companies</p>
                        </div>
                        <div class="p-3 bg-primary/10 rounded-lg">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8 text-primary">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                            </svg>
                        </div>
                    </div>
                </div>
            </a>

            <!-- Teams Card -->
            <a href="{{ route('admin.teams') }}" class="card bg-base-100 shadow-sm border-base-300 border-1 hover:shadow-md transition">
                <div class="card-body">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="card-title text-lg">Teams</h2>
                            <p class="text-3xl font-bold mt-2">{{ App\Models\Team::count() }}</p>
                            <p class="text-sm text-warning mt-1">{{ App\Models\Team::where('approved', false)->count() }} pending approval</p>
                        </div>
                        <div class="p-3 bg-secondary/10 rounded-lg">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8 text-secondary">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                            </svg>
                        </div>
                    </div>
                </div>
            </a>

            <!-- Users Card -->
            <a href="{{ route('admin.users') }}" class="card bg-base-100 shadow-sm border-base-300 border-1 hover:shadow-md transition">
                <div class="card-body">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="card-title text-lg">Users</h2>
                            <p class="text-3xl font-bold mt-2">{{ App\Models\User::whereNotNull('email_verified_at')->count() }}</p>
                            <p class="text-sm text-success mt-1">Verified users <span class="text-warning"> (incl. {{ App\Models\User::where('is_admin', true)->count() }} admins)</span></p>
                        </div>
                        <div class="p-3 bg-accent/10 rounded-lg">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8 text-accent">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                            </svg>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    </div>
</x-layouts.admin>
