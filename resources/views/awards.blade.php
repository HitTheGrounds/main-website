<x-layouts.public>
    <!-- Hero Section -->
    <section class="py-12 sm:py-16 md:py-20 lg:py-24">
      <div class="container mx-auto px-4 sm:px-6 md:px-8">
        <div class="max-w-4xl mx-auto text-center">
          <h1 class="text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-bold font-heading uppercase mb-6" data-aos="fade-up" data-aos-duration="1000">
            <span class="text-base-content">Awards & </span>
            <span class="text-primary-ink">Recognition</span>
          </h1>
          <p class="text-base-content/80 text-base sm:text-lg md:text-xl leading-relaxed" data-aos="fade-up" data-aos-duration="1500">
            More than just runs and wickets — it's about celebrating those standout moments.
            Here are the awards that will honor the best of the best!
          </p>
        </div>
      </div>
    </section>

    <!-- Awards Section -->
    <section class="bg-base-100 py-12 sm:py-16 md:py-20">
      <div class="container mx-auto px-4 sm:px-6 md:px-8">
        <div class="max-w-7xl mx-auto space-y-20">

          <!-- Champions Section -->
          <div data-aos="fade-up" data-aos-duration="1000">
            <h2 class="text-3xl sm:text-4xl md:text-5xl font-bold font-title text-center mb-8">
              <span class="text-primary-ink">Tournament </span>
              <span class="text-base-content">Champions</span>
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 lg:gap-12 items-center">
              <div class="flex justify-center" data-aos="fade-up" data-aos-duration="1200">
                <img src="/award/award_1.avif" alt="Champions Trophy" class="w-full max-w-md object-contain">
              </div>
              <div class="text-center md:text-left" data-aos="fade-left" data-aos-duration="1200">
                <div class="badge badge-primary badge-lg mb-4">Team Award</div>
                <h3 class="text-2xl sm:text-3xl font-title text-primary-ink mb-4">Champions Trophy</h3>
                <p class="text-base-content/80 text-base md:text-lg leading-relaxed">
                  The ultimate prize for the team that demonstrates exceptional skill, teamwork, and determination throughout the tournament. Only the best will lift this prestigious trophy.
                </p>
              </div>
            </div>
          </div>

          <div class="divider"></div>

          <!-- Runner-up Section -->
          <div data-aos="fade-up" data-aos-duration="1000">
            <h2 class="text-3xl sm:text-4xl md:text-5xl font-bold font-title text-center mb-8">
              <span class="text-base-content">Tournament </span>
              <span class="text-warning">Runner-up</span>
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 lg:gap-12 items-center">
              <div class="text-center md:text-left order-2 md:order-1" data-aos="fade-right" data-aos-duration="1200">
                <div class="badge badge-warning badge-lg mb-4">Team Award</div>
                <h3 class="text-2xl sm:text-3xl font-title text-warning mb-4">Runner-up Trophy</h3>
                <p class="text-base-content/80 text-base md:text-lg leading-relaxed">
                  Recognition for the team with outstanding performance throughout the tournament. Awarded to the 2nd place finisher who demonstrated exceptional competitiveness and sportsmanship.
                </p>
              </div>
              <div class="flex justify-center order-1 md:order-2" data-aos="fade-up" data-aos-duration="1200">
                <img src="/award/award_2.avif" alt="Runner-up Trophy" class="w-full max-w-md object-contain">
              </div>
            </div>
          </div>

          <div class="divider"></div>

          <!-- Best Performing Players Section -->
          <div data-aos="fade-up" data-aos-duration="1000">
            <h2 class="text-3xl sm:text-4xl md:text-5xl font-bold font-title text-center mb-8">
              <span class="text-base-content">Best Performing </span>
              <span class="text-primary-ink">Players</span>
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 lg:gap-12 items-center">
              <div class="flex justify-center" data-aos="fade-up" data-aos-duration="1200">
                <img src="/award/award_3.avif" alt="Best Performing Players Trophy" class="w-full max-w-md object-contain">
              </div>
              <div class="text-center md:text-left" data-aos="fade-left" data-aos-duration="1200">
                <div class="badge badge-lg mb-4 border-0 bg-secondary text-secondary-content">Individual Awards</div>
                <h3 class="text-2xl sm:text-3xl font-title text-primary-ink mb-4">Excellence Recognition</h3>
                <p class="text-base-content/80 text-base md:text-lg leading-relaxed mb-6">
                  Celebrating individual excellence across various categories. These awards recognize players who shine in specific aspects of the game.
                </p>
                <ul class="divide-y divide-base-content/20 border-y border-base-content/20 text-left">
                  <li class="flex items-center gap-3 py-3 text-lg font-semibold text-base-content"><span class="size-1.5 shrink-0 bg-primary"></span>Best Batsman</li>
                  <li class="flex items-center gap-3 py-3 text-lg font-semibold text-base-content"><span class="size-1.5 shrink-0 bg-primary"></span>Best Bowler</li>
                  <li class="flex items-center gap-3 py-3 text-lg font-semibold text-base-content"><span class="size-1.5 shrink-0 bg-primary"></span>Best Female Player</li>
                </ul>
              </div>
            </div>
          </div>

        </div>
      </div>
    </section>

    <!-- Call to Action Section -->
    <section class="bg-base-200 py-12 sm:py-16 md:py-20" data-aos="fade-up" data-aos-duration="1500">
      <div class="container mx-auto px-4 sm:px-6 md:px-8">
        <div class="card bg-base-100 shadow-xl border border-primary max-w-4xl mx-auto">
          <div class="card-body text-center py-8 md:py-12">
            <h2 class="card-title text-3xl sm:text-4xl md:text-5xl font-title text-primary-ink justify-center mb-4">
              Ready to Compete?
            </h2>
            <p class="text-base-content/80 text-base md:text-lg mb-6">
              Register your team now and compete for these prestigious awards!
            </p>
            <div class="card-actions justify-center">
              <x-mary-button link="{{ route('register') }}" label="Register Your Team" class="btn btn-primary btn-lg" />
            </div>
          </div>
        </div>
      </div>
    </section>
</x-layouts.public>
