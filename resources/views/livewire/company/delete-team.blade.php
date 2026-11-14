<?php

use App\Models\Team;
use App\Helpers\CompanyAuth;
use App\Services\Tournament\RosterProtectionService;
use Livewire\Volt\Component;

new class extends Component {
    public Team $team;

    public function delete(): void
    {
        $this->resetErrorBag('team');
        $user = CompanyAuth::user();
        abort_if($user?->is_scorer, 403);

        if (!$user || $this->team->user_id !== $user->id) {
            $this->redirect(route('company.teams'), navigate: true);
            return;
        }

        app(RosterProtectionService::class)->deleteTeam($this->team->id, $user);

        $this->redirect(route('company.teams'), navigate: true);
    }
}; ?>

<div>
    @error('team')
        <div role="alert" class="alert alert-error mb-4">{{ $message }}</div>
    @enderror
    <div class="alert alert-warning mb-4">
        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        <span>This action cannot be undone. All team members will also be deleted.</span>
    </div>

    <p class="mb-6">Are you sure you want to delete team <strong>{{ $team->team_name }}</strong>?</p>

    <div class="flex gap-2 justify-end">
        <button type="button" onclick="delete_team_modal.close()" class="btn btn-ghost">
            Cancel
        </button>
        <button wire:click="delete" class="btn btn-error">
            Delete Team
        </button>
    </div>
</div>
