<?php

use App\Models\Team;
use App\Models\TeamMember;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public Team $team;
    public string $member_name = '';
    public string $gender = '';
    public $photo = null;
    public ?string $existingPicture = null;
    public bool $is_captain = false;
    public ?int $editingId = null;
    public bool $showModal = false;

    public function mount(Team $team): void
    {
        $this->team = $team;
    }

    public function getExistingMemberProperty()
    {
        if ($this->editingId) {
            return TeamMember::find($this->editingId);
        }
        return null;
    }

    public function openAddModal(): void
    {
        if ($this->team->locked) {
            return;
        }
        $this->reset('member_name', 'gender', 'photo', 'existingPicture', 'is_captain', 'editingId');
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        if ($this->team->locked) {
            return;
        }

        $member = TeamMember::find($id);
        if ($member && $member->team_id === $this->team->id) {
            $this->editingId = $id;
            $this->member_name = $member->name;
            $this->gender = $member->gender;
            $this->is_captain = $member->is_captain;
            $this->photo = null;
            $this->existingPicture = $member->picture;
            $this->showModal = true;
        }
    }

    public function saveMember(): void
    {
        // Check if team is locked
        if ($this->team->locked) {
            $this->addError('member_name', 'This team is locked and cannot be modified.');
            return;
        }

        // Check member limit
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
            'photo' => ['nullable', 'image', 'max:2048'],
            'is_captain' => ['boolean'],
        ]);

        $data = [
            'name' => $validated['member_name'],
            'gender' => $validated['gender'],
            'is_captain' => $this->is_captain,
        ];

        if ($this->photo) {
            $data['picture'] = $this->photo->store('team-members', 'public');
        }

        if ($this->editingId) {
            $member = TeamMember::find($this->editingId);
            if ($member && $member->team_id === $this->team->id) {
                // Delete old picture if new one is uploaded
                if ($this->photo && $member->picture) {
                    Storage::disk('public')->delete($member->picture);
                }
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

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->reset('member_name', 'gender', 'photo', 'existingPicture', 'is_captain', 'editingId');
    }

    public function deleteMember(int $id): void
    {
        // Check if team is locked
        if ($this->team->locked) {
            return;
        }

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
}; ?>

<div>
    <div class="card bg-base-100 border-base-300 border-2">
        <div class="card-body">
            <div class="flex justify-between items-center mb-4">
                <h2 class="card-title">Team Members ({{ $team->members->count() }}/12)</h2>
                @if(!$team->locked)
                <button wire:click="openAddModal" class="btn btn-primary btn-sm" {{ $team->members->count() >= 12 ? 'disabled' : '' }}>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Add Member
                </button>
                @endif
            </div>

            <!-- Team Composition Progress -->
            <div class="mb-4 p-4 bg-base-200 rounded-lg">
                <h3 class="font-semibold mb-3">Team Composition</h3>

                <!-- Progress Bars -->
                <div class="space-y-3">
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span>Male Players</span>
                            <span class="font-semibold {{ $team->getMaleCount() >= 9 ? 'text-success' : 'text-warning' }}">
                                {{ $team->getMaleCount() }}/9
                            </span>
                        </div>
                        <progress
                            class="progress {{ $team->getMaleCount() >= 9 ? 'progress-success' : 'progress-warning' }} w-full"
                            value="{{ $team->getMaleCount() }}"
                            max="9"
                        ></progress>
                    </div>

                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span>Female Players</span>
                            <span class="font-semibold {{ $team->getFemaleCount() >= 3 ? 'text-success' : 'text-warning' }}">
                                {{ $team->getFemaleCount() }}/3
                            </span>
                        </div>
                        <progress
                            class="progress {{ $team->getFemaleCount() >= 3 ? 'progress-success' : 'progress-warning' }} w-full"
                            value="{{ $team->getFemaleCount() }}"
                            max="3"
                        ></progress>
                    </div>
                </div>

                <!-- Rules Info -->
                <div class="mt-3 text-sm text-base-content/70">
                    <div class="flex items-start gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 flex-shrink-0 mt-0.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                        </svg>
                        <div>
                            <p class="font-medium">Team Requirements:</p>
                            <ul class="list-disc list-inside mt-1 space-y-1">
                                <li>On field: 8 players</li>
                                <li>Reserved: 4 players</li>
                                <li>Total: 12 players (9 male, 3 female)</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Validation Status -->
                @if($team->members->count() > 0)
                <div class="mt-3">
                    @if($team->isValidConfiguration())
                        <div class="alert alert-success">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Team meets all requirements and can be locked!</span>
                        </div>
                    @elseif($team->meetsMinimumRequirements())
                        <div class="alert alert-info">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                            </svg>
                            <span>Team meets minimum requirements. You can add {{ 12 - $team->members->count() }} more player(s).</span>
                        </div>
                    @else
                        <div class="alert alert-warning">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                            </svg>
                            <span>
                                Team doesn't meet requirements. Need:
                                @if($team->getMaleCount() < 9) {{ 9 - $team->getMaleCount() }} more male player(s) @endif
                                @if($team->getFemaleCount() < 3) {{ 3 - $team->getFemaleCount() }} more female player(s) @endif
                            </span>
                        </div>
                    @endif
                </div>
                @endif
            </div>

            @if($team->locked)
            <div class="alert alert-warning mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                </svg>
                <span>This team is locked. Members cannot be added, edited, or removed.</span>
            </div>
            @endif

            <!-- Members Grid -->
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
                                @if(!$team->locked)
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
                                @endif
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
                    <p class="text-sm text-base-content/50">Add team members (10-12 members required)</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Add/Edit Modal -->
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

            {{-- <div class="form-control">
                <label class="label">
                    <span class="label-text font-medium">Photo (Optional)</span>
                </label>
                <x-mary-file wire:model="photo" accept="image/png, image/jpeg">
                        <img src="{{ $photo ? $photo->temporaryUrl() : ($existingPicture ? Storage::url( $existingPicture) : '/placeholder/male.avif') }}" class="h-40 rounded-lg" />
                </x-mary-file>
                @error('photo')
                    <label class="label">
                        <span class="label-text-alt text-error">{{ $message }}</span>
                    </label>
                @enderror
            </div> --}}

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
