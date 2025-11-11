<x-layouts.company>
    @php
        $user = \App\Helpers\CompanyAuth::user();
        $maxTeamCount = $user->company->max_team_count ?? 2;
    @endphp

    <div class="w-full h-full">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-3xl font-bold text-base-content">My Teams</h1>
                <p class="mt-2 text-base-content/70">Manage your tournament teams ({{ $user->teams->count() }}/{{ $maxTeamCount }})</p>
            </div>
            @if($user->teams->count() < $maxTeamCount)
                @if(config('registration.team_enabled'))
                    <a href="{{ route('company.teams.create') }}" class="btn btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Add New Team
                    </a>
                @else
                    <button onclick="team_creation_closed_modal.showModal()" class="btn">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Team Registration Closed
                    </button>
                @endif
            @endif
        </div>

        @if($user->teams->count() > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                @foreach($user->teams as $team)
                    <div class="card bg-base-100 shadow-sm border-base-300 border-1">
                        <div class="card-body">
                            <h2 class="card-title">{{ $team->team_name }}</h2>

                            <div class="space-y-2 text-sm">
                                <div>
                                    <span class="font-semibold">Captain:</span>
                                    <span>{{ $team->captain()?->name ?? 'Not assigned' }}</span>
                                </div>
                                <div>
                                    <span class="font-semibold">Email:</span>
                                    <span>{{ $team->captain_email }}</span>
                                </div>
                                <div>
                                    <span class="font-semibold">Phone:</span>
                                    <span>{{ $team->captain_phone }}</span>
                                </div>
                                <div>
                                    <span class="font-semibold">Team Members:</span>
                                    <span>{{ $team->members->count() }}/12</span>
                                </div>
                            </div>

                            <div class="card-actions justify-end mt-4">
                                <a href="{{ route('company.teams.show', $team) }}" class="btn btn-primary btn-sm">
                                    View Details
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="card bg-base-100 shadow-sm border-base-300 border-1">
                <div class="card-body text-center py-12">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-20 h-20 mx-auto opacity-50">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                    </svg>
                    <h3 class="text-xl font-bold mt-4">No Teams Yet</h3>
                    <p class="text-base-content/70 mt-2">Create your first team to participate in the tournament</p>
                    <p class="text-sm text-base-content/50 mt-1">You can register up to {{ $maxTeamCount }} teams</p>
                    <div class="mt-6">
                        @if(config('registration.team_enabled'))
                            <a href="{{ route('company.teams.create') }}" class="btn btn-primary">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                                Create First Team
                            </a>
                        @else
                            <button onclick="team_creation_closed_modal.showModal()" class="btn btn-disabled">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                                Team Registration Closed
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </div>

    <!-- Team Creation Closed Modal -->
    <dialog id="team_creation_closed_modal" class="modal modal-bottom sm:modal-middle">
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
