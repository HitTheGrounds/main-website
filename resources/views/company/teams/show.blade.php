<x-layouts.company>
    <div>
        <div class="mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-base-content">{{ $team->team_name }}</h1>
                    <p class="mt-2 text-base-content/70">Team Details & Members</p>
                </div>
                <a href="{{ route('company.teams') }}" class="btn btn-ghost">
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
                <div class="card bg-base-100 border-base-300 border-2">
                    <div class="card-body">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="card-title">Team Information</h2>
                            @if(!$team->locked)
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
                            @endif
                        </div>
                        <div class="space-y-3 text-sm">
                            <div>
                                <p class="font-semibold">Team Name</p>
                                <p class="text-base-content/70">{{ $team->team_name }}</p>
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
                        </div>
                        @if(!$team->locked)
                        <div class="mt-4">
                            <livewire:company.lock-team :team="$team" />
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Team Members Card -->
            <div class="lg:col-span-2">
                <livewire:company.team-members :team="$team" />
            </div>
        </div>

        <!-- Edit Team Modal -->
        <dialog id="edit_team_modal" class="modal">
            <div class="modal-box">
                <h3 class="font-bold text-lg mb-4">Edit Team</h3>
                <livewire:company.edit-team :team="$team" />
            </div>
            <form method="dialog" class="modal-backdrop">
                <button>close</button>
            </form>
        </dialog>

        <!-- Delete Team Modal -->
        <dialog id="delete_team_modal" class="modal">
            <div class="modal-box">
                <h3 class="font-bold text-lg mb-4">Delete Team</h3>
                <livewire:company.delete-team :team="$team" />
            </div>
            <form method="dialog" class="modal-backdrop">
                <button>close</button>
            </form>
        </dialog>
    </div>
</x-layouts.company>
