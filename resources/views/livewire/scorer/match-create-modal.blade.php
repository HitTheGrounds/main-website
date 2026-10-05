<?php

use App\Models\Team;
use App\Models\TournamentGroup;
use App\Models\TournamentMatch;
use App\Services\TournamentStandingsService;
use Livewire\Volt\Component;
use Livewire\Attributes\On;

new class extends Component {
    public bool $show = false;
    public bool $showConfirm = false;
    public ?int $editingMatchId = null;

    public string $stage = 'G';
    public ?int $group_id = null;
    public ?int $bracket_position = null;
    
    public ?int $team1_id = null;
    public ?int $team2_id = null;
    public ?int $batting_first_id = null;
    
    public string $status = 'upcoming';

    // Scores
    public ?int $team1_score = null;
    public ?int $team1_overs = null;
    public ?int $team1_balls = null;
    public ?int $team1_wickets = null;

    public ?int $team2_score = null;
    public ?int $team2_overs = null;
    public ?int $team2_balls = null;
    public ?int $team2_wickets = null;

    public string $outcome = ''; // 'team1', 'team2', 'tie'

    public $groups = [];
    public $availableTeams = [];

    public function mount()
    {
        $this->groups = TournamentGroup::all();
        $this->updateAvailableTeams();
    }

    public $original_updated_at = null;
    public bool $sfConflictWarning = false;
    
    public function loadMatch(int $matchId)
    {
        $match = TournamentMatch::find($matchId);
        if (!$match) return;

        $this->editingMatchId = $match->id;
        $this->original_updated_at = $match->updated_at?->timestamp;
        $this->sfConflictWarning = false;
        
        $this->stage = $match->stage;
        $this->group_id = $match->group_id;
        $this->bracket_position = $match->bracket_position;
        $this->updateAvailableTeams();
        
        $this->team1_id = $match->team1_id;
        $this->team2_id = $match->team2_id;
        $this->batting_first_id = $match->batting_first_id;
        $this->status = $match->status;
        
        $this->team1_score = $match->team1_score;
        $this->team1_overs = $match->team1_overs;
        $this->team1_balls = $match->team1_balls;
        $this->team1_wickets = $match->team1_wickets;
        
        $this->team2_score = $match->team2_score;
        $this->team2_overs = $match->team2_overs;
        $this->team2_balls = $match->team2_balls;
        $this->team2_wickets = $match->team2_wickets;
        
        if ($match->status === 'finished') {
            if ($match->is_draw) {
                $this->outcome = 'tie';
            } elseif ($match->winner_id === $this->team1_id) {
                $this->outcome = 'team1';
            } elseif ($match->winner_id === $this->team2_id) {
                $this->outcome = 'team2';
            }
        } else {
            $this->outcome = '';
        }
        
        $this->show = true;
    }

    public function getBallsPerOverProperty()
    {
        return config("tournament.balls_per_over.{$this->stage}", 4);
    }

    public function getMaxOversProperty()
    {
        return config('tournament.overs_per_match', 5);
    }

    public function getMaxWicketsProperty()
    {
        return config('tournament.max_wickets', 11);
    }

    public function updatedStage()
    {
        $this->group_id = null;
        $this->bracket_position = $this->stage === 'F' ? 1 : null;
        $this->team1_id = null;
        $this->team2_id = null;
        $this->sfConflictWarning = false;
        $this->updateAvailableTeams();
    }

    public function updatedGroupId()
    {
        $this->team1_id = null;
        $this->team2_id = null;
        $this->updateAvailableTeams();
    }
    
    public function updatedOutcome()
    {
        $this->sfConflictWarning = false; // Reset warning if they change the outcome again
    }

    public function updateAvailableTeams()
    {
        if ($this->stage === 'G') {
            if ($this->group_id) {
                $group = TournamentGroup::find($this->group_id);
                $this->availableTeams = $group ? $group->teams()->get() : collect();
            } else {
                $this->availableTeams = collect();
            }
        } elseif ($this->stage === 'QF') {
            $this->availableTeams = Team::whereHas('groupTeam', function($q) {
                $q->where('qualified', true);
            })->get();
        } elseif ($this->stage === 'SF') {
            $this->availableTeams = Team::whereIn('id', function($q) {
                $q->select('winner_id')->from('matches')->where('stage', 'QF')->whereNotNull('winner_id');
            })->get();
        } elseif ($this->stage === 'F') {
            $this->availableTeams = Team::whereIn('id', function($q) {
                $q->select('winner_id')->from('matches')->where('stage', 'SF')->whereNotNull('winner_id');
            })->get();
        }
    }

    public function validateMatch()
    {
        $rules = [
            'stage' => 'required|in:G,QF,SF,F',
            'team1_id' => 'required|exists:teams,id|different:team2_id',
            'team2_id' => 'required|exists:teams,id',
            'status' => 'required|in:upcoming,live,finished',
        ];

        if ($this->stage === 'G') {
            $rules['group_id'] = 'required|exists:tournament_groups,id';
        } elseif ($this->stage === 'QF') {
            $rules['bracket_position'] = 'required|integer|between:1,4';
        } elseif ($this->stage === 'SF') {
            $rules['bracket_position'] = 'required|integer|between:1,2';
        } elseif ($this->stage === 'F') {
            $this->bracket_position = 1;
            $rules['bracket_position'] = 'required|integer|in:1';
        }

        if ($this->status === 'finished') {
            $maxOvers = $this->maxOvers;
            $maxBalls = $this->ballsPerOver - 1;
            $maxWickets = $this->maxWickets;

            $rules = array_merge($rules, [
                'team1_score' => 'required|integer|min:0',
                'team1_overs' => "required|integer|min:0|max:{$maxOvers}",
                'team1_balls' => "required|integer|min:0|max:{$maxBalls}",
                'team1_wickets' => "required|integer|min:0|max:{$maxWickets}",
                
                'team2_score' => 'required|integer|min:0',
                'team2_overs' => "required|integer|min:0|max:{$maxOvers}",
                'team2_balls' => "required|integer|min:0|max:{$maxBalls}",
                'team2_wickets' => "required|integer|min:0|max:{$maxWickets}",
                
                'outcome' => 'required|in:team1,team2,tie',
            ]);
            
            if (in_array($this->stage, ['QF', 'SF', 'F']) && $this->outcome === 'tie') {
                $this->addError('outcome', 'Draws are not allowed in knockout stages.');
                return false;
            }
        }

        $this->validate($rules);
        
        if (in_array($this->stage, ['QF', 'SF', 'F'])) {
            $slotQuery = TournamentMatch::where('stage', $this->stage)
                ->where('bracket_position', $this->bracket_position);
                
            if ($this->editingMatchId) {
                $slotQuery->where('id', '!=', $this->editingMatchId);
            }
            
            if ($slotQuery->exists()) {
                $this->addError('bracket_position', 'This bracket slot is already occupied.');
                return false;
            }
        }
        
        $query = TournamentMatch::where('stage', $this->stage)
            ->where(function($q) {
                $q->where(function($q2) {
                    $q2->where('team1_id', $this->team1_id)->where('team2_id', $this->team2_id);
                })->orWhere(function($q2) {
                    $q2->where('team1_id', $this->team2_id)->where('team2_id', $this->team1_id);
                });
            });
            
        if ($this->editingMatchId) {
            $query->where('id', '!=', $this->editingMatchId);
        }

        if ($query->exists()) {
            $this->addError('team2_id', 'These teams have already been paired in this stage.');
            return false;
        }
        
        // 10.5 Knockout edit safety
        if ($this->editingMatchId && in_array($this->stage, ['QF', 'SF'])) {
            $oldMatch = TournamentMatch::find($this->editingMatchId);
            $newWinnerId = null;
            if ($this->status === 'finished') {
                $newWinnerId = $this->outcome === 'team1' ? $this->team1_id : ($this->outcome === 'team2' ? $this->team2_id : null);
            }
            
            if ($oldMatch->status === 'finished' && $oldMatch->winner_id && $oldMatch->winner_id !== $newWinnerId) {
                $nextStage = $this->stage === 'QF' ? 'SF' : 'F';
                $hasSubsequent = TournamentMatch::where('stage', $nextStage)
                    ->where(function($q) use ($oldMatch) {
                        $q->where('team1_id', $oldMatch->winner_id)->orWhere('team2_id', $oldMatch->winner_id);
                    })->exists();
                    
                if ($hasSubsequent && !$this->sfConflictWarning) {
                    $this->addError('conflict', "Warning: Changing or removing the winner will invalidate the scheduled {$nextStage} match for the old winner. Click Save again to proceed anyway.");
                    $this->sfConflictWarning = true;
                    return false;
                }
            }
        }

        return true;
    }

    public function openConfirm()
    {
        if ($this->validateMatch()) {
            if ($this->status === 'finished') {
                $this->showConfirm = true;
                $this->show = false;
            } else {
                $this->saveMatch();
            }
        }
    }

    public function cancelConfirm()
    {
        $this->showConfirm = false;
        $this->show = true;
    }

    public function saveMatch()
    {
        if (!$this->validateMatch()) {
            $this->showConfirm = false;
            $this->show = true;
            return;
        }

        $winnerId = null;
        $isDraw = false;
        if ($this->status === 'finished') {
            if ($this->outcome === 'team1') {
                $winnerId = $this->team1_id;
            } elseif ($this->outcome === 'team2') {
                $winnerId = $this->team2_id;
            } else {
                $isDraw = true;
            }
        }
        
        $user = request()->attributes->get('user') ?? auth()->user();

        $data = [
            'stage' => $this->stage,
            'status' => $this->status,
            'group_id' => $this->stage === 'G' ? $this->group_id : null,
            'bracket_position' => in_array($this->stage, ['QF', 'SF', 'F']) ? $this->bracket_position : null,
            'team1_id' => $this->team1_id,
            'team2_id' => $this->team2_id,
            'batting_first_id' => $this->batting_first_id ?: null,
            
            'team1_score' => $this->status === 'finished' ? $this->team1_score : null,
            'team1_overs' => $this->status === 'finished' ? $this->team1_overs : null,
            'team1_balls' => $this->status === 'finished' ? $this->team1_balls : null,
            'team1_wickets' => $this->status === 'finished' ? $this->team1_wickets : null,
            
            'team2_score' => $this->status === 'finished' ? $this->team2_score : null,
            'team2_overs' => $this->status === 'finished' ? $this->team2_overs : null,
            'team2_balls' => $this->status === 'finished' ? $this->team2_balls : null,
            'team2_wickets' => $this->status === 'finished' ? $this->team2_wickets : null,
            
            'is_draw' => $isDraw,
            'winner_id' => $winnerId,
        ];
        
        $needsRecalc = false;

        if ($this->editingMatchId) {
            $match = TournamentMatch::find($this->editingMatchId);
            
            // 10.2 Optimistic Locking Check
            if ($match->updated_at?->timestamp !== $this->original_updated_at) {
                $this->addError('conflict', 'This match was modified by another scorer. Please close and reopen the edit modal to see their changes.');
                $this->showConfirm = false;
                $this->show = true;
                return;
            }
            
            $oldStatus = $match->status;
            $match->update($data);
            
            if (($oldStatus === 'finished' || $this->status === 'finished') && $this->stage === 'G' && $this->group_id) {
                $needsRecalc = true;
            }
            $this->dispatch('match-updated');
        } else {
            $data['entered_by'] = $user ? $user->id : null;
            TournamentMatch::create($data);
            
            if ($this->status === 'finished' && $this->stage === 'G' && $this->group_id) {
                $needsRecalc = true;
            }
            $this->dispatch('match-created');
        }

        if ($needsRecalc) {
            $group = TournamentGroup::find($this->group_id);
            if ($group) {
                app(TournamentStandingsService::class)->recalculateForGroup($group);
            }
        }

        $this->showConfirm = false;
        $this->show = false;
        
        $this->resetForm();
    }
    
    public function resetForm()
    {
        $this->reset([
            'editingMatchId', 'team1_id', 'team2_id', 'batting_first_id', 'status',
            'team1_score', 'team1_overs', 'team1_balls', 'team1_wickets',
            'team2_score', 'team2_overs', 'team2_balls', 'team2_wickets',
            'outcome', 'bracket_position'
        ]);
        $this->status = 'upcoming';
    }
    
    public function getTeamName($id)
    {
        return Team::find($id)?->team_name ?? 'Unknown';
    }
}; ?>

