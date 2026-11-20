<section id="ground-plan" aria-labelledby="ground-plan-title" class="relative isolate bg-base-200">
    <x-PageContainer class="ground-plan-container">
        <div class="ground-plan-decoration" aria-hidden="true">
            @foreach ([2 => 'ball', 3 => 'player', 4 => 'ball', 5 => 'player', 6 => 'bat', 7 => 'ball'] as $position => $kind)
                <x-CricketDoodle :kind="$kind" class="ground-plan-doodle ground-plan-doodle-{{ $position }}" />
            @endforeach
            <svg class="ground-plan-extra ground-plan-bat" viewBox="0 0 120 180" fill="none">
                <g transform="rotate(25 60 90)" stroke="currentColor" stroke-width="4" stroke-linejoin="round">
                    <path d="M51 18h18v55H51z" fill="currentColor"/><path d="M45 73h30l6 78q-21 16-42 0z"/><path d="M51 84v57M60 84v63M69 84v57" stroke-width="2"/>
                </g>
            </svg>
            <svg class="ground-plan-orbit ground-plan-orbit-left" viewBox="0 0 320 540" fill="none">
                <ellipse cx="160" cy="270" rx="138" ry="235" stroke="currentColor" stroke-width="1" />
                <ellipse cx="160" cy="270" rx="108" ry="190" stroke="currentColor" stroke-width="1" stroke-dasharray="5 12" />
                <path class="ground-plan-trail" d="M70 430C280 380 40 170 235 95" stroke="currentColor" stroke-width="2" stroke-dasharray="7 10" />
                <g class="ground-plan-ball"><circle cx="70" cy="430" r="17" fill="currentColor"/><path d="M62 415Q78 430 62 445M68 414Q84 430 68 446" stroke="#F4F1EA" stroke-width="2" stroke-dasharray="3 3"/></g>
            </svg>
            <svg class="ground-plan-orbit ground-plan-orbit-right" viewBox="0 0 320 540" fill="none">
                <ellipse cx="160" cy="270" rx="138" ry="235" stroke="currentColor" stroke-width="1" />
                <ellipse cx="160" cy="270" rx="108" ry="190" stroke="currentColor" stroke-width="1" stroke-dasharray="5 12" />
                <g stroke="currentColor" stroke-width="5" stroke-linecap="round"><path d="M137 310V215M160 310V215M183 310V215M132 210H154M166 210H188"/></g>
                <circle class="ground-plan-ring" cx="160" cy="265" r="65" stroke="currentColor" stroke-width="2" stroke-dasharray="3 14" />
                <path class="ground-plan-trail" d="M70 420Q270 400 245 340" stroke="currentColor" stroke-width="3" stroke-dasharray="6 10" />
                <circle cx="245" cy="340" r="11" fill="currentColor" />
            </svg>
        </div>
        <div class="ground-plan-content mx-auto max-w-[1400px]">
            <div data-aos="fade-up" class="ground-plan-heading mb-6 flex flex-col items-center gap-5 text-center sm:mb-8">
                <div>
                    <p class="mb-3 text-xs font-bold uppercase tracking-[0.2em] text-primary-ink">Find your way</p>
                    <h2 id="ground-plan-title" class="font-heading text-[clamp(2.2rem,9vw,4rem)]">
                        The <span class="text-primary">ground plan.</span>
                    </h2>
                    <p class="mx-auto mt-4 max-w-[48ch] text-base leading-relaxed text-base-content/75 sm:text-lg">
                        University of Moratuwa grounds. Get your bearings before match day.
                    </p>
                </div>
                <a href="https://maps.app.goo.gl/efGNoHjXfRSPCnv96" target="_blank" rel="noopener noreferrer"
                   class="btn-island btn-island-solid shrink-0" aria-label="Open venue in Google Maps (opens in a new tab)">
                    Open in Google Maps
                    <span class="btn-dot" aria-hidden="true">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 17 17 7M7 7h10v10" /></svg>
                    </span>
                </a>
            </div>

            <figure data-aos="fade-up" data-aos-delay="150" class="ground-plan-image overflow-hidden rounded-2xl border border-base-content/15 bg-base-100 sm:rounded-3xl">
                <img src="{{ asset('images/ground-plan-2026.webp') }}" width="1672" height="941"
                         alt="Hit the Grounds 2026 venue plan: University Grounds in the centre, Pavilion to the upper left, Lagaan to the upper right, New Gym to the lower right, and washrooms at L Block across Main Street. John Rodrigo Mawatha runs along the top and left."
                         loading="lazy" decoding="async" class="block h-auto w-full rounded-2xl sm:rounded-3xl">
            </figure>
        </div>
    </x-PageContainer>
</section>
