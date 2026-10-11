<section class="bg-base-100">
  <x-PageContainer>
    <h2 class="font-heading text-center text-[clamp(2.25rem,6vw,4rem)] text-base-content mb-10 md:mb-14" data-aos="fade-up">
      Meet the <span class="text-primary">Contenders</span>
    </h2>

    <!-- Industry Teams -->
    <a href="{{ route('teams.industry') }}" class="group relative block overflow-hidden rounded-2xl h-[260px] sm:h-[320px] lg:h-[380px]" data-aos="fade-up" data-aos-duration="1000">
      <img src="/images/industry-teams.avif" alt="Illustration of a batter in orange raising his bat and fist in celebration while a teammate kneels beside the stumps" style="object-position: 50% 22%"
           class="absolute inset-0 h-full w-full object-cover transition-transform duration-[1200ms] ease-[cubic-bezier(0.32,0.72,0,1)] group-hover:scale-105" loading="lazy">
      <div class="absolute inset-0 bg-gradient-to-t from-black/75 via-black/15 to-transparent"></div>

      <div class="absolute inset-x-0 bottom-0 flex items-end justify-between gap-6 p-6 sm:p-10">
        <h3 class="font-heading text-4xl sm:text-5xl lg:text-6xl text-base-100">Industry Teams</h3>
        <span class="grid size-14 shrink-0 place-items-center rounded-full bg-primary text-primary-content transition-transform duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] group-hover:translate-x-1 group-hover:-translate-y-1" aria-hidden="true">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M8 7h9v9"/></svg>
        </span>
      </div>
    </a>
  </x-PageContainer>
</section>