<div>
    <x-mary-button label="Create Match" wire:click="$set('show', true)" class="btn-primary" icon="o-plus" />
    
    <x-mary-modal wire:model="show" title="{{ $editingMatchId ? 'Edit Match' : 'Create / Schedule Match' }}" class="backdrop-blur" box-class="w-11/12 max-w-4xl" @keydown.escape.window="$wire.resetForm()">
        <form wire:submit.prevent="openConfirm" class="flex flex-col gap-6">
            @error('conflict')
                <div class="alert alert-warning shadow-sm">
                    <x-mary-icon name="o-exclamation-triangle" class="w-6 h-6 shrink-0" />
                    <div>
                        <h3 class="font-bold">Attention Needed</h3>
                        <div class="text-sm">{{ $message }}</div>
                    </div>
                </div>
            @enderror
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-mary-select wire:model.live="stage" label="Stage" :options="[
                    ['id' => 'G', 'name' => 'Group Stage'],
                    ['id' => 'QF', 'name' => 'Quarter-Finals'],
                    ['id' => 'SF', 'name' => 'Semi-Finals'],
                    ['id' => 'F', 'name' => 'The Final']
                ]" required />

                @if($stage === 'G')
                    <x-mary-select wire:model.live="group_id" label="Group" :options="$groups" required placeholder="Select a Group" />
                @elseif($stage === 'QF')
                    <x-mary-select wire:model="bracket_position" label="Quarter-Final Slot" :options="[
                        ['id' => 1, 'name' => 'Slot 1'],
                        ['id' => 2, 'name' => 'Slot 2'],
                        ['id' => 3, 'name' => 'Slot 3'],
                        ['id' => 4, 'name' => 'Slot 4']
                    ]" required placeholder="Select Bracket Slot" />
                @elseif($stage === 'SF')
                    <x-mary-select wire:model="bracket_position" label="Semi-Final Slot" :options="[
                        ['id' => 1, 'name' => 'SF 1 (Winner QF 1 vs 2)'],
                        ['id' => 2, 'name' => 'SF 2 (Winner QF 3 vs 4)']
                    ]" required placeholder="Select Bracket Slot" />
                @elseif($stage === 'F')
                    <input type="hidden" wire:model="bracket_position" value="1" />
                    <div class="form-control w-full">
                        <label class="label"><span class="label-text font-semibold">Bracket Slot</span></label>
                        <div class="px-4 py-3 bg-base-200 rounded-lg text-sm">The Final (Slot 1)</div>
                    </div>
                @endif
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-mary-select wire:model="team1_id" label="Team 1" :options="$availableTeams" option-label="team_name" option-value="id" required placeholder="Select Team 1" />
                <x-mary-select wire:model="team2_id" label="Team 2" :options="$availableTeams" option-label="team_name" option-value="id" required placeholder="Select Team 2" />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-mary-select wire:model="batting_first_id" label="Batting First (Optional)" :options="[
                    ['id' => $team1_id, 'name' => $team1_id ? $this->getTeamName($team1_id) : 'Team 1'],
                    ['id' => $team2_id, 'name' => $team2_id ? $this->getTeamName($team2_id) : 'Team 2']
                ]" placeholder="Select team that batted first" />

                <x-mary-select wire:model.live="status" label="Match Status" :options="[
                    ['id' => 'upcoming', 'name' => 'Upcoming'],
                    ['id' => 'live', 'name' => 'Live'],
                    ['id' => 'finished', 'name' => 'Finished']
                ]" required />
            </div>

            @if($status === 'finished')
                <div class="divider">Match Results</div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Team 1 Score -->
                    <div class="card bg-base-200 p-4">
                        <h3 class="font-bold mb-4">{{ $team1_id ? $this->getTeamName($team1_id) : 'Team 1' }} Score</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <x-mary-input wire:model="team1_score" label="Runs" type="number" min="0" required />
                            <x-mary-input wire:model="team1_wickets" label="Wickets" type="number" min="0" max="{{ $this->maxWickets }}" required />
                            <x-mary-input wire:model="team1_overs" label="Overs" type="number" min="0" max="{{ $this->maxOvers }}" required />
                            <x-mary-input wire:model="team1_balls" label="Balls" type="number" min="0" max="{{ $this->ballsPerOver - 1 }}" required />
                        </div>
                    </div>

                    <!-- Team 2 Score -->
                    <div class="card bg-base-200 p-4">
                        <h3 class="font-bold mb-4">{{ $team2_id ? $this->getTeamName($team2_id) : 'Team 2' }} Score</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <x-mary-input wire:model="team2_score" label="Runs" type="number" min="0" required />
                            <x-mary-input wire:model="team2_wickets" label="Wickets" type="number" min="0" max="{{ $this->maxWickets }}" required />
                            <x-mary-input wire:model="team2_overs" label="Overs" type="number" min="0" max="{{ $this->maxOvers }}" required />
                            <x-mary-input wire:model="team2_balls" label="Balls" type="number" min="0" max="{{ $this->ballsPerOver - 1 }}" required />
                        </div>
                    </div>
                </div>

                @php
                    $outcomeOptions = [
                        ['id' => 'team1', 'name' => $team1_id ? $this->getTeamName($team1_id).' Won' : 'Team 1 Won'],
                        ['id' => 'team2', 'name' => $team2_id ? $this->getTeamName($team2_id).' Won' : 'Team 2 Won'],
                    ];
                    if ($stage === 'G') {
                        $outcomeOptions[] = ['id' => 'tie', 'name' => 'Tie / Draw'];
                    }
                @endphp
                <div class="mt-4">
                    <x-mary-select wire:model="outcome" label="Match Outcome" :options="$outcomeOptions" required placeholder="Select winner" />
                </div>
            @endif

            <x-slot:actions>
                <x-mary-button label="Cancel" wire:click="$set('show', false); resetForm()" class="btn-ghost" />
                <x-mary-button type="submit" label="{{ $status === 'finished' ? 'Review & Save' : 'Save Match' }}" class="btn-primary" spinner="openConfirm" />
            </x-slot:actions>
        </form>
    </x-mary-modal>

    <!-- Confirmation Modal -->
    <x-mary-modal wire:model="showConfirm" title="Confirm Match Results" class="backdrop-blur">
        <div class="text-center mb-6">
            <h2 class="text-xl font-bold text-success mb-2">
                @if($outcome === 'team1')
                    {{ $this->getTeamName($team1_id) }} Won
                @elseif($outcome === 'team2')
                    {{ $this->getTeamName($team2_id) }} Won
                @else
                    Match Drawn
                @endif
            </h2>
            <p class="text-sm opacity-70">Please review the scores carefully before saving. Standings will be automatically updated.</p>
        </div>

        <div class="grid grid-cols-2 gap-4 text-center">
            <div class="p-4 bg-base-200 rounded-lg">
                <div class="font-bold mb-2">{{ $this->getTeamName($team1_id) }}</div>
                <div class="text-2xl">{{ $team1_score }} / {{ $team1_wickets }}</div>
                <div class="text-sm opacity-70">in {{ $team1_overs }}.{{ $team1_balls }} overs</div>
            </div>
            <div class="p-4 bg-base-200 rounded-lg">
                <div class="font-bold mb-2">{{ $this->getTeamName($team2_id) }}</div>
                <div class="text-2xl">{{ $team2_score }} / {{ $team2_wickets }}</div>
                <div class="text-sm opacity-70">in {{ $team2_overs }}.{{ $team2_balls }} overs</div>
            </div>
        </div>

        <x-slot:actions>
            <x-mary-button label="Back to Edit" wire:click="cancelConfirm" class="btn-ghost" />
            <x-mary-button label="Confirm & Save" wire:click="saveMatch" class="btn-primary" icon="o-check" spinner="saveMatch" />
        </x-slot:actions>
    </x-mary-modal>
</div>
