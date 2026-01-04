<x-layouts.admin>
    <div>
        <div class="mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-base-content">{{ $team->team_name }}</h1>
                    <p class="mt-2 text-base-content/70">Team Details & Members</p>
                </div>
                <a href="{{ route('admin.teams') }}" wire:navigate class="btn btn-ghost">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                    Back to Teams
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Team Info Card -->
            <div class="lg:col-span-1">
                <div class="card bg-base-100 shadow-sm border-base-300 border-1">
                    <div class="card-body">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="card-title">Team Information</h2>
                            <div class="dropdown dropdown-end">
                                <label tabindex="0" class="btn btn-ghost btn-sm btn-circle">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 12.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 18.75a.75.75 0 110-1.5.75.75 0 010 1.5z" />
                                    </svg>
                                </label>
                                <ul tabindex="0" class="dropdown-content menu p-2 shadow-lg bg-base-100 rounded-box w-52 border border-base-300">
                                    <li><a onclick="edit_team_modal.showModal()">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                        </svg>
                                        Edit Team
                                    </a></li>
                                    <li><a onclick="delete_team_modal.showModal()" class="text-error">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                        </svg>
                                        Delete Team
                                    </a></li>
                                </ul>
                            </div>
                        </div>
                        <div class="space-y-3 text-sm">
                            <div>
                                <p class="font-semibold">Team ID</p>
                                <p class="text-base-content/70">{{ $team->id }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Team Name</p>
                                <p class="text-base-content/70">{{ $team->team_name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Company</p>
                                <p class="text-base-content/70">{{ $team->company?->name ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Captain Email</p>
                                <p class="text-base-content/70">{{ $team->captain_email }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Captain Phone</p>
                                <p class="text-base-content/70">{{ $team->captain_phone }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Total Members</p>
                                <p class="text-base-content/70">{{ $team->members->count() }}/12</p>
                            </div>
                            <div>
                                <p class="font-semibold">Captain</p>
                                <p class="text-base-content/70">{{ $team->captain()?->name ?? 'Not assigned' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Status</p>
                                <div class="flex gap-2">
                                    @if($team->locked)
                                        <span class="badge badge-warning">Locked</span>
                                    @else
                                        <span class="badge badge-info">Open</span>
                                    @endif
                                    @if($team->approved)
                                        <span class="badge badge-success">Approved</span>
                                    @else
                                        <span class="badge badge-ghost">Pending</span>
                                    @endif
                                </div>
                            </div>
                            <div>
                                <p class="font-semibold">Created</p>
                                <p class="text-base-content/70">{{ $team->created_at->format('M d, Y h:i A') }}</p>
                            </div>
                        </div>

                        @if(!$team->approved)
                        <div class="mt-4">
                            <livewire:admin.approve-team :team="$team" />
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Team Members Card -->
            <div class="lg:col-span-2">
                <div class="card bg-base-100 shadow-sm border-base-300 border-1">
                    <div class="card-body">
                        <!-- Team Composition Progress -->
                        <div class="mb-4 p-4 bg-base-200 rounded-lg">
                            <h3 class="font-semibold mb-3">Team Composition</h3>

                            <!-- Progress Bars -->
                            <div class="space-y-3">
                                <div>
                                    <div class="flex justify-between text-sm mb-1">
                                        <span>Male Players (Required: 6, Max: 9)</span>
                                        <span class="font-semibold {{ $team->getMaleCount() >= 6 ? 'text-success' : 'text-warning' }}">
                                            {{ $team->getMaleCount() }}/9
                                        </span>
                                    </div>
                                    <progress
                                        class="progress {{ $team->getMaleCount() >= 6 ? 'progress-success' : 'progress-warning' }} w-full"
                                        value="{{ $team->getMaleCount() }}"
                                        max="9"
                                    ></progress>
                                </div>

                                <div>
                                    <div class="flex justify-between text-sm mb-1">
                                        <span>Female Players (Required: 2, Max: 3)</span>
                                        <span class="font-semibold {{ $team->getFemaleCount() >= 2 ? 'text-success' : 'text-warning' }}">
                                            {{ $team->getFemaleCount() }}/3
                                        </span>
                                    </div>
                                    <progress
                                        class="progress {{ $team->getFemaleCount() >= 2 ? 'progress-success' : 'progress-warning' }} w-full"
                                        value="{{ $team->getFemaleCount() }}"
                                        max="3"
                                    ></progress>
                                </div>
                            </div>

                            <!-- Validation Status -->
                            @if($team->members->count() > 0)
                            <div class="mt-3">
                                @if($team->isValidConfiguration())
                                    <div class="alert alert-success">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span>Team meets all requirements!</span>
                                    </div>
                                @elseif($team->meetsMinimumRequirements())
                                    <div class="alert alert-info">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                                        </svg>
                                        <span>Team meets minimum requirements. Can add {{ 12 - $team->members->count() }} more player(s).</span>
                                    </div>
                                @else
                                    <div class="alert alert-warning">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                        </svg>
                                        <span>
                                            Team doesn't meet requirements. Need:
                                            @if($team->getMaleCount() < 6) {{ 6 - $team->getMaleCount() }} more male player(s) @endif
                                            @if($team->getFemaleCount() < 2) {{ 2 - $team->getFemaleCount() }} more female player(s) @endif
                                        </span>
                                    </div>
                                @endif
                            </div>
                            @endif
                        </div>

                        <!-- Members Grid -->
                        <livewire:admin.team-members-manager :team="$team" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Team Modal -->
        <dialog id="edit_team_modal" class="modal">
            <div class="modal-box">
                <h3 class="font-bold text-lg mb-4">Edit Team</h3>
                <livewire:admin.edit-team :team="$team" />
            </div>
            <form method="dialog" class="modal-backdrop">
                <button>close</button>
            </form>
        </dialog>

        <!-- Delete Team Modal -->
        <dialog id="delete_team_modal" class="modal">
            <div class="modal-box">
                <h3 class="font-bold text-lg mb-4">Delete Team</h3>
                <livewire:admin.delete-team :team="$team" />
            </div>
            <form method="dialog" class="modal-backdrop">
                <button>close</button>
            </form>
        </dialog>
    </div>
</x-layouts.admin>
