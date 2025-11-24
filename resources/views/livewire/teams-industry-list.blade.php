<?php

use App\Models\Team;
use Livewire\Volt\Component;

new class extends Component {
    public string $search = '';

    public function with(): array
    {
        $query = Team::with(['company', 'members'])
            ->where('approved', true)
            ->whereHas('company');

        return [
            'teams' => $query->latest()->get(),
        ];
    }
}; ?>

<div class="min-h-screen py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto">
        <div class="text-center mb-12" data-aos="fade-up">
            <h1 class="text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-bold font-heading uppercase mb-4">
                <span class="text-primary">Industry </span>
                <span class="text-base-content">Teams</span>
            </h1>
            <p class="text-base-content/80 text-base sm:text-lg md:text-xl max-w-3xl mx-auto">
                Registered industry teams for Hit the Grounds 2025
            </p>
        </div>

        <!-- Teams Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6" data-aos="fade-up" data-aos-delay="200">
            @forelse($teams as $team)
                <a href="{{-- route('company.public-profile', $team->company) --}}"
                   wire:navigate
                   class="card bg-base-200 shadow-sm hover:shadow-md transition-shadow duration-300 cursor-default group">
                    <div class="card-body p-6">
                        <!-- Company Logo -->
                        <div class="flex justify-center mb-4">
                            @if($team->company->logo)
                                <div class="avatar">
                                    <div class="w-32 h-32 rounded-lg transition-all">
                                        <img src="{{ Storage::url($team->company->logo) }}"
                                             alt="{{ $team->company->name }}"
                                             class="w-full h-full object-contain" />
                                    </div>
                                </div>
                            @else
                                <div class="avatar placeholder">
                                    <div class="w-32 h-32 rounded-lg transition-all">
                                    <x-mary-icon name="o-building-office-2" class="w-full h-full text-primary/50" />
                                    </div>
                                </div>
                            @endif
                        </div>

                        <!-- Team Name -->
                        <h3 class="card-title text-xl font-bold text-center justify-center mb-2 group-hover:text-primary transition-colors">
                            {{ $team->team_name }}
                        </h3>

                        <!-- Company Name -->
                        <p class="text-center text-base-content/70 text-lg mb-4">
                            {{ $team->company->name }}
                        </p>
                    </div>
                </a>
            @empty
                <div class="col-span-full text-center py-12">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-24 w-24 mx-auto text-base-content/20 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    <h3 class="text-xl font-semibold text-base-content/60 mb-2">No teams found</h3>
                    <p class="text-base-content/40">
                        @if($search)
                            No teams match your search criteria. Try a different search term.
                        @else
                            No industry teams have been registered yet.
                        @endif
                    </p>
                </div>
            @endforelse
        </div>

    </div>
</div>
