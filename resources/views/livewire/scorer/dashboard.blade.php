<?php
use Livewire\Volt\Component;

new class extends Component {
    public string $selectedStage = 'G';
}; ?>

<div>
    <div class="container mx-auto px-4 py-8">
        <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-end gap-4">
            <div>
                <h1 class="text-3xl font-bold text-base-content">Scorer Dashboard</h1>
                <p class="mt-2 text-base-content/70">Manage tournament matches and standings.</p>
            </div>
            <div>
                <livewire:scorer.match-create-modal />
            </div>
        </div>
        
        <x-mary-tabs wire:model="selectedStage" class="mt-6">
            <x-mary-tab name="G" label="Group Stage" icon="o-table-cells">
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mt-4">
                    <div class="xl:col-span-2">
                        <livewire:scorer.matches-list stage="G" />
                    </div>
                    <div>
                        <livewire:scorer.group-standings />
                    </div>
                </div>
            </x-mary-tab>
            <x-mary-tab name="QF" label="Quarter-Finals" icon="o-trophy">
                <div class="mt-4">
                    <livewire:scorer.bracket-slot-manager />
                    <livewire:scorer.matches-list stage="QF" />
                </div>
            </x-mary-tab>
            <x-mary-tab name="SF" label="Semi-Finals" icon="o-star">
                <div class="mt-4">
                    <livewire:scorer.matches-list stage="SF" />
                </div>
            </x-mary-tab>
            <x-mary-tab name="F" label="The Final" icon="o-sparkles">
                <div class="mt-4">
                    <livewire:scorer.matches-list stage="F" />
                </div>
            </x-mary-tab>
        </x-mary-tabs>
    </div>
</div>
