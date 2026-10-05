<?php

use App\Models\TournamentMatch;
use Livewire\Volt\Component;

new class extends Component {
    public $qfMatches = [];
    public $sfMatches = [];
    public $lockedSlots = [];
    
    protected $listeners = ['match-created' => 'loadMatches', 'match-updated' => 'loadMatches'];

    public function mount()
    {
        $this->loadMatches();
    }
    
    public function loadMatches()
    {
        $this->qfMatches = TournamentMatch::with(['team1', 'team2'])
            ->where('stage', 'QF')
            ->get()
            ->keyBy('bracket_position')
            ->toArray();
            
        $this->sfMatches = TournamentMatch::where('stage', 'SF')
            ->get()
            ->keyBy('bracket_position')
            ->toArray();
            
        $this->lockedSlots = [];
        if (isset($this->sfMatches[1])) {
            $this->lockedSlots[] = 1;
            $this->lockedSlots[] = 2;
        }
        if (isset($this->sfMatches[2])) {
            $this->lockedSlots[] = 3;
            $this->lockedSlots[] = 4;
        }
    }
    
    public function swapSlots($slotA, $slotB)
    {
        if (in_array($slotA, $this->lockedSlots) || in_array($slotB, $this->lockedSlots)) {
            $this->addError('swap', 'Cannot swap locked slots.');
            return;
        }
        
        $matchA = TournamentMatch::where('stage', 'QF')->where('bracket_position', $slotA)->first();
        $matchB = TournamentMatch::where('stage', 'QF')->where('bracket_position', $slotB)->first();
        
        if ($matchA) {
            $matchA->update(['bracket_position' => 999]); // Temp
        }
        if ($matchB) {
            $matchB->update(['bracket_position' => $slotA]);
        }
        if ($matchA) {
            $matchA->update(['bracket_position' => $slotB]);
        }
        
        $this->loadMatches();
        $this->dispatch('match-updated');
    }
    
    public function getTeamName($matchArray, $teamKey)
    {
        if (!isset($matchArray[$teamKey])) return 'TBD';
        return $matchArray[$teamKey]['team_name'] ?? 'TBD';
    }
}; ?>

