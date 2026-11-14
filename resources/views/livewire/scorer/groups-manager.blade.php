<?php

use App\Services\Tournament\TournamentGroupService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new class extends Component {
    public string $newGroupName = '';
    public string $groupName = '';
    public string $selectedTeamId = '';
    public string $notice = '';

    #[Locked]
    public ?int $editingGroupId = null;

    #[Locked]
    public ?int $editingVersion = null;

    public function with(): array
    {
        $setup = app(TournamentGroupService::class)->setup($this->year());

        return [
            'setup' => $setup,
            'year' => $this->year(),
            'selectedGroup' => $setup['groups']->firstWhere('id', $this->editingGroupId),
        ];
    }

    public function openGroup(int $id): void
    {
        $setup = app(TournamentGroupService::class)->setup($this->year());
        $group = $setup['groups']->firstWhere('id', $id);
        if (! $group) {
            throw ValidationException::withMessages(['group' => 'Select an existing group from this tournament.']);
        }
        $this->resetErrorBag();
        $this->notice = '';
        $this->editingGroupId = $group['id'];
        $this->editingVersion = $group['version'];
        $this->groupName = $group['name'];
        $this->selectedTeamId = '';
    }

    public function reloadGroups(): void
    {
        $setup = app(TournamentGroupService::class)->setup($this->year());
        $id = $this->editingGroupId;
        $this->resetErrorBag();
        if ($id !== null && $setup['groups']->contains('id', $id)) {
            $this->openGroup($id);
        } else {
            $this->reset('editingGroupId', 'editingVersion', 'groupName', 'selectedTeamId');
        }
        $this->notice = 'Group setup reloaded.';
    }

    public function createGroup(): void
    {
        $group = $this->perform(fn () => app(TournamentGroupService::class)->create($this->year(), $this->newGroupName), ['name' => 'newGroupName']);
        $this->newGroupName = '';
        $this->openGroup($group->id);
        $this->notice = 'Group created.';
    }

    public function renameGroup(): void
    {
        $group = $this->perform(function () {
            $this->requireSelection();

            return app(TournamentGroupService::class)->rename($this->year(), $this->editingGroupId, $this->editingVersion, $this->groupName);
        }, ['name' => 'groupName']);
        $this->editingVersion = $group->version;
        $this->groupName = $group->name;
        $this->notice = 'Group name saved.';
    }

    public function assignTeam(): void
    {
        $this->perform(function () {
            $this->requireSelection();
            Validator::make(['selectedTeamId' => $this->selectedTeamId], ['selectedTeamId' => ['required', 'integer', 'min:1']])->validate();
            app(TournamentGroupService::class)->assign($this->year(), $this->editingGroupId, $this->editingVersion, (int) $this->selectedTeamId);
        }, ['team' => 'selectedTeamId']);
        $this->editingVersion++;
        $this->selectedTeamId = '';
        $this->notice = 'Team assigned.';
    }

    public function removeTeam(int $teamId): void
    {
        $this->perform(function () use ($teamId) {
            $this->requireSelection();
            app(TournamentGroupService::class)->remove($this->year(), $this->editingGroupId, $this->editingVersion, $teamId);
        });
        $this->editingVersion++;
        $this->notice = 'Team assignment removed. The registered team is unchanged.';
    }

    public function deleteGroup(): void
    {
        $this->perform(function () {
            $this->requireSelection();
            app(TournamentGroupService::class)->delete($this->year(), $this->editingGroupId, $this->editingVersion);
        });
        $this->reset('editingGroupId', 'editingVersion', 'groupName', 'selectedTeamId');
        $this->notice = 'Empty group deleted.';
    }

    private function perform(callable $action, array $fields = []): mixed
    {
        $this->resetErrorBag();
        $this->notice = '';
        try {
            return $action();
        } catch (ValidationException $exception) {
            $messages = [];
            foreach ($exception->errors() as $field => $errors) {
                $messages[$fields[$field] ?? $field] = $errors;
            }
            throw ValidationException::withMessages($messages);
        }
    }

    private function requireSelection(): void
    {
        if ($this->editingGroupId === null || $this->editingVersion === null) {
            throw ValidationException::withMessages(['group' => 'Choose a group before making this change.']);
        }
    }

    private function year(): int
    {
        return (int) config('tournament.year');
    }
}; ?>

