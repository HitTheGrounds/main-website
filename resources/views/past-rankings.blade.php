@php
    // Newest first. 'dark' marks a logo that only exists in a white version.
    $logos = [
        'GTN' => ['/images/rankings/gtn.avif'],
        'Synopsys' => ['/images/rankings/synopsys.avif'],
        'Codegen' => ['/partner/codegen.avif'],
        'Virtusa' => ['/images/rankings/virtusa.avif'],
        'SimCentric Technologies' => ['/images/rankings/simcentric.avif'],
        'DirectFN' => ['/images/rankings/directfn.avif', true],
        '99X' => ['/images/rankings/99x.avif'],
        'WSO2' => ['/images/rankings/wso2.avif'],
    ];

    $rankings = [
        2025 => ['GTN', 'Synopsys'],
        2024 => ['Synopsys', 'Codegen'],
        2019 => ['Virtusa', 'SimCentric Technologies'],
        2018 => ['DirectFN', '99X'],
        2015 => ['WSO2', 'Virtusa'],
    ];
@endphp

<x-layouts.public>
    <section class="bg-base-100">
        <x-PageContainer>
            <h1 class="mb-12 text-center font-heading text-[clamp(2.75rem,8vw,5.5rem)] md:mb-16" data-aos="fade-up">
                <span class="text-base-content">Past </span><span class="text-primary">Rankings</span>
            </h1>

            <div class="mx-auto flex max-w-6xl flex-col gap-12 md:gap-16">
                @foreach ($rankings as $year => [$champion, $runnerUp])
                    <article data-aos="fade-up">
                        <h2 class="mb-6 text-center font-heading text-6xl leading-none text-base-content sm:text-7xl md:text-8xl">{{ $year }}</h2>

                        <div class="grid gap-4 sm:grid-cols-2 sm:gap-6">
                            @foreach ([['Champions', $champion, true], ['1st Runners-up', $runnerUp, false]] as [$label, $team, $isChampion])
                                @php
                                    [$src, $dark] = $logos[$team] + [1 => false];
                                @endphp
                                <div class="flex flex-col items-center rounded-[1.75rem] p-5 text-center sm:p-6 {{ $isChampion ? 'bg-[#17120E] text-base-100' : 'border border-base-300 bg-base-200 text-base-content' }}">
                                    <span class="inline-block rounded-full px-3 py-1.5 text-xs font-bold uppercase tracking-[0.14em] {{ $isChampion ? 'bg-primary text-primary-content' : 'bg-secondary text-secondary-content' }}">{{ $label }}</span>

                                    <div class="mt-5 grid h-28 w-full place-items-center rounded-2xl px-6 sm:h-32 {{ $dark ? 'bg-[#1F2A44]' : 'bg-[#FBFAF6]' }}">
                                        <img src="{{ $src }}" alt="{{ $team }} logo" class="max-h-16 w-auto max-w-full object-contain sm:max-h-20" loading="lazy">
                                    </div>

                                    <h3 class="mt-5 font-heading text-2xl sm:text-3xl">{{ $team }}</h3>
                                </div>
                            @endforeach
                        </div>
                    </article>
                @endforeach
            </div>
        </x-PageContainer>
    </section>
</x-layouts.public>
