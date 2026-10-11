<div
    class="hero min-h-screen relative overflow-hidden bg-base-100 bg-cover bg-center bg-no-repeat text-base-content"
    style="background-image: url('/images/about.jpeg');">
    <div class="absolute inset-0 bg-black/60" aria-hidden="true"></div>

    <x-PageContainer class="hero-content max-w-none flex-col lg:flex-row gap-8 sm:gap-12 md:gap-16 lg:gap-20 xl:gap-32 2xl:gap-56 z-10">
      <!-- Text Content -->
      <div class="w-full lg:flex-1 flex flex-col gap-6 sm:gap-8 md:gap-10 lg:gap-12 text-center" data-aos="fade-up" data-aos-duration="1000">
      <h2 class="text-2xl sm:text-3xl md:text-4xl lg:text-5xl xl:text-6xl font-bold text-white font-title">
        Bridging Excellence in Technology and Sport
      </h2>
      <p class="text-xs sm:text-sm md:text-base lg:text-lg leading-relaxed text-white/90">
        Hit the Grounds is the annual cricket tournament hosted by the Department of Computer Science and Engineering (CSE), celebrating sportsmanship, teamwork, and university spirit. This exciting event brings together students, faculty, and alumni for a high-energy showcase of skill and team-spirit.
        <br />
        Set against the vibrant backdrop of campus life, the tournament encourages friendly competition and connection, featuring teams from different CSE batches as well as teams from the industry. More than a game, it's a celebration of community, talent, and the love of cricket
      </p>
      </div>

      <!-- Image Section -->
      <div class="w-full lg:flex-1 max-w-md lg:max-w-lg xl:max-w-xl aspect-[4/5] relative flex-shrink-0 hidden md:block" >
        <!-- Geometric accents -->
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
          <!-- Offset frames -->
          <div class="card absolute left-0 top-0 h-4/5 w-4/5 border-4 border-accent/50"></div>
          <div class="card absolute bottom-0 right-0 h-4/5 w-4/5 border-4 border-primary"></div>
          <div class="absolute bottom-6 left-6 h-24 w-24 border-b-4 border-l-4 border-[#24346c]"></div>

          <!-- Concentric target -->
          <div class="absolute -right-4 top-8 grid size-28 place-items-center rounded-full border-2 border-[#24346c]/70">
            <div class="grid size-16 place-items-center rounded-full border border-primary/70">
              <div class="size-4 rotate-45 bg-primary"></div>
            </div>
          </div>

          <!-- Floating diamonds -->
          <div class="absolute left-5 top-8 size-7 rotate-45 border-2 border-[#24346c] bg-base-100"></div>
          <div class="absolute left-14 top-3 size-3 rotate-45 bg-primary"></div>

          <!-- X motif -->
          <div class="absolute left-2 top-1/2 z-0 size-32 -translate-y-1/2">
            <span class="absolute left-1/2 top-0 h-full w-1.5 -translate-x-1/2 rotate-45 bg-[#24346c]"></span>
            <span class="absolute left-1/2 top-0 h-full w-1.5 -translate-x-1/2 -rotate-45 bg-primary"></span>
          </div>

          <!-- Dot matrix -->
          <div class="absolute -left-3 bottom-7 grid grid-cols-6 gap-3">
            @for ($dot = 0; $dot < 30; $dot++)
              <span class="rounded-full {{ $dot % 5 === 0 ? 'size-2.5 bg-[#24346c]' : ($dot % 3 === 0 ? 'size-2 bg-primary' : 'size-2 bg-base-content/35') }}"></span>
            @endfor
          </div>

          <div class="absolute -right-3 bottom-24 grid grid-cols-5 gap-3">
            @for ($dot = 0; $dot < 20; $dot++)
              <span class="rounded-full {{ $dot % 4 === 0 ? 'size-2.5 bg-[#24346c]' : 'size-2 bg-primary/70' }}"></span>
            @endfor
          </div>

          <!-- Stacked finishing bars -->
          <div class="absolute -bottom-3 right-8 flex items-end gap-2">
            <span class="h-2 w-10 bg-base-content/60"></span>
            <span class="h-2 w-16 bg-primary"></span>
            <span class="size-2 bg-[#24346c]"></span>
          </div>
        </div>

        <!-- Main logo box: frosted glass -->
        <div class="card z-10 absolute inset-0 m-auto flex h-4/5 w-4/5 items-center justify-center overflow-hidden border border-white/30 bg-white/10 p-6 shadow-[0_30px_60px_-20px_rgb(0_0_0_/_.55),inset_0_1px_0_rgb(255_255_255_/_.45)] backdrop-blur-xl backdrop-saturate-150 sm:p-8" data-aos="fade-up" data-aos-duration="1500" data-aos-easing="ease-in-out" data-aos-delay="300">
          <div class="pointer-events-none absolute inset-0 bg-[linear-gradient(135deg,rgb(255_255_255_/_.28)_0%,rgb(255_255_255_/_.06)_38%,transparent_60%)]" aria-hidden="true"></div>
          <div class="pointer-events-none absolute -right-16 -top-16 size-56 rounded-full bg-primary/25 blur-3xl" aria-hidden="true"></div>
          <img src="/images/htg-logo-orange.avif" alt="HIT THE GROUNDS 2026" width="1500" height="710" class="relative w-full h-auto object-contain drop-shadow-[0_8px_20px_rgba(255,106,19,0.35)]">
        </div>
      </div>
    </x-PageContainer>
</div>
