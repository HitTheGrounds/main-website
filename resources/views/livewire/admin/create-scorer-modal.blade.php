<?php

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component {
    public string $name = '';
    public string $email = '';
    public bool $show = false;

    public function createScorer(): void
    {
        $user = request()->attributes->get('user') ?? auth()->user();
        if (!$user || !$user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
        ]);

        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'role' => 'scorer',
            'company_id' => null,
            // We can pre-verify the email since the admin created them
            'email_verified_at' => now(),
        ]);

        $this->show = false;
        $this->reset(['name', 'email']);
        
        $this->dispatch('scorer-created');
    }
}; ?>

<div>
    <x-mary-button label="Create Scorer Account" wire:click="$set('show', true)" class="btn-primary btn-sm" icon="o-plus" />

    <x-mary-modal wire:model="show" title="Create Scorer Account" class="backdrop-blur">
        <form wire:submit="createScorer" class="flex flex-col gap-4">
            <x-mary-input
                wire:model="name"
                label="Name"
                type="text"
                required
                autofocus
            />

            <x-mary-input
                wire:model="email"
                label="Email"
                type="email"
                required
            />

            <x-slot:actions>
                <x-mary-button label="Cancel" wire:click="$set('show', false)" class="btn-ghost" />
                <x-mary-button type="submit" label="Create Scorer" class="btn-primary" />
            </x-slot:actions>
        </form>
    </x-mary-modal>
</div>
