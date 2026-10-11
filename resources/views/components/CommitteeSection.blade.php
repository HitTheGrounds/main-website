@php
    $committeeGroups = [
        [
            'title' => 'Executive Committee',
            'members' => [
                ['name' => 'Akindu Himan', 'role' => 'Chairperson', 'photo' => '/people/akindu.avif'],
                ['name' => 'Devindi Wickramarathna', 'role' => 'Vice Chairperson', 'photo' => '/people/devindi.avif'],
                ['name' => 'Sadil Ephraims', 'role' => 'Vice Chairperson', 'photo' => '/people/sadil.avif'],
            ],
        ],
        [
            'title' => 'Partnership Development Committee',
            'members' => [
                ['name' => 'Samadhi Talagala', 'role' => 'Partnership Development Committee Lead', 'photo' => '/people/samadhi.avif'],
                ['name' => 'Eshin Fernando', 'role' => 'Partnership Development Committee Lead', 'photo' => '/people/eshin.avif'],
            ],
        ],
        [
            'title' => 'Finance Committee',
            'members' => [
                ['name' => 'Umen Samaranayake', 'role' => 'Finance Committee Lead', 'photo' => '/people/umen.avif'],
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
                        <article class="card border border-base-300 bg-base-200 shadow-sm" data-aos="fade-up" data-aos-duration="1000">
                            <div class="card-body items-center text-center">
                                <img src="{{ $member['photo'] }}" alt="{{ $member['name'] }}" width="112" height="112" loading="lazy" class="mb-3 size-28 rounded-full object-cover ring ring-primary/30 ring-offset-4 ring-offset-base-200">
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
