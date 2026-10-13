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
            <livewire:company.team-info :team="$team" />

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
