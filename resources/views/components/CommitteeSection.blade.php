@php
    $committeeGroups = [
        [
            'title' => 'Executive Committee',
            'members' => [
                ['name' => 'Akindu Himan', 'role' => 'Chairperson', 'initials' => 'AH'],
                ['name' => 'Devindi Wickramarathna', 'role' => 'Vice Chairperson', 'initials' => 'DW'],
                ['name' => 'Sadil Ephraims', 'role' => 'Vice Chairperson', 'initials' => 'SE'],
            ],
        ],
        [
            'title' => 'Partnership Development Committee',
            'members' => [
                ['name' => 'Samadhi Talagala', 'role' => 'Partnership Development Committee Lead', 'initials' => 'ST'],
                ['name' => 'Eshin Fernando', 'role' => 'Partnership Development Committee Lead', 'initials' => 'EF'],
            ],
        ],
        [
            'title' => 'Finance Committee',
            'members' => [
                ['name' => 'Umen Samaranayake', 'role' => 'Finance Committee Lead', 'initials' => 'US'],
            ],
        ],
    ];
@endphp

<section id="committee" class="scroll-mt-24 bg-base-100">
    <x-PageContainer>
        <div class="mb-12 text-center" data-aos="fade-up">
            <p class="mb-3 text-sm font-bold uppercase tracking-[0.25em] text-primary-ink">The team behind the tournament</p>
            <h2 class="font-title text-3xl font-bold text-base-content sm:text-4xl md:text-5xl">Committee Leads</h2>
        </div>

        @foreach ($committeeGroups as $group)
            <div class="mb-16 last:mb-0">
                <h3 class="mb-8 text-center font-title text-2xl font-bold text-base-content sm:text-3xl" data-aos="fade-up">
                    {{ $group['title'] }}
                </h3>

                <div class="mx-auto grid grid-cols-1 gap-6 {{ count($group['members']) === 1 ? 'max-w-sm' : (count($group['members']) === 2 ? 'max-w-2xl sm:grid-cols-2' : 'max-w-5xl sm:grid-cols-2 lg:grid-cols-3') }}">
                    @foreach ($group['members'] as $member)
                        <article class="card border border-base-300 bg-base-200 shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-md" data-aos="fade-up" data-aos-duration="1000">
                            <div class="card-body items-center text-center">
                                <div class="mb-3 grid size-28 place-items-center rounded-full bg-primary text-3xl font-bold text-primary-content ring ring-primary/30 ring-offset-4 ring-offset-base-200" aria-hidden="true">
                                    {{ $member['initials'] }}
                                </div>
                                <h4 class="card-title justify-center text-xl text-base-content">{{ $member['name'] }}</h4>
                                <p class="text-sm leading-relaxed text-base-content/70">{{ $member['role'] }}</p>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        @endforeach
    </x-PageContainer>
</section>
