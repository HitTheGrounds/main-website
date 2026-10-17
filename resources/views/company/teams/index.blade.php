<x-layouts.company>
    @php
        $user = \App\Helpers\CompanyAuth::user();
        $maxTeamCount = $user->company->max_team_count ?? 2;
    @endphp

    <div class="w-full h-full">
        <div class="flex justify-between items-center mb-6 bg-primary -mx-4 -mt-4 lg:-mx-6 lg:-mt-6 px-4 lg:px-6 pt-4 lg:pt-6 lg:pb-4 border-b-2 border-base-300">
            <div>
                <h1 class="text-3xl font-bold text-accent">My Teams</h1>
                <p class="mt-2 text-accent">Manage your tournament teams
                    ({{ $user->teams->count() }}/{{ $maxTeamCount }})</p>
            </div>
            @if($user->teams->count() < $maxTeamCount)
                @if(config('registration.team_enabled'))
                    <a href="{{ route('company.teams.create') }}" class="btn btn-secondary">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Add New Team
                    </a>
                @else
                    <button onclick="team_creation_closed_modal.showModal()" class="btn">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" class="w-5 h-5">
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
                    <a href="{{ route('company.teams.show', $team) }}" class="card bg-base-100 border-base-300 border-1 hover:border-primary/50 transition-all cursor-pointer group">
                        <div class="card-body">
                            <div class="flex items-center justify-between gap-2">
                                <h3 class="font-bold text-lg transition-colors">{{ $team->team_name }}</h3>
                                <div class="flex items-center gap-1 text-xs font-semibold text-primary group-hover:translate-x-0.5 transition-transform">
                                    <span>View Details</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-4">
                                        <path fill-rule="evenodd" d="M6.22 4.22a.75.75 0 0 1 1.06 0l3.25 3.25a.75.75 0 0 1 0 1.06l-3.25 3.25a.75.75 0 0 1-1.06-1.06L8.94 8 6.22 5.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                            </div>

                            <hr class="border-base-300 border-1/2 my-1">

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-2">
                                <div class="flex items-center gap-2.5">
                                    <div class="rounded-full bg-base-200 p-2 shrink-0">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-4 text-base-content/80">
                                            <path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM12.735 14c.618 0 1.093-.561.872-1.139a6.002 6.002 0 0 0-11.215 0c-.22.578.254 1.139.872 1.139h9.47Z" />
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-xs text-base-content/70">Team Captain</p>
                                        <p class="font-medium truncate">{{ $team->captain()?->name ?? 'Not assigned' }}</p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2.5">
                                    <div class="rounded-full bg-base-200 p-2 shrink-0">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-4 text-base-content/80">
                                            <path d="M8 8a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5ZM3.156 11.763c.16-.629.44-1.21.813-1.72a2.5 2.5 0 0 0-2.725 1.377c-.136.287.102.58.418.58h1.449c.01-.077.025-.156.045-.237ZM12.847 11.763c.02.08.036.16.046.237h1.446c.316 0 .554-.293.417-.579a2.5 2.5 0 0 0-2.722-1.378c.374.51.653 1.09.813 1.72ZM14 7.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0ZM3.5 9a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3ZM5 13c-.552 0-1.013-.455-.876-.99a4.002 4.002 0 0 1 7.753 0c.136.535-.324.99-.877.99H5Z" />
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-xs text-base-content/70">Members</p>
                                        <p class="font-medium truncate">{{ $team->members->count() }}/12</p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2.5">
                                    <div class="rounded-full bg-base-200 p-2 shrink-0">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-4 text-base-content/80">
                                            <path d="M2.5 3A1.5 1.5 0 0 0 1 4.5v.793c.026.009.051.02.076.032L7.674 8.51c.206.1.446.1.652 0l6.598-3.185A.755.755 0 0 1 15 5.293V4.5A1.5 1.5 0 0 0 13.5 3h-11Z" />
                                            <path d="M15 6.954 8.978 9.86a2.25 2.25 0 0 1-1.956 0L1 6.954V11.5A1.5 1.5 0 0 0 2.5 13h11a1.5 1.5 0 0 0 1.5-1.5V6.954Z" />
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-xs text-base-content/70">Contact Person Email</p>
                                        <p class="font-medium truncate">{{ $team->captain_email }}</p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2.5">
                                    <div class="rounded-full bg-base-200 p-2 shrink-0">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-4 text-base-content/80">
                                            <path fill-rule="evenodd" d="m3.855 7.286 1.067-.534a1 1 0 0 0 .542-1.046l-.44-2.858A1 1 0 0 0 4.036 2H3a1 1 0 0 0-1 1v2c0 .709.082 1.4.238 2.062a9.012 9.012 0 0 0 6.7 6.7A9.024 9.024 0 0 0 11 14h2a1 1 0 0 0 1-1v-1.036a1 1 0 0 0-.848-.988l-2.858-.44a1 1 0 0 0-1.046.542l-.534 1.067a7.52 7.52 0 0 1-4.86-4.859Z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-xs text-base-content/70">Contact Person Phone</p>
                                        <p class="font-medium truncate">{{ $team->captain_phone }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @else
            <div class="card bg-base-100 border-base-300 border-2">
                <div class="card-body text-center py-12">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" class="w-20 h-20 mx-auto opacity-50">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                    </svg>
                    <h3 class="text-xl font-bold mt-4">No Teams Yet</h3>
                    <p class="text-base-content/70 mt-2">Create your first team to participate in the tournament</p>
                    <p class="text-sm text-base-content/50 mt-1">You can register up to {{ $maxTeamCount }} teams</p>
                    <div class="mt-6">
                        @if(config('registration.team_enabled'))
                            <a href="{{ route('company.teams.create') }}" class="btn btn-primary">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                    stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                                Create First Team
                            </a>
                        @else
                            <button onclick="team_creation_closed_modal.showModal()" class="btn btn-disabled">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                    stroke="currentColor" class="w-5 h-5">
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
                <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-warning" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <h3 class="font-bold text-2xl">Team Registration Closed</h3>
                <p class="text-center text-base-content/80">
                    New team registrations are currently closed. You can still manage your existing teams, but cannot
                    create new ones at this time.
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