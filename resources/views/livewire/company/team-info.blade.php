<?php

use App\Models\Team;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component {
    public Team $team;

    #[On('team-members-updated.{team.id}')]
    #[On('team-updated')]
    public function refreshTeam(): void
    {
        $this->team->refresh();
    }
}; ?>

<div class="lg:col-span-1">
    <div class="card bg-base-100 border-base-300 border-2">
        <div class="card-body">
            <div class="flex justify-between items-center mb-4">
                <h2 class="card-title">Team Information</h2>
                @if(!$team->locked)
                    <div class="dropdown dropdown-end">
                        <label tabindex="0" class="btn btn-ghost btn-sm btn-circle">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 6.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 12.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 18.75a.75.75 0 110-1.5.75.75 0 010 1.5z" />
                            </svg>
                        </label>
                        <ul tabindex="0"
                            class="dropdown-content menu p-2 shadow-lg bg-base-100 rounded-box w-52 border border-base-300">
                            <li><a onclick="edit_team_modal.showModal()">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                        stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                    </svg>
                                    Edit Team
                                </a></li>
                            <li><a onclick="delete_team_modal.showModal()" class="text-error">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                        stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
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
                    <p class="font-semibold">Contact Person Email</p>
                    <p class="text-base-content/70">{{ $team->captain_email }}</p>
                </div>
                <div>
                    <p class="font-semibold">Contact Person Phone</p>
                    <p class="text-base-content/70">{{ $team->captain_phone }}</p>
                </div>
                <div>
                    <p class="font-semibold">Total Members</p>
                    <p class="text-base-content/70">{{ $team->members->count() }}/12</p>
                </div>
                <div>
                    <p class="font-semibold">Team Captain</p>
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