<?php

use App\Models\Team;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $filter = 'all';

    public function approveTeam(int $teamId): void
    {
        $team = Team::findOrFail($teamId);
        $team->update(['approved' => true]);

        $this->dispatch('team-approved');
    }

    public function toggleLock(int $teamId): void
    {
        $team = Team::findOrFail($teamId);
        $team->update(['locked' => !$team->locked]);

        $this->dispatch('team-lock-toggled');
    }

    public function with(): array
    {
        $query = Team::with(['company', 'user', 'members']);

        if ($this->filter === 'pending') {
            $query->where('approved', false);
        } elseif ($this->filter === 'approved') {
            $query->where('approved', true);
        }

        return [
            'teams' => $query->latest()->paginate(10),
        ];
    }
}; ?>

<div>
    <div class="card bg-base-100 shadow-sm border-base-300 border-1">
        <div class="card-body">
            <!-- Filter tabs -->
            <div class="tabs tabs-boxed mb-4">
                <a wire:click="$set('filter', 'all')" class="tab {{ $filter === 'all' ? 'tab-active' : '' }}">All Teams</a>
                <a wire:click="$set('filter', 'pending')" class="tab {{ $filter === 'pending' ? 'tab-active' : '' }}">Pending Approval</a>
                <a wire:click="$set('filter', 'approved')" class="tab {{ $filter === 'approved' ? 'tab-active' : '' }}">Approved</a>
            </div>

            <div class="overflow-x-auto">
                <table class="table table-zebra">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Team Name</th>
                            <th>Company</th>
                            <th>Captain</th>
                            <th>Members</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($teams as $team)
                            <tr>
                                <td>{{ $team->id }}</td>
                                <td>
                                    <a href="{{ route('admin.teams.show', $team) }}" wire:navigate class="font-semibold link link-hover link-primary">
                                        {{ $team->team_name }}
                                    </a>
                                </td>
                                <td>{{ $team->company?->name ?? 'N/A' }}</td>
                                <td>{{ $team->captain_email }}</td>
                                <td>{{ $team->members->count() }}</td>
                                <td>
                                    <div class="flex gap-2">
                                        @if($team->locked)
                                            <span class="badge badge-warning badge-sm">Locked</span>
                                        @endif
                                        @if($team->approved)
                                            <span class="badge badge-success badge-sm">Approved</span>
                                        @else
                                            <span class="badge badge-ghost badge-sm">Pending</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="flex gap-2">
                                        @if(!$team->approved)
                                            <button
                                                wire:click="approveTeam({{ $team->id }})"
                                                wire:confirm="Are you sure you want to approve this team?"
                                                class="btn btn-success btn-xs"
                                            >
                                                Approve
                                            </button>
                                        @endif
                                        <button
                                            wire:click="toggleLock({{ $team->id }})"
                                            wire:confirm="Are you sure you want to {{ $team->locked ? 'unlock' : 'lock' }} this team?"
                                            class="btn {{ $team->locked ? 'btn-warning' : 'btn-error' }} btn-xs"
                                        >
                                            {{ $team->locked ? 'Unlock' : 'Lock' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-base-content/70">No teams found</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $teams->links() }}
            </div>
        </div>
    </div>
</div>
