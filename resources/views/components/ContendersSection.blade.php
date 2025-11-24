
<section class="bg-base-200 py-16 md:py-24">
  <div class="container mx-auto px-4">
    <h2 class="text-4xl md:text-5xl font-bold text-center mb-12 font-title" data-aos="zoom-in">
      Meet the Contenders
    </h2>

    <div class="flex justify-center gap-6 max-w-6xl mx-auto">
      <!-- University Teams -->
      {{-- <a href="{{ route('teams.university') }}" class="card card-compact bg-base-100 shadow-sm hover:shadow-md transition-all duration-300 cursor-pointer group"  data-aos="fade-right" data-aos-duration="1500" data-aos-delay="500">
        <figure class="h-64 md:h-80 overflow-hidden">
          <img
            src="/red_team.avif"
            alt="University Teams"
            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
          />
        </figure>
        <div class="card-body">
          <h3 class="card-title text-2xl md:text-3xl justify-center">
            University Teams
          </h3>
        </div>
      </a> --}}

      <!-- Industry Teams -->
      <a href="{{ route('teams.industry') }}" class="card card-compact bg-base-100 shadow-sm hover:shadow-md transition-all duration-300 cursor-pointer group" data-aos="fade-left" data-aos-duration="1500" data-aos-delay="500">
        <figure class="h-64 md:h-80 overflow-hidden" >
          <img
            src="/blue_team.avif"
            alt="Industry Teams"
            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
          />
        </figure>
        <div class="card-body">
          <h3 class="card-title text-2xl md:text-3xl justify-center">
            Industry Teams
          </h3>
        </div>
      </a>
    </div>
  </div>
</section>
