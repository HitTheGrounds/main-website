<?php

use App\Services\Tournament\PlayoffFeature;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new class extends Component {
    public bool $enabled = false;
    #[Locked]
    public int $version = 0;
    public string $notice = '';

    public function mount(): void
    {
        $this->reloadSettings();
    }

    public function reloadSettings(): void
    {
        $settings = app(PlayoffFeature::class)->settings((int) config('tournament.year'));
        $this->enabled = $settings['enabled'];
        $this->version = $settings['version'];
        $this->notice = '';
        $this->resetErrorBag();
    }

    public function save(): void
    {
        $settings = app(PlayoffFeature::class)->save((int) config('tournament.year'), $this->enabled, $this->version);
        $this->version = $settings['version'];
        $this->notice = 'Playoffs '.($settings['enabled'] ? 'enabled' : 'disabled').'.';
        $this->resetErrorBag();
    }
}; ?>

<section class="card bg-base-100 border border-base-300 mt-6" aria-labelledby="playoffs-heading">
    <form wire:submit="save" class="card-body">
        <h2 id="playoffs-heading" class="card-title">Tournament {{ config('tournament.year') }}: Playoffs</h2>
        <p>Enable an optional round between the group stage and quarter-finals when the schedule allows. All saved group qualifiers can play, including repeat appearances. While enabled, only published Playoff winners qualify for quarter-finals.</p>
        <p>Playoffs appear in Matches and player stats. Group tables and the public bracket stay unchanged. Disabling hides Playoffs and excludes their stats while retaining saved match records.</p>
        <label class="flex items-center gap-3 my-3"><input type="checkbox" wire:model="enabled" class="toggle toggle-primary" /> Enable Playoffs</label>
        @if($notice)<p role="status" class="text-success">{{ $notice }}</p>@endif
        @error('playoffs')<p role="alert" class="text-error">{{ $message }}</p>@enderror
        <div class="card-actions"><button type="submit" wire:loading.attr="disabled" class="btn btn-primary">Save tournament settings</button><button type="button" wire:click="reloadSettings" class="btn btn-ghost">Reload</button></div>
    </form>
</section>
