<?php

use App\Models\User;
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

        $user->update(['is_admin' => true]);

        $this->dispatch('user-promoted');
    }

    public function demoteFromAdmin(int $userId): void
    {
        $user = User::findOrFail($userId);
        $user->update(['is_admin' => false]);

        $this->dispatch('user-demoted');
    }

    public function with(): array
    {
        $query = User::with('company');

        if ($this->filter === 'admins') {
            $query->where('is_admin', true);
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
    <div class="card bg-base-100 border-base-300 border-2">
        <div class="card-body">
            <!-- Filter tabs -->
            <div class="tabs tabs-boxed mb-4">
                <a wire:click="$set('filter', 'all')" class="tab {{ $filter === 'all' ? 'tab-active' : '' }}">All Users</a>
                <a wire:click="$set('filter', 'verified')" class="tab {{ $filter === 'verified' ? 'tab-active' : '' }}">Verified</a>
                <a wire:click="$set('filter', 'admins')" class="tab {{ $filter === 'admins' ? 'tab-active' : '' }}">Admins</a>
                <a wire:click="$set('filter', 'company')" class="tab {{ $filter === 'company' ? 'tab-active' : '' }}">With Company</a>
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
                                    @if($user->is_admin)
                                        <span class="badge badge-primary badge-sm">Admin</span>
                                    @else
                                        <span class="badge badge-ghost badge-sm">User</span>
                                    @endif
                                </td>
                                <td>
                                    @if(!$user->company_id)
                                        @if($user->is_admin)
                                            <button
                                                wire:click="demoteFromAdmin({{ $user->id }})"
                                                wire:confirm="Are you sure you want to demote this user from admin?"
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