<div class="space-y-6">
    <div class="space-y-2 text-center">
        <h1 class="text-2xl font-bold sm:text-3xl">Group setup</h1>
        <p class="text-sm text-base-content/70">Tournament {{ $year }} · Assign existing approved teams to groups.</p>
        @if($year === 2026)
            <p class="text-sm text-base-content/70">The 2026 plan is seven groups of four teams. Group counts and sizes are flexible.</p>
        @endif
        <button type="button" wire:click="reloadGroups" wire:loading.attr="disabled" class="btn btn-ghost btn-sm">Reload group setup</button>
        <p wire:loading role="status" class="text-sm text-base-content/70">Updating group setup…</p>
    </div>

    <div aria-live="polite">
        @if($notice)
            <p role="status" class="alert alert-success">{{ $notice }}</p>
        @endif
        @foreach(['tournament', 'group', 'team', 'year'] as $field)
            @error($field)
                <p role="alert" class="alert alert-error mb-2">{{ $message }}</p>
            @enderror
        @endforeach
    </div>

    <dl class="grid grid-cols-2 gap-3 text-center lg:grid-cols-4">
        @foreach([
            'Groups' => $setup['groups']->count(),
            'Assigned teams' => $setup['groups']->sum('team_count'),
            'Approved unassigned teams' => $setup['unassigned_teams']->count(),
            'Expected group matches' => $setup['expected_matches'],
        ] as $label => $value)
            <div class="rounded-xl border border-base-300 bg-base-100 p-4">
                <dt class="text-sm text-base-content/70">{{ $label }}</dt>
                <dd class="mt-1 text-2xl font-bold">{{ $value }}</dd>
            </div>
        @endforeach
    </dl>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="card border border-base-300 bg-base-100 shadow-xs" aria-labelledby="create-group-heading">
            <div class="card-body">
                <h2 id="create-group-heading" class="text-xl font-bold text-center">Create a group</h2>
                <form wire:submit="createGroup" class="space-y-3">
                    <label class="block text-sm font-semibold" for="new-group-name">Group name</label>
                    <input id="new-group-name" wire:model="newGroupName" type="text" maxlength="100" required class="input input-bordered w-full" placeholder="For example, Group A" aria-describedby="new-group-error">
                    <p id="new-group-error" class="text-sm text-error">@error('newGroupName'){{ $message }}@enderror</p>
                    <button type="submit" wire:loading.attr="disabled" class="btn btn-primary w-full">Create group</button>
                </form>
            </div>
        </section>

        <section class="card border border-base-300 bg-base-100 shadow-xs" aria-labelledby="manage-group-heading">
            <div class="card-body space-y-3">
                <h2 id="manage-group-heading" class="text-xl font-bold text-center">Manage a group</h2>
                <label for="group-choice" class="text-sm font-semibold">Choose a group</label>
                <select id="group-choice" wire:change="openGroup($event.target.value)" wire:loading.attr="disabled" class="select select-bordered w-full">
                    <option value="" disabled @selected($editingGroupId === null)>Choose a group</option>
                    @foreach($setup['groups'] as $group)
                        <option value="{{ $group['id'] }}" @selected($editingGroupId === $group['id'])>{{ $group['name'] }} ({{ $group['team_count'] }} teams)</option>
                    @endforeach
                </select>
                @if($selectedGroup)
                    <form wire:submit="renameGroup" class="space-y-3">
                        <label for="edit-group-name" class="block text-sm font-semibold">Group name</label>
                        <input id="edit-group-name" wire:model="groupName" type="text" maxlength="100" required class="input input-bordered w-full" aria-describedby="group-name-error">
                        <p id="group-name-error" class="text-sm text-error">@error('groupName'){{ $message }}@enderror</p>
                        <button type="submit" wire:loading.attr="disabled" class="btn btn-outline btn-sm">Save name</button>
                    </form>
                    <form wire:submit="assignTeam" class="space-y-3">
                        <label for="team-choice" class="block text-sm font-semibold">Approved unassigned team</label>
                        <select id="team-choice" wire:model="selectedTeamId" class="select select-bordered w-full" required aria-describedby="team-choice-error">
                            <option value="">Choose a team</option>
                            @foreach($setup['unassigned_teams'] as $team)
                                <option value="{{ $team->id }}">{{ $team->team_name }}</option>
                            @endforeach
                        </select>
                        <p id="team-choice-error" class="text-sm text-error">@error('selectedTeamId'){{ $message }}@enderror</p>
                        @if($setup['unassigned_teams']->isEmpty())
                            <p class="text-sm text-base-content/70">No approved unassigned teams are available.</p>
                        @endif
                        <button type="submit" wire:loading.attr="disabled" @disabled($setup['unassigned_teams']->isEmpty()) class="btn btn-primary btn-sm">Assign team</button>
                    </form>
                    <h3 class="text-base font-semibold text-center">Teams in {{ $selectedGroup['name'] }}</h3>
                    <ul class="divide-y divide-base-300">
                        @forelse($selectedGroup['teams'] as $team)
                            <li wire:key="selected-team-{{ $selectedGroup['id'] }}-{{ $team['id'] }}" class="flex flex-wrap items-center justify-between gap-2 py-2">
                                <span class="break-words">{{ $team['name'] }}</span>
                                <button type="button" wire:click="removeTeam({{ $team['id'] }})" wire:confirm="Remove this unused assignment? The registered team and players will be kept." wire:loading.attr="disabled" class="btn btn-ghost btn-sm text-error" aria-label="Remove {{ $team['name'] }} from {{ $selectedGroup['name'] }}">Remove</button>
                            </li>
                        @empty
                            <li class="py-2 text-sm text-base-content/70 text-center">No teams assigned yet.</li>
                        @endforelse
                    </ul>
                    <p class="text-sm text-base-content/70">Assignments cannot change once fixtures, saved figures or confirmed qualification depend on them. To move an unused team, remove its assignment here, then select the other group.</p>
                    <button type="button" wire:click="deleteGroup" wire:confirm="Delete this empty group?" wire:loading.attr="disabled" @disabled($selectedGroup['team_count'] > 0) class="btn btn-outline btn-error btn-sm">Delete empty group</button>
                @else
                    <p class="text-sm text-base-content/70 text-center">Create or choose a group to manage its teams.</p>
                @endif
            </div>
        </section>
    </div>

    <section aria-labelledby="all-groups-heading" class="space-y-4">
        <h2 id="all-groups-heading" class="text-xl font-bold text-center">All groups</h2>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @forelse($setup['groups'] as $group)
                <article wire:key="group-summary-{{ $group['id'] }}" class="card border border-base-300 bg-base-100 shadow-xs">
                    <div class="card-body text-center">
                        <h3 class="card-title justify-center break-words">{{ $group['name'] }}</h3>
                        <p class="text-sm text-base-content/70">{{ $group['team_count'] }} teams · {{ $group['expected_matches'] }} expected group matches</p>
                        <ul class="space-y-1 text-sm">
                            @forelse($group['teams'] as $team)
                                <li class="break-words">{{ $team['name'] }}</li>
                            @empty
                                <li class="text-base-content/70">No teams assigned yet.</li>
                            @endforelse
                        </ul>
                    </div>
                </article>
            @empty
                <p class="text-center text-base-content/70 sm:col-span-2 xl:col-span-3">No groups have been created for this tournament yet.</p>
            @endforelse
        </div>
    </section>
</div>
