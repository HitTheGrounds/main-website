<x-layouts.company>
    @php
        $user = \App\Helpers\CompanyAuth::user();
        $maxTeamCount = $user->company->max_team_count ?? 2;
    @endphp

    <div>
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-base-content">Company Dashboard</h1>
            <p class="mt-2 text-base-content/70">Welcome, {{ $user->name }}</p>
        </div>

            <!-- Company Details Card -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <div class="card bg-base-100 shadow-sm border-base-300 border-1">
                    <div class="card-body">
                        <h2 class="card-title">Company Information</h2>
                        <div class="space-y-3">
                            <div>
                                <p class="text-sm text-base-content/70">Company Name</p>
                                <p class="font-medium">{{ $user->company?->name ?? $user->company_name ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-base-content/70">Contact Person</p>
                                <p class="font-medium">{{ $user->name }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-base-content/70">Email</p>
                                <p class="font-medium">{{ $user->email }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-base-content/70">Phone</p>
                                <p class="font-medium">{{ $user->company?->phone ?? $user->phone ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-base-content/70">Registered At</p>
                                <p class="font-medium">{{ $user->created_at->format('F d, Y h:i A') }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card bg-base-100 shadow-sm border-base-300 border-1">
                    <div class="card-body">
                        <h2 class="card-title">Status</h2>
                        <div class="space-y-3">
                            <div>
                                <p class="text-sm text-base-content/70">Email Verification</p>
                                @if($user->email_verified_at)
                                    <div class="badge badge-success">Verified</div>
                                @else
                                    <div class="badge badge-warning">Not Verified</div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <!-- Teams Section -->
        <div class="card bg-base-100 shadow-sm border-base-300 border-1">
            <div class="card-body">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="card-title">My Teams ({{ $user->teams->count() }}/{{ $maxTeamCount }})</h2>
                    @if($user->teams->count() < $maxTeamCount)
                        @if(config('registration.team_enabled'))
                            <a href="{{ route('company.teams.create') }}" class="btn btn-primary btn-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                                Add Team
                            </a>
                        @else
                            <button onclick="dashboard_team_closed_modal.showModal()" class="btn btn-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                                Team Registration Closed
                            </button>
                        @endif
                    @endif
                </div>

                @if($user->teams->count() > 0)
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($user->teams as $team)
                            <a href="{{ route('company.teams.show', $team) }}" class="card bg-base-200 hover:bg-base-300 transition-colors cursor-pointer">
                                <div class="card-body">
                                    <h3 class="font-bold text-lg">{{ $team->team_name }}</h3>
                                    <div class="text-sm space-y-1">
                                        <p><span class="opacity-70">Captain:</span> {{ $team->captain()?->name ?? 'Not assigned' }}</p>
                                        <p><span class="opacity-70">Email:</span> {{ $team->captain_email }}</p>
                                        <p><span class="opacity-70">Phone:</span> {{ $team->captain_phone }}</p>
                                        <p><span class="opacity-70">Members:</span> {{ $team->members->count() }}/12</p>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 mx-auto opacity-50">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                        </svg>
                        <p class="mt-4 text-base-content/70">No teams yet</p>
                        <p class="text-sm text-base-content/50">You can register up to {{ $maxTeamCount }} teams</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Team Creation Closed Modal -->
    <dialog id="dashboard_team_closed_modal" class="modal modal-bottom sm:modal-middle">
        <div class="modal-box">
            <div class="flex flex-col items-center gap-4 py-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-warning" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <h3 class="font-bold text-2xl">Team Registration Closed</h3>
                <p class="text-center text-base-content/80">
                    New team registrations are currently closed. You can still manage your existing teams, but cannot create new ones at this time.
                </p>
            </div>
            <div class="modal-action">
                <form method="dialog" class="w-full">
                    <button class="btn btn-primary w-full">Close</button>
                </form>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>close</button>
        </form>
    </dialog>
</x-layouts.company>
