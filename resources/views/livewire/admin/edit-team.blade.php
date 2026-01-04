<?php

use App\Models\Team;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new class extends Component {
    public Team $team;

    #[Validate('required|string|max:255')]
    public string $team_name = '';

    #[Validate('required|email|max:255')]
    public string $captain_email = '';

    #[Validate('required|string|max:20')]
    public string $captain_phone = '';

    public function mount(): void
    {
        $this->team_name = $this->team->team_name;
        $this->captain_email = $this->team->captain_email;
        $this->captain_phone = $this->team->captain_phone;
    }

    public function update(): void
    {
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

        <!-- Captain Email -->
        <div class="form-control">
            <label class="label">
                <span class="label-text font-medium">Team Captain Email</span>
            </label>
            <input
                type="email"
                wire:model="captain_email"
                class="input input-bordered w-full @error('captain_email') input-error @enderror"
                placeholder="captain@example.com"
                required
            />
            @error('captain_email')
                <label class="label">
                    <span class="label-text-alt text-error">{{ $message }}</span>
                </label>
            @enderror
        </div>

        <!-- Captain Phone -->
        <div class="form-control">
            <label class="label">
                <span class="label-text font-medium">Team Captain Phone</span>
            </label>
            <input
                type="tel"
                wire:model="captain_phone"
                class="input input-bordered w-full @error('captain_phone') input-error @enderror"
                placeholder="+1 (555) 123-4567"
                required
            />
            @error('captain_phone')
                <label class="label">
                    <span class="label-text-alt text-error">{{ $message }}</span>
                </label>
            @enderror
        </div>

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
