<?php

use App\Models\Team;
use App\Helpers\CompanyAuth;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component {
    public Team $team;

    #[On('team-members-updated.{team.id}')]
    public function refreshTeam(): void
    {
        $this->team->refresh();
    }

    public function lock(): void
    {
        $user = CompanyAuth::user();

        if (!$user || $this->team->user_id !== $user->id) {
            $this->redirect(route('company.teams'), navigate: true);
            return;
        }

        if ($this->team->locked) {
            return;
        }

        // Check if team meets requirements
        if (!$this->team->isValidConfiguration()) {
            $this->dispatch('error', message: 'Team does not meet the required composition (Min: 6 male, 2 female)');
            return;
        }

        $this->team->update(['locked' => true]);

        $this->dispatch('team-locked');
        $this->redirect(route('company.teams.show', $this->team), navigate: true);
    }
}; ?>

<div>
    @php
        $canLock = $team->isValidConfiguration();
    @endphp

    <button
        wire:click="lock"
        wire:confirm="Are you sure you want to lock this team? This action cannot be undone and will prevent any future edits."
        class="btn btn-block btn-sm {{ $canLock ? 'btn-warning' : 'btn-disabled' }}"
        {{ !$canLock ? 'disabled' : '' }}
    >
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
        </svg>
        Lock Team {{ $canLock ? '(Permanent)' : '(Requirements Not Met)' }}
    </button>

    @if(!$canLock)
    <p class="text-xs text-warning mt-2 text-center">
        Team must meet composition requirements before locking
    </p>
    @endif
</div>