<div>
    <div class="card bg-base-100 shadow-sm border border-base-200 mb-8">
        <div class="card-body p-4 md:p-6">
            <h2 class="card-title text-xl mb-4">Quarter-Final Bracket Slots</h2>
            
            @error('swap')
                <div class="alert alert-error mb-4">
                    <x-mary-icon name="o-exclamation-triangle" class="w-5 h-5" />
                    <span>{{ $message }}</span>
                </div>
            @enderror

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- SF1 Side -->
                <div class="bg-base-200/50 p-4 rounded-xl border border-base-300">
                    <div class="text-center font-bold mb-4 flex items-center justify-center gap-2">
                        <span>SF 1 Pathway</span>
                        @if(isset($sfMatches[1]))
                            <x-mary-icon name="o-lock-closed" class="w-4 h-4 text-error" tooltip="Locked (SF1 Scheduled)" />
                        @else
                            <x-mary-icon name="o-lock-open" class="w-4 h-4 text-success" tooltip="Open" />
                        @endif
                    </div>
                    
                    <div class="flex flex-col gap-4 relative">
                        <!-- Slot 1 -->
                        <div class="card bg-base-100 shadow-sm border {{ isset($sfMatches[1]) ? 'border-error/30' : 'border-primary/30' }}">
                            <div class="card-body p-3">
                                <div class="text-xs font-bold text-base-content/50 mb-1">Slot 1</div>
                                @if(isset($qfMatches[1]))
                                    <div class="font-semibold truncate">{{ $this->getTeamName($qfMatches[1], 'team1') }}</div>
                                    <div class="text-xs opacity-50">vs</div>
                                    <div class="font-semibold truncate">{{ $this->getTeamName($qfMatches[1], 'team2') }}</div>
                                @else
                                    <div class="text-sm opacity-50 italic">Empty Slot</div>
                                @endif
                            </div>
                        </div>
                        
                        <!-- Swap Button for Slot 1 and 2 -->
                        @if(!isset($sfMatches[1]))
                            <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 z-10">
                                <x-mary-button wire:click="swapSlots(1, 2)" class="btn-circle btn-sm btn-primary shadow-lg" tooltip="Swap Slots 1 & 2" icon="o-arrows-up-down" spinner />
                            </div>
                        @endif
                        
                        <!-- Slot 2 -->
                        <div class="card bg-base-100 shadow-sm border {{ isset($sfMatches[1]) ? 'border-error/30' : 'border-primary/30' }}">
                            <div class="card-body p-3">
                                <div class="text-xs font-bold text-base-content/50 mb-1">Slot 2</div>
                                @if(isset($qfMatches[2]))
                                    <div class="font-semibold truncate">{{ $this->getTeamName($qfMatches[2], 'team1') }}</div>
                                    <div class="text-xs opacity-50">vs</div>
                                    <div class="font-semibold truncate">{{ $this->getTeamName($qfMatches[2], 'team2') }}</div>
                                @else
                                    <div class="text-sm opacity-50 italic">Empty Slot</div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- SF2 Side -->
                <div class="bg-base-200/50 p-4 rounded-xl border border-base-300">
                    <div class="text-center font-bold mb-4 flex items-center justify-center gap-2">
                        <span>SF 2 Pathway</span>
                        @if(isset($sfMatches[2]))
                            <x-mary-icon name="o-lock-closed" class="w-4 h-4 text-error" tooltip="Locked (SF2 Scheduled)" />
                        @else
                            <x-mary-icon name="o-lock-open" class="w-4 h-4 text-success" tooltip="Open" />
                        @endif
                    </div>
                    
                    <div class="flex flex-col gap-4 relative">
                        <!-- Slot 3 -->
                        <div class="card bg-base-100 shadow-sm border {{ isset($sfMatches[2]) ? 'border-error/30' : 'border-primary/30' }}">
                            <div class="card-body p-3">
                                <div class="text-xs font-bold text-base-content/50 mb-1">Slot 3</div>
                                @if(isset($qfMatches[3]))
                                    <div class="font-semibold truncate">{{ $this->getTeamName($qfMatches[3], 'team1') }}</div>
                                    <div class="text-xs opacity-50">vs</div>
                                    <div class="font-semibold truncate">{{ $this->getTeamName($qfMatches[3], 'team2') }}</div>
                                @else
                                    <div class="text-sm opacity-50 italic">Empty Slot</div>
                                @endif
                            </div>
                        </div>
                        
                        <!-- Swap Button for Slot 3 and 4 -->
                        @if(!isset($sfMatches[2]))
                            <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 z-10">
                                <x-mary-button wire:click="swapSlots(3, 4)" class="btn-circle btn-sm btn-primary shadow-lg" tooltip="Swap Slots 3 & 4" icon="o-arrows-up-down" spinner />
                            </div>
                        @endif
                        
                        <!-- Slot 4 -->
                        <div class="card bg-base-100 shadow-sm border {{ isset($sfMatches[2]) ? 'border-error/30' : 'border-primary/30' }}">
                            <div class="card-body p-3">
                                <div class="text-xs font-bold text-base-content/50 mb-1">Slot 4</div>
                                @if(isset($qfMatches[4]))
                                    <div class="font-semibold truncate">{{ $this->getTeamName($qfMatches[4], 'team1') }}</div>
                                    <div class="text-xs opacity-50">vs</div>
                                    <div class="font-semibold truncate">{{ $this->getTeamName($qfMatches[4], 'team2') }}</div>
                                @else
                                    <div class="text-sm opacity-50 italic">Empty Slot</div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>
