@php
    $awards = [
        ['name' => 'Champion', 'description' => 'Awarded to the tournament-winning team.'],
        ['name' => '1st Runners-up', 'description' => 'Awarded to the tournament’s second-place team.'],
        ['name' => 'Most Valuable Player — Male', 'description' => 'Best overall male player of the season.'],
        ['name' => 'Most Valuable Player — Female', 'description' => 'Best overall female player of the season.'],
        ['name' => 'Orange Cap', 'description' => 'Awarded to the tournament’s leading run-scorer.'],
        ['name' => 'Purple Cap', 'description' => 'Awarded to the tournament’s leading wicket-taker.'],
        ['name' => 'Super Striker of the Tournament', 'description' => 'Awarded to the player with the most sixes hit.'],
        ['name' => '4s of the Tournament', 'description' => 'Awarded to the player with the most fours hit.'],
        ['name' => 'Green Dot Ball Award', 'description' => 'Awarded to the bowler with the most dot balls delivered.'],
        ['name' => 'Player of the Match — Final', 'description' => 'Awarded to the standout performer in the final.'],
    ];
@endphp

<x-layouts.public>
    <section class="overflow-hidden bg-base-100">
        <x-PageContainer>
            <div class="grid items-center gap-10 lg:grid-cols-12 lg:gap-14">
                <header class="lg:col-span-7" data-aos="fade-up">
                    <p class="mb-4 text-sm font-bold uppercase tracking-[0.22em] text-primary-ink">Hit the Grounds 2026</p>
                    <h1 class="font-heading text-[clamp(2.75rem,8vw,5.5rem)]">
                        <span class="text-base-content">Awards & </span>
                        <span class="text-primary">Recognition</span>
                    </h1>
                    <p class="mt-5 max-w-2xl text-lg leading-relaxed text-base-content/75">
                        Celebrating the teams, players, and unforgettable performances that define the tournament.
                    </p>
                </header>

                <div class="relative mx-auto h-72 w-full max-w-md sm:h-80 lg:col-span-5" aria-hidden="true" data-aos="fade-up" data-aos-delay="120">
                    <div class="absolute inset-x-8 bottom-3 h-24 rounded-[50%] bg-primary/20 blur-3xl"></div>
                    <img src="/award/award_2.avif" alt="" class="absolute bottom-3 left-[6%] h-[72%] w-auto -rotate-6 object-contain drop-shadow-xl transition duration-500 hover:-translate-y-2 hover:-rotate-3">
                    <img src="/award/award_3.avif" alt="" class="absolute bottom-3 right-[7%] h-[68%] w-auto rotate-6 object-contain drop-shadow-xl transition duration-500 hover:-translate-y-2 hover:rotate-3">
                    <img src="/award/award_1.avif" alt="" class="absolute bottom-0 left-1/2 z-10 h-full w-auto -translate-x-1/2 object-contain drop-shadow-2xl transition duration-500 hover:-translate-x-1/2 hover:-translate-y-2">
                </div>
            </div>
        </x-PageContainer>
    </section>

    <section class="bg-base-200">
        <x-PageContainer>
            <div class="mb-8 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between" data-aos="fade-up">
                <div>
                    <p class="text-sm font-bold uppercase tracking-[0.22em] text-primary-ink">Official award list</p>
                    <h2 class="mt-2 font-heading text-3xl text-base-content sm:text-4xl">Ten ways to make your mark</h2>
                </div>
                <p class="max-w-sm text-base-content/65 sm:text-right">Every run, wicket, boundary, and match-defining moment counts.</p>
            </div>

            <div data-aos="fade-up" data-aos-delay="100">
                <table class="block w-full border-separate border-spacing-y-2 md:table" aria-label="Tournament awards">
                    <thead class="hidden md:table-header-group">
                        <tr class="text-left text-xs font-bold uppercase tracking-[0.18em] text-base-content/55">
                            <th scope="col" class="w-16 px-4 pb-2">No.</th>
                            <th scope="col" class="w-[38%] px-5 pb-2">Award</th>
                            <th scope="col" class="px-5 pb-2">Recognition</th>
                        </tr>
                    </thead>
                    <tbody class="block md:table-row-group">
                        @foreach ($awards as $award)
                            <tr class="group mb-3 block rounded-xl bg-base-100 transition-colors duration-200 hover:bg-primary/10 md:mb-0 md:table-row">
                                <td class="hidden px-4 py-5 align-top font-mono text-sm font-bold text-primary-ink md:table-cell">
                                    {{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}
                                </td>
                                <th scope="row" class="block px-5 pb-1 pt-5 text-left text-lg font-bold text-base-content md:table-cell md:py-5 md:align-top">
                                    {{ $award['name'] }}
                                </th>
                                <td class="block px-5 pb-5 pt-1 leading-relaxed text-base-content/70 md:table-cell md:py-5 md:align-top">
                                    {{ $award['description'] }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-10 flex flex-col gap-5 rounded-2xl bg-secondary px-6 py-6 text-secondary-content sm:flex-row sm:items-center sm:justify-between sm:px-8" data-aos="fade-up">
                <div>
                    <h2 class="font-heading text-2xl sm:text-3xl">Ready to compete?</h2>
                    <p class="mt-1 text-secondary-content/75">Register your team and take your shot at the honours.</p>
                </div>
                <x-mary-button link="{{ route('register') }}" label="Register Your Team" class="btn btn-primary shrink-0 rounded-full" />
            </div>
        </x-PageContainer>
    </section>
</x-layouts.public>
