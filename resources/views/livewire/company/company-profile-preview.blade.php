<?php

use App\Models\Company;
use Livewire\Volt\Component;
use Livewire\Attributes\On;

new class extends Component {
    public Company $company;

    public function mount(Company $company): void
    {
        $this->company = $company;
    }

    #[On('company-updated')]
    public function refreshCompany(): void
    {
        $this->company->refresh();
    }
}; ?>

<div>
    <div class="card bg-base-100 border-base-300 border-2">
        <div class="card-body">
            <h2 class="card-title mb-4">Profile Preview</h2>

            <!-- Company Header -->
            <div class="flex items-start gap-6 mb-6">
                <div class="avatar">
                    <div class="w-24 h-24 rounded-lg">
                        @if($company->logo)
                            <img src="{{ Storage::url($company->logo) }}" alt="{{ $company->name }}" class="object-cover" />
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
                    <h3 class="text-2xl font-bold">{{ $company->name }}</h3>
                    @if($company->phone)
                        <div class="flex items-center gap-2 mt-2 text-base-content/70">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />
                            </svg>
                            <span>{{ $company->phone }}</span>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Divider -->
            <div class="divider"></div>

            <!-- Description -->
            @if($company->description)
                <div class="prose max-w-none">
                    <h4 class="text-lg font-semibold mb-3">About Us</h4>
                    <div class="markdown-content" id="mainarticle">
                        {!! \Illuminate\Support\Str::markdown($company->description, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                    </div>
                </div>
            @else
                <div class="text-center py-8 text-base-content/50">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 mx-auto mb-3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                    </svg>
                    <p>No description added yet</p>
                    <p class="text-sm mt-1">Click "Edit Description" to add information about your company</p>
                </div>
            @endif

            <!-- Teams Info -->
            <div class="divider"></div>
            <div class="grid grid-cols-2 gap-4">
                <div class="stat bg-base-200 rounded-lg">
                    <div class="stat-title">Registered Teams</div>
                    <div class="stat-value text-primary-ink">{{ $company->teams->count() }}</div>
                    <div class="stat-desc">Out of 2 maximum</div>
                </div>
                <div class="stat bg-base-200 rounded-lg">
                    <div class="stat-title">Total Members</div>
                    <div class="stat-value text-secondary">{{ $company->teams->sum(fn($team) => $team->members->count()) }}</div>
                    <div class="stat-desc">Across all teams</div>
                </div>
            </div>
        </div>
    </div>
</div>
