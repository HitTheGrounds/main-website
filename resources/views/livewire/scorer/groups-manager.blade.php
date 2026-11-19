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
    // Which popup is open: '' (none), 'create' or 'manage'. UI state only.
    public string $panel = '';

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
        $this->panel = 'manage';
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
            // Refresh the open group without reopening a popup the scorer closed.
            $panel = $this->panel;
            $this->openGroup($id);
            $this->panel = $panel;
        } else {
            $this->reset('editingGroupId', 'editingVersion', 'groupName', 'selectedTeamId', 'panel');
        }
        $this->notice = 'Group setup reloaded.';
    }

    public function createGroup(): void
    {
        $this->panel = 'create';
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
        $this->reset('editingGroupId', 'editingVersion', 'groupName', 'selectedTeamId', 'panel');
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

<div class="scorer-screen sg-screen space-y-5" x-data="{ panel: $wire.entangle('panel') }">
    <header class="scorer-heading sd-heading">
        <div class="sd-heading-copy">
            <h1 class="text-2xl font-bold sm:text-3xl">Group setup</h1>
            <p class="text-sm text-base-content/70">Tournament {{ $year }} · Assign existing approved teams to groups.</p>
            @if($year === 2026)
                <p class="text-sm text-base-content/70">The 2026 plan is seven groups of four teams. Group counts and sizes are flexible.</p>
            @endif
        </div>
        <div class="sg-heading-actions">
            <button type="button" wire:click="reloadGroups" wire:loading.attr="disabled" class="btn btn-ghost sd-btn-quiet">
                <x-scorer.icon name="refresh" /> Reload
            </button>
            <button type="button" x-on:click="panel = 'create'" class="btn btn-primary" aria-haspopup="dialog" aria-controls="create-group-dialog">
                <x-scorer.icon name="plus" /> Create group
            </button>
        </div>
        <p wire:loading role="status" class="sd-loading">Updating group setup…</p>
    </header>

    {{-- Feedback shows on the page only while no popup is open; each popup has its own. --}}
    @if($panel === '')
        <div class="scorer-feedback" aria-live="polite">
            @if($notice)<p role="status" class="alert alert-success">{{ $notice }}</p>@endif
            @foreach(['tournament', 'group', 'team', 'year'] as $field)
                @error($field)<p role="alert" class="alert alert-error mb-2">{{ $message }}</p>@enderror
            @endforeach
        </div>
    @endif

    <dl class="sg-stats">
        @foreach([
            'Groups' => $setup['groups']->count(),
            'Assigned teams' => $setup['groups']->sum('team_count'),
            'Approved unassigned teams' => $setup['unassigned_teams']->count(),
            'Expected group matches' => $setup['expected_matches'],
        ] as $label => $value)
            <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
        @endforeach
    </dl>

    <section aria-labelledby="all-groups-heading" class="sg-overview">
        <h2 id="all-groups-heading">Group overview</h2>
        <div class="sg-grid">
            @forelse($setup['groups'] as $group)
                <article wire:key="group-summary-{{ $group['id'] }}" class="sg-card" aria-labelledby="sg-group-{{ $group['id'] }}">
                    <header class="sg-card-head">
                        <div>
                            <h3 id="sg-group-{{ $group['id'] }}">{{ $group['name'] }}</h3>
                            <p>{{ $group['team_count'] }} teams · {{ $group['expected_matches'] }} expected group matches</p>
                        </div>
                        <button type="button" wire:click="openGroup({{ $group['id'] }})" wire:loading.attr="disabled" class="btn btn-outline btn-sm" aria-haspopup="dialog" aria-label="Manage {{ $group['name'] }}">
                            <x-scorer.icon name="pencil" /> Manage
                        </button>
                    </header>
                    <ul class="sg-team-list">
                        @forelse($group['teams'] as $team)
                            <li><x-tournament.team-badge :name="$team['name']" /><span>{{ $team['name'] }}</span></li>
                        @empty
                            <li class="sg-empty-row">No teams assigned yet.</li>
                        @endforelse
                    </ul>
                </article>
            @empty
                <div class="sd-empty">
                    <x-scorer.icon name="users" />
                    <p>No groups have been created for this tournament yet.</p>
                    <button type="button" x-on:click="panel = 'create'" class="btn btn-primary btn-sm"><x-scorer.icon name="plus" /> Create group</button>
                </div>
            @endforelse
        </div>
    </section>

    {{-- Create group popup. --}}
    <dialog id="create-group-dialog" class="sd-dialog sg-dialog-sm" aria-labelledby="create-group-heading" wire:ignore.self
            x-effect="panel === 'create' ? ($el.open || $el.showModal()) : ($el.open && $el.close())"
            x-on:close="panel = ''" x-on:click.self="$el.close()">
        <form wire:submit="createGroup" class="sd-dialog-panel">
            <header class="sd-dialog-head">
                <div>
                    <h2 id="create-group-heading">Create a group</h2>
                    <p>You can assign teams straight after creating it.</p>
                </div>
                <button type="button" x-on:click="$el.closest('dialog').close()" class="sd-icon-btn" aria-label="Close"><x-scorer.icon name="x" /></button>
            </header>
            <div class="sd-dialog-body">
                @if($panel === 'create')
                    <div class="scorer-feedback" aria-live="polite">
                        @foreach(['tournament', 'group', 'year'] as $field)
                            @error($field)<p role="alert" class="alert alert-error">{{ $message }}</p>@enderror
                        @endforeach
                    </div>
                @endif
                <label class="form-control" for="new-group-name"><span class="label">Group name</span>
                    <input id="new-group-name" wire:model="newGroupName" type="text" maxlength="100" required class="input w-full" placeholder="For example, Group A" aria-describedby="new-group-error" autofocus>
                </label>
                <p id="new-group-error" class="sg-field-error">@error('newGroupName'){{ $message }}@enderror</p>
            </div>
            <footer class="sd-dialog-foot">
                <button type="button" x-on:click="$el.closest('dialog').close()" class="btn btn-ghost">Cancel</button>
                <button type="submit" wire:loading.attr="disabled" class="btn btn-primary"><x-scorer.icon name="plus" /> Create group</button>
            </footer>
        </form>
    </dialog>

    {{-- Manage group popup, opened by openGroup() through "panel". --}}
    <dialog id="manage-group-dialog" class="sd-dialog" aria-labelledby="manage-group-heading" wire:ignore.self
            x-effect="panel === 'manage' ? ($el.open || $el.showModal()) : ($el.open && $el.close())"
            x-on:close="panel = ''" x-on:click.self="$el.close()">
        <div class="sd-dialog-panel">
            @if($selectedGroup)
                <header class="sd-dialog-head" wire:key="manage-{{ $selectedGroup['id'] }}">
                    <div>
                        <h2 id="manage-group-heading">Manage {{ $selectedGroup['name'] }}</h2>
                        <p>{{ $selectedGroup['team_count'] }} teams · {{ $selectedGroup['expected_matches'] }} expected group matches</p>
                    </div>
                    <button type="button" x-on:click="$el.closest('dialog').close()" class="sd-icon-btn" aria-label="Close"><x-scorer.icon name="x" /></button>
                </header>
                <div class="sd-dialog-body">
                    @if($panel === 'manage')
                        <div class="scorer-feedback" aria-live="polite">
                            @if($notice)<p role="status" class="alert alert-success">{{ $notice }}</p>@endif
                            @foreach(['tournament', 'group', 'team', 'year'] as $field)
                                @error($field)<p role="alert" class="alert alert-error">{{ $message }}</p>@enderror
                            @endforeach
                        </div>
                    @endif

                    <form wire:submit="renameGroup" class="sg-rename">
                        <label class="form-control" for="edit-group-name"><span class="label">Group name</span>
                            <input id="edit-group-name" wire:model="groupName" type="text" maxlength="100" required class="input w-full" aria-describedby="group-name-error">
                        </label>
                        <button type="submit" wire:loading.attr="disabled" class="btn btn-outline"><x-scorer.icon name="check" /> Save name</button>
                        <p id="group-name-error" class="sg-field-error">@error('groupName'){{ $message }}@enderror</p>
                    </form>

                    <section class="sg-section" aria-labelledby="sg-teams-heading">
                        <h3 id="sg-teams-heading" class="sg-section-title">Teams in {{ $selectedGroup['name'] }}</h3>
                        <ul class="sg-team-list sg-team-list-edit">
                            @forelse($selectedGroup['teams'] as $team)
                                <li wire:key="selected-team-{{ $selectedGroup['id'] }}-{{ $team['id'] }}">
                                    <x-tournament.team-badge :name="$team['name']" /><span>{{ $team['name'] }}</span>
                                    <button type="button" wire:click="removeTeam({{ $team['id'] }})" wire:confirm="Remove this unused assignment? The registered team and players will be kept." wire:loading.attr="disabled" class="btn btn-ghost btn-sm sg-remove" aria-label="Remove {{ $team['name'] }} from {{ $selectedGroup['name'] }}">
                                        <x-scorer.icon name="x" /> Remove
                                    </button>
                                </li>
                            @empty
                                <li class="sg-empty-row">No teams assigned yet.</li>
                            @endforelse
                        </ul>
                    </section>

                    <form wire:submit="assignTeam" class="sg-section sg-assign" aria-labelledby="sg-assign-heading">
                        <h3 id="sg-assign-heading" class="sg-section-title">Add a team</h3>
                        <div class="sg-assign-row">
                            <label class="form-control" for="team-choice"><span class="label">Approved unassigned team</span>
                                <select id="team-choice" wire:model="selectedTeamId" class="select w-full" required aria-describedby="team-choice-error">
                                    <option value="">Choose a team</option>
                                    @foreach($setup['unassigned_teams'] as $team)
                                        <option value="{{ $team->id }}">{{ $team->team_name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <button type="submit" wire:loading.attr="disabled" @disabled($setup['unassigned_teams']->isEmpty()) class="btn btn-primary"><x-scorer.icon name="plus" /> Assign team</button>
                        </div>
                        <p id="team-choice-error" class="sg-field-error">@error('selectedTeamId'){{ $message }}@enderror</p>
                        @if($setup['unassigned_teams']->isEmpty())
                            <p class="sd-note">No approved unassigned teams are available.</p>
                        @endif
                    </form>

                    <p class="sr-muted">Assignments cannot change once fixtures, saved figures or confirmed qualification depend on them. To move an unused team, remove its assignment here, then select the other group.</p>

                    <div class="sg-danger">
                        <div>
                            <strong>Delete group</strong>
                            <p>{{ $selectedGroup['team_count'] > 0 ? 'Remove every team first. Only empty groups can be deleted.' : 'This group is empty and can be deleted.' }}</p>
                        </div>
                        <button type="button" wire:click="deleteGroup" wire:confirm="Delete this empty group?" wire:loading.attr="disabled" @disabled($selectedGroup['team_count'] > 0) class="btn btn-outline btn-error btn-sm">
                            <x-scorer.icon name="trash" /> Delete empty group
                        </button>
                    </div>
                </div>
                <footer class="sd-dialog-foot">
                    <button type="button" x-on:click="$el.closest('dialog').close()" class="btn btn-primary">Done</button>
                </footer>
            @endif
        </div>
    </dialog>
</div>

