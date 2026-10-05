<?php

use App\Models\User;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $filter = 'verified';

    public function promoteToAdmin(int $userId): void
    {
        $user = User::findOrFail($userId);

        // Only promote users without a company
        if ($user->company_id) {
            $this->dispatch('error', message: 'Cannot promote users with a company to admin');
            return;
        }

        $user->update(['role' => 'admin']);

        $this->dispatch('user-promoted');
    }

    public function demoteFromAdmin(int $userId): void
    {
        $user = User::findOrFail($userId);
        $user->update(['role' => 'company']);

        $this->dispatch('user-demoted');
    }

    #[On('scorer-created')]
    public function refreshList(): void
    {
        // Triggers re-render to show new scorer
    }

    public function with(): array
    {
        $query = User::with('company');

        if ($this->filter === 'admins') {
            $query->where('role', 'admin');
        } elseif ($this->filter === 'company') {
            $query->whereNotNull('company_id');
        } elseif ($this->filter === 'verified') {
            $query->whereNotNull('email_verified_at');
        }

        return [
            'users' => $query->latest()->paginate(10),
        ];
    }
}; ?>

<div>
    <div class="card bg-base-100 shadow-sm border-base-300 border-1">
        <div class="card-body">
            <!-- Filter tabs and Actions -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4">
                <div class="tabs tabs-boxed">
                    <a wire:click="$set('filter', 'all')" class="tab {{ $filter === 'all' ? 'tab-active' : '' }}">All Users</a>
                    <a wire:click="$set('filter', 'verified')" class="tab {{ $filter === 'verified' ? 'tab-active' : '' }}">Verified</a>
                    <a wire:click="$set('filter', 'admins')" class="tab {{ $filter === 'admins' ? 'tab-active' : '' }}">Admins</a>
                    <a wire:click="$set('filter', 'company')" class="tab {{ $filter === 'company' ? 'tab-active' : '' }}">With Company</a>
                </div>
                <div>
                    <livewire:admin.create-scorer-modal />
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="table table-zebra">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Company</th>
                            <th>Role</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $user)
                            <tr>
                                <td>{{ $user->id }}</td>
                                <td class="font-semibold">{{ $user->name }}</td>
                                <td>{{ $user->email }}</td>
                                <td>{{ $user->company?->name ?? 'N/A' }}</td>
                                <td>
                                    @if($user->isAdmin())
                                        <span class="badge badge-primary badge-sm">Admin</span>
                                    @elseif($user->isScorer())
                                        <span class="badge badge-secondary badge-sm">Scorer</span>
                                    @else
                                        <span class="badge badge-ghost badge-sm">Company</span>
                                    @endif
                                </td>
                                <td>
                                    @if(!$user->company_id)
                                        @if($user->isAdmin())
                                            <button
                                                wire:click="demoteFromAdmin({{ $user->id }})"
                                                wire:confirm="Are you sure you want to demote this admin?"
                                                class="btn btn-warning btn-xs"
                                            >
                                                Demote
                                            </button>
                                        @else
                                            <button
                                                wire:click="promoteToAdmin({{ $user->id }})"
                                                wire:confirm="Are you sure you want to promote this user to admin?"
                                                class="btn btn-success btn-xs"
                                            >
                                                Promote to Admin
                                            </button>
                                        @endif
                                    @else
                                        <span class="text-xs text-base-content/50">Company user</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-base-content/70">No users found</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $users->links() }}
            </div>
        </div>
    </div>
</div>
