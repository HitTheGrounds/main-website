<?php

use App\Models\Team;
use App\Helpers\CompanyAuth;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new class extends Component {
    public Team $team;

    #[Validate('required|string|max:255')]
    public string $team_name = '';

    public function mount(): void
    {
        $this->team_name = $this->team->team_name;
    }

    public function update(): void
    {
        $user = CompanyAuth::user();

        if (!$user || $this->team->user_id !== $user->id) {
            $this->redirect(route('company.teams'), navigate: true);
            return;
        }

        // Prevent editing locked teams
        if ($this->team->locked) {
            $this->addError('team_name', 'This team is locked and cannot be edited.');
            return;
        }

        $validated = $this->validate();

        $this->team->update($validated);

        $this->dispatch('team-updated');
        $this->js('edit_team_modal.close()');
    }
}; ?>

<div>
    <form wire:submit="update" class="space-y-4">
        <!-- Team Name -->
        <div class="form-control">
            <label class="label">
                <span class="label-text font-medium">Team Name</span>
            </label>
            <input
                type="text"
                wire:model="team_name"
                class="input input-bordered w-full @error('team_name') input-error @enderror"
                placeholder="Enter team name"
                required
            />
            @error('team_name')
                <label class="label">
                    <span class="label-text-alt text-error">{{ $message }}</span>
                </label>
            @enderror
        </div>

        {{-- Captain email and phone are managed through member management --}}

        <!-- Submit Buttons -->
        <div class="flex gap-2 justify-end mt-6">
            <button type="button" onclick="edit_team_modal.close()" class="btn btn-ghost">
                Cancel
            </button>
            <button type="submit" class="btn btn-primary">
                Update Team
            </button>
        </div>
    </form>
</div>
