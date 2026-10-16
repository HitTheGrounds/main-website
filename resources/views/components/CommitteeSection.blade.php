@php
    $committeeGroups = [
        [
            'title' => 'Executive Committee',
            'members' => [
                ['name' => 'Akindu Himan', 'role' => 'Chairperson', 'photo' => '/people/akindu.avif', 'email' => 'akindu.22@cse.mrt.ac.lk', 'phone' => '0705226048'],
                ['name' => 'Devindi Wickramarathna', 'role' => 'Vice Chairperson', 'photo' => '/people/devindi.avif', 'email' => 'devindi.24@cse.mrt.ac.lk', 'phone' => '+94 70 166 3373'],
                ['name' => 'Sadil Ephraims', 'role' => 'Vice Chairperson', 'photo' => '/people/sadil.avif', 'email' => 'sadil.24@cse.mrt.ac.lk', 'phone' => '0707170688'],
            ],
        ],
    ];

    $leadGroups = [
        [
            'title' => 'Partnership Committee',
            'members' => [
                ['name' => 'Samadhi Talagala', 'role' => 'Partnership Development Committee Lead', 'photo' => '/people/samadhi.avif', 'email' => 'samadhi.22@cse.mrt.ac.lk', 'phone' => '+94 71 117 8992'],
                ['name' => 'Eshin Fernando', 'role' => 'Partnership Development Committee Lead', 'photo' => '/people/eshin.avif', 'email' => 'eshin.22@cse.mrt.ac.lk', 'phone' => '+94 76 166 6171'],
            ],
        ],
        [
            'title' => 'Finance Committee',
            'members' => [
                ['name' => 'Umen Samaranayake', 'role' => 'Finance Committee Lead', 'photo' => '/people/umen.avif', 'email' => 'umen.22@cse.mrt.ac.lk', 'phone' => '+94 70 147 9694'],
            ],
        ],
    ];
@endphp

<section id="committee" class="bg-base-100">
    <x-PageContainer>
        <div class="mb-12 text-center" data-aos="fade-up">
            <p class="mb-3 text-sm font-bold uppercase tracking-[0.25em] text-primary-ink">The team behind the tournament</p>
            <h2 class="font-title text-3xl font-bold text-base-content sm:text-4xl md:text-5xl">Committee Leads</h2>
        </div>

        @foreach ($committeeGroups as $group)
            <div class="mb-10 sm:mb-16">
                <h3 class="mb-5 text-center font-title text-lg font-bold leading-tight text-base-content sm:mb-8 sm:text-3xl" data-aos="fade-up">
                    {{ $group['title'] }}
                </h3>

                <div class="mx-auto grid max-w-5xl grid-cols-3 gap-2 sm:gap-6">
                    @foreach ($group['members'] as $member)
                        <x-committee-card :member="$member" />
                    @endforeach
                </div>
            </div>
        @endforeach

        {{-- Two committees in one row: two cards, a divider, then one --}}
        <div class="mx-auto grid max-w-5xl grid-cols-[2fr_1fr]">
            @foreach ($leadGroups as $group)
                <div class="flex flex-col {{ $loop->last ? 'border-l border-base-300 pl-1.5 sm:pl-6' : 'pr-1.5 sm:pr-6' }}">
                    <h3 class="mb-5 flex min-h-[3.25rem] items-center justify-center text-center sm:block sm:min-h-0 font-title text-lg font-bold leading-tight text-base-content sm:mb-8 sm:text-3xl" data-aos="fade-up">
                        {{ $group['title'] }}
                    </h3>

                    <div class="grid flex-1 grid-rows-[1fr] gap-2 sm:gap-6 {{ count($group['members']) > 1 ? 'grid-cols-2' : 'grid-cols-1' }}">
                        @foreach ($group['members'] as $member)
                            <x-committee-card :member="$member" />
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </x-PageContainer>
</section>
