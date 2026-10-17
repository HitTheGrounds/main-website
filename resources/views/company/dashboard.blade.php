<x-layouts.company>
    @php
        $user = \App\Helpers\CompanyAuth::user();
        $maxTeamCount = $user->company->max_team_count ?? 2;
    @endphp

    <div>
        <!-- Header -->
        <div class="mb-6 bg-primary -mx-4 -mt-4 lg:-mx-6 lg:-mt-6 px-4 lg:px-6 pt-4 lg:pt-6 lg:pb-4 border-b-2 border-base-300">
            <h1 class="text-3xl font-bold text-accent">Company Dashboard</h1>
            <p class="mt-2 text-accent/70">Welcome, {{ $user->name }}</p>
        </div>
        <!-- <div class="mb-6">
            <h1 class="text-3xl font-bold bg-primary -mx-4 -mt-4 lg:-mx-6 lg:-mt-6 px-4 lg:px-6 pt-4 lg:pt-6 lg:pb-4  text-accent">Company Dashboard</h1>
            <p class="mt-2 mt-6">Welcome, {{ $user->name }}</p>
        </div> -->

            <!-- Company Details Card -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8">
                <div class="card bg-base-100 border-base-300 border-2 md:col-span-2">
                    <div class="card-body">
                        <div class="flex items-center gap-2">
                            <div class="rounded-full bg-primary/20 p-2">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5 text-primary/80">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z" />
                                </svg>
                            </div>
                            <h2 class="card-title">Company Information</h2>
                        </div>
                        <hr class="border-base-300 border-1/2 my-2">
                        <div class="space-y-3">
                            <div>
                                <p class="text-sm text-base-content/70 flex items-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-4">
                                    <path fill-rule="evenodd" d="M1.75 2a.75.75 0 0 0 0 1.5H2v9h-.25a.75.75 0 0 0 0 1.5h1.5a.75.75 0 0 0 .75-.75v-1.5a.75.75 0 0 1 .75-.75h1.5a.75.75 0 0 1 .75.75v1.5c0 .414.336.75.75.75h.5a.75.75 0 0 0 .75-.75V3.5h.25a.75.75 0 0 0 0-1.5h-7.5ZM3.5 5.5A.5.5 0 0 1 4 5h.5a.5.5 0 0 1 .5.5V6a.5.5 0 0 1-.5.5H4a.5.5 0 0 1-.5-.5v-.5Zm.5 2a.5.5 0 0 0-.5.5v.5A.5.5 0 0 0 4 9h.5a.5.5 0 0 0 .5-.5V8a.5.5 0 0 0-.5-.5H4Zm2-2a.5.5 0 0 1 .5-.5H7a.5.5 0 0 1 .5.5V6a.5.5 0 0 1-.5.5h-.5A.5.5 0 0 1 6 6v-.5Zm.5 2A.5.5 0 0 0 6 8v.5a.5.5 0 0 0 .5.5H7a.5.5 0 0 0 .5-.5V8a.5.5 0 0 0-.5-.5h-.5ZM11.5 6a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h2.75a.75.75 0 0 0 0-1.5H14v-5h.25a.75.75 0 0 0 0-1.5H11.5Zm.5 1.5h.5a.5.5 0 0 1 .5.5v.5a.5.5 0 0 1-.5.5H12a.5.5 0 0 1-.5-.5V8a.5.5 0 0 1 .5-.5Zm0 2.5a.5.5 0 0 0-.5.5v.5a.5.5 0 0 0 .5.5h.5a.5.5 0 0 0 .5-.5v-.5a.5.5 0 0 0-.5-.5H12Z" clip-rule="evenodd" />
                                    </svg>
                                    Company Name
                                </p>
                                <p class="font-medium">{{ $user->company?->name ?? $user->company_name ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-base-content/70 flex items-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-4">
                                        <path fill-rule="evenodd" d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM12.735 14c.618 0 1.093-.561.872-1.139a6.002 6.002 0 0 0-11.215 0c-.22.578.254 1.139.872 1.139h9.47Z" />
                                    </svg>
                                    Contact Person
                                </p>
                                <p class="font-medium">{{ $user->name }}</p>
                            </div>
                            <div>
                                
                                <div class="inline-flex items-baseline gap-2">
                                    <p class="text-sm text-base-content/70 flex items-center gap-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-4">
                                            <path d="M2.5 3A1.5 1.5 0 0 0 1 4.5v.793c.026.009.051.02.076.032L7.674 8.51c.206.1.446.1.652 0l6.598-3.185A.755.755 0 0 1 15 5.293V4.5A1.5 1.5 0 0 0 13.5 3h-11Z" />
                                            <path d="M15 6.954 8.978 9.86a2.25 2.25 0 0 1-1.956 0L1 6.954V11.5A1.5 1.5 0 0 0 2.5 13h11a1.5 1.5 0 0 0 1.5-1.5V6.954Z" />
                                        </svg>
                                        Email
                                    </p>
                                    <!-- <p class="text-sm text-base-content/70">Email Verification</p> -->
                                    @if($user->email_verified_at)
                                    <div class="badge badge-sm badge-soft badge-success inline-flex items-center gap-0.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-3.5">
                                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                                    </svg>

                                        Verified</div>
                                    @else
                                    <div class="badge badge-sm badge-outline badge-warning">Not Verified</div>
                                    @endif
                                </div>
                                <p class="font-medium">{{ $user->email }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-base-content/70 flex items-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-4">
                                        <path fill-rule="evenodd" d="m3.855 7.286 1.067-.534a1 1 0 0 0 .542-1.046l-.44-2.858A1 1 0 0 0 4.036 2H3a1 1 0 0 0-1 1v2c0 .709.082 1.4.238 2.062a9.012 9.012 0 0 0 6.7 6.7A9.024 9.024 0 0 0 11 14h2a1 1 0 0 0 1-1v-1.036a1 1 0 0 0-.848-.988l-2.858-.44a1 1 0 0 0-1.046.542l-.534 1.067a7.52 7.52 0 0 1-4.86-4.859Z" clip-rule="evenodd" />
                                    </svg>Phone</p>
                                <p class="font-medium">{{ $user->company?->phone ?? $user->phone ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-base-content/70 flex items-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-4">
                                        <path fill-rule="evenodd" d="M4 1.75a.75.75 0 0 1 1.5 0V3h5V1.75a.75.75 0 0 1 1.5 0V3a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2V1.75ZM4.5 6a1 1 0 0 0-1 1v4.5a1 1 0 0 0 1 1h7a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1h-7Z" clip-rule="evenodd" />
                                    </svg>Registered At</p>
                                <p class="font-medium">{{ $user->created_at?->timezone('Asia/Colombo')->format('F d, Y h:i A') ?? 'N/A' }}</p>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- <div class="card bg-base-100 border-base-300 border-2">
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
                </div> -->
            <!-- </div> -->

            <!-- Teams Section -->
            <div class="card bg-base-100 border-base-300 border-2 md:col-span-3">
                <div class="card-body">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center gap-2">
                            <div class="rounded-full bg-primary/20 p-2">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5 text-primary/80">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                                </svg>
                            </div>
                            <h2 class="card-title">My Teams ({{ $user->teams->count() }}/{{ $maxTeamCount }})</h2>
                        </div>

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

                    <hr class="border-base-300 border-1/2 my-2">

                    @if($user->teams->count() > 0)
                        <div class="grid grid-cols-1 md:grid-cols-1 gap-4">
                            @foreach($user->teams as $team)
                                <a href="{{ route('company.teams.show', $team) }}" class="card bg-base-200 hover:bg-base-300 border border-base-100 hover:border-base-300 transition-colors cursor-pointer">
                                    <div class="card-body">
                                        <h3 class="font-bold text-lg">{{ $team->team_name }}</h3>
                                        <div class="text-sm space-y-1">
                                            <p><span class="opacity-70">Team Captain:</span> {{ $team->captain()?->name ?? 'Not assigned' }}</p>
                                            <p><span class="opacity-70">Contact Person Email:</span> {{ $team->captain_email }}</p>
                                            <p><span class="opacity-70">Contact Person Phone:</span> {{ $team->captain_phone }}</p>
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
