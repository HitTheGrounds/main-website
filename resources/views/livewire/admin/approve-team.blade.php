<?php

use App\Models\Team;
use Livewire\Volt\Component;

new class extends Component {
    public Team $team;

    public function approve(): void
    {
        $user = request()->attributes->get('user');

        if (!$user || !$user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $this->team->update(['approved' => true]);

        $this->dispatch('team-approved');
        $this->redirect(route('admin.teams.show', $this->team), navigate: true);
    }
}; ?>

<div>
    <button
        wire:click="approve"
        wire:confirm="Are you sure you want to approve this team?"
        class="btn btn-success btn-block"
    >
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        Approve Team
    </button>
</div>
