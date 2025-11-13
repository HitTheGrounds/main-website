<?php

use App\Models\Company;
use Livewire\Volt\Component;

new class extends Component {
    public Company $company;

    public function mount(Company $company): void
    {
        $this->company = $company->load(['teams.members']);
    }

    public function with(): array
    {
        return [
            'totalPlayers' => $this->company->teams->sum(function($team) {
                return $team->members->count();
            }),
        ];
    }
}; ?>

<div class="min-h-screen py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-6xl mx-auto">
        <!-- Company Header Section -->
        <div class="card bg-base-100 shadow-sm border-base-300 border mb-8" data-aos="fade-up">
            <div class="card-body">
                <!-- Company Header -->
                <div class="flex items-center gap-6 mb-6">
                    <div class="avatar">
                        <div class="w-24 h-24 rounded-lg">
                            @if($company->logo)
                                <img src="{{ Storage::url($company->logo) }}" alt="{{ $company->name }}" class="w-full h-full object-contain" />
                            @else
                                <div class="w-full h-full bg-base-300 flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 text-base-content/30">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z" />
                                    </svg>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="flex-1">
                        <h1 class="text-3xl font-bold">{{ $company->name }}</h1>
                    </div>
                </div>

                <!-- Description -->
                @if($company->description)
                    <!-- Divider -->
                    <div class="divider"></div>

                    <div class="prose max-w-none mb-6">
                        <div id="mainarticle" class="markdown-content text-base-content/70">
                            {!! \Illuminate\Support\Str::markdown($company->description, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Teams Section -->
        <div data-aos="fade-up" data-aos-delay="100">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-3xl font-bold">
                    <span class="text-primary">Registered</span>
                    <span class="text-base-content">Teams</span>
                </h2>
            </div>

            @if($company->teams->count() > 0)
                <div class="space-y-6">
                    @foreach($company->teams as $team)
                        <div class="card bg-base-200 shadow-sm">
                            <div class="card-body">
                                <h3 class="card-title text-2xl mb-4">
                                    {{ $team->team_name }}
                                </h3>

                                <!-- Team Members Cards -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                                    @foreach($team->members->sortByDesc('is_captain') as $member)
                                        <div class="card bg-base-100 shadow-sm hover:shadow-md transition-shadow">
                                            <div class="card-body p-4">
                                                <h4 class="font-semibold text-base mb-2">{{ $member->name }}</h4>
                                                <div class="flex flex-wrap gap-2">
                                                    @if($member->is_captain)
                                                        <span class="badge badge-primary badge-sm">Captain</span>
                                                    @endif
                                                    <span class="badge badge-outline badge-sm {{ $member->gender === 'male' ? 'badge-info' : 'badge-secondary' }}">
                                                        {{ ucfirst($member->gender) }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="card bg-base-200 shadow-sm">
                    <div class="card-body text-center py-12">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-24 w-24 mx-auto text-base-content/20 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <h3 class="text-xl font-semibold text-base-content/60 mb-2">No Teams Yet</h3>
                        <p class="text-base-content/40">This company hasn't registered any teams yet.</p>
                    </div>
                </div>
            @endif
        </div>

        <!-- Back Button -->
        <div class="mt-8 text-center">
            <a href="{{ route('teams.industry') }}" wire:navigate class="btn btn-outline">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Back to Teams
            </a>
        </div>
    </div>
</div>
