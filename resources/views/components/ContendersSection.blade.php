<section class="bg-base-100 py-24 md:py-32">
  <div class="mx-auto max-w-6xl px-4 sm:px-6">
    <h2 class="font-heading text-[clamp(2.25rem,6vw,4rem)] text-base-content mb-10 md:mb-14" data-aos="fade-up">
      Meet the <span class="text-primary">Contenders</span>
    </h2>

    <!-- Industry Teams -->
    <a href="{{ route('teams.industry') }}" class="group relative block overflow-hidden rounded-2xl aspect-[4/5] sm:aspect-[16/10] lg:aspect-[21/9]" data-aos="fade-up" data-aos-duration="1000">
      <img src="/images/contenders.avif" alt="A village cricket match in progress: a batter at the crease with fielders behind"
           class="absolute inset-0 h-full w-full object-cover transition-transform duration-[1200ms] ease-[cubic-bezier(0.32,0.72,0,1)] group-hover:scale-105" loading="lazy">
      <div class="absolute inset-0 bg-gradient-to-t from-black/75 via-black/15 to-transparent"></div>

      <div class="absolute inset-x-0 bottom-0 flex items-end justify-between gap-6 p-6 sm:p-10">
        <h3 class="font-heading text-4xl sm:text-5xl lg:text-6xl text-base-100">Industry Teams</h3>
        <span class="grid size-14 shrink-0 place-items-center rounded-full bg-primary text-primary-content transition-transform duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] group-hover:translate-x-1 group-hover:-translate-y-1" aria-hidden="true">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M8 7h9v9"/></svg>
        </span>
      </div>
    </a>
  </div>
</section>
