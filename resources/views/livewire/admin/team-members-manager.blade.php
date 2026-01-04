<?php

use App\Models\Team;
use App\Models\TeamMember;
use Livewire\Volt\Component;

new class extends Component {
    public Team $team;
    public string $member_name = '';
    public string $gender = '';
    public bool $is_captain = false;
    public ?int $editingId = null;
    public bool $showModal = false;

    public function mount(Team $team): void
    {
        $this->team = $team;
    }

    public function openAddModal(): void
    {
        $this->reset('member_name', 'gender', 'is_captain', 'editingId');
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        $member = TeamMember::find($id);
        if ($member && $member->team_id === $this->team->id) {
            $this->editingId = $id;
            $this->member_name = $member->name;
            $this->gender = $member->gender;
            $this->is_captain = $member->is_captain;
            $this->showModal = true;
        }
    }

    public function saveMember(): void
    {
        // Check member limit for new members
        if (!$this->editingId && $this->team->members()->count() >= 12) {
            $this->addError('member_name', 'Team can only have up to 12 members.');
            return;
        }

        // Check if trying to add another captain
        if ($this->is_captain) {
            $captainQuery = $this->team->members()->where('is_captain', true);
            if ($this->editingId) {
                $captainQuery->where('id', '!=', $this->editingId);
            }
            if ($captainQuery->exists()) {
                $this->addError('is_captain', 'Team can only have one captain.');
                return;
            }
        }

        $validated = $this->validate([
            'member_name' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'string', 'in:Male,Female,Other'],
            'is_captain' => ['boolean'],
        ]);

        $data = [
            'name' => $validated['member_name'],
            'gender' => $validated['gender'],
            'is_captain' => $this->is_captain,
        ];

        if ($this->editingId) {
            $member = TeamMember::find($this->editingId);
            if ($member && $member->team_id === $this->team->id) {
                $member->update($data);
            }
        } else {
            TeamMember::create([
                ...$data,
                'team_id' => $this->team->id,
            ]);
        }

        $this->closeModal();
        $this->team->refresh();
    }

    public function deleteMember(int $id): void
    {
        $member = TeamMember::find($id);
        if ($member && $member->team_id === $this->team->id) {
            // Delete picture if exists
            if ($member->picture) {
                Storage::disk('public')->delete($member->picture);
            }
            $member->delete();
            $this->team->refresh();
        }
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->reset('member_name', 'gender', 'is_captain', 'editingId');
    }
}; ?>

<div>
    <div class="flex justify-between items-center mb-4">
        <h2 class="card-title">Team Members ({{ $team->members->count() }}/12)</h2>
        <button wire:click="openAddModal" class="btn btn-primary btn-sm" {{ $team->members->count() >= 12 ? 'disabled' : '' }}>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Add Member
        </button>
    </div>

    @if($team->members->count() > 0)
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
            @foreach($team->members as $member)
                <div class="card bg-base-200 shadow-sm">
                    <figure class="px-4 pt-4">
                        <img
                            src="{{ $member->picture ? Storage::url($member->picture) : ($member->gender === 'Female' ? '/placeholder/female.avif' : '/placeholder/male.avif') }}"
                            alt="{{ $member->name }}"
                            class="rounded-lg h-32 w-32 object-cover"
                        />
                    </figure>
                    <div class="card-body p-4">
                        <h3 class="font-semibold text-sm">{{ $member->name }}</h3>
                        <p class="text-xs text-base-content/70">{{ $member->gender }}</p>
                        <div class="flex gap-1 flex-wrap mt-1">
                            @if($member->is_captain)
                                <span class="badge badge-primary badge-xs">Captain</span>
                            @else
                                <span class="badge badge-ghost badge-xs">Member</span>
                            @endif
                        </div>
                        <div class="card-actions justify-end mt-2">
                            <button
                                wire:click="openEditModal({{ $member->id }})"
                                class="btn btn-ghost btn-xs"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3 h-3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                </svg>
                            </button>
                            <button
                                wire:click="deleteMember({{ $member->id }})"
                                wire:confirm="Are you sure you want to remove this member?"
                                class="btn btn-ghost btn-xs text-error"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3 h-3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="text-center py-8">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 mx-auto opacity-50">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
            </svg>
            <p class="mt-4 text-base-content/70">No members added yet</p>
        </div>
    @endif

    <!-- Add/Edit Member Modal -->
    <x-mary-modal wire:model="showModal" title="{{ $editingId ? 'Edit' : 'Add' }} Team Member" class="backdrop-blur">
        <form wire:submit="saveMember" class="space-y-4">
            <div class="form-control">
                <label class="label">
                    <span class="label-text font-medium">Name</span>
                </label>
                <input
                    type="text"
                    wire:model="member_name"
                    class="input input-bordered w-full @error('member_name') input-error @enderror"
                    placeholder="Enter member name"
                />
                @error('member_name')
                    <label class="label">
                        <span class="label-text-alt text-error">{{ $message }}</span>
                    </label>
                @enderror
            </div>

            <div class="form-control">
                <label class="label">
                    <span class="label-text font-medium">Gender</span>
                </label>
                <select wire:model="gender" class="select select-bordered w-full @error('gender') select-error @enderror">
                    <option value="">Select Gender</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                </select>
                @error('gender')
                    <label class="label">
                        <span class="label-text-alt text-error">{{ $message }}</span>
                    </label>
                @enderror
            </div>

            <div class="form-control">
                <label class="label cursor-pointer justify-start gap-2">
                    <input
                        type="checkbox"
                        wire:model="is_captain"
                        class="checkbox"
                    />
                    <span class="label-text">Mark as Team Captain</span>
                </label>
                @error('is_captain')
                    <label class="label">
                        <span class="label-text-alt text-error">{{ $message }}</span>
                    </label>
                @enderror
            </div>

            <div class="modal-action">
                <button type="button" wire:click="closeModal" class="btn btn-ghost">
                    Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    {{ $editingId ? 'Update' : 'Add' }} Member
                </button>
            </div>
        </form>
    </x-mary-modal>
</div>
