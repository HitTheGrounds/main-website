<div class="hero min-h-screen relative overflow-hidden bg-black">
    <!-- Layered hero images -->
    <img src="/hero/0_Background.avif" alt="" class="absolute inset-0 w-full h-full object-cover" data-aos="fade" data-aos-duration="1000" />
    <img src="/hero/1_Particles.avif" alt="" class="absolute inset-0 w-full h-full object-cover mix-blend-screen" data-aos="fade-right" data-aos-duration="500" data-aos-delay="700" />
    <img src="/hero/2_Energy_Lines.avif" alt="" class="absolute inset-0 w-full h-full object-cover mix-blend-screen" data-aos="fade-right" data-aos-duration="500" data-aos-delay="500" />
    <img src="/hero/3_Player.avif" alt="" class="absolute inset-0 w-full h-full object-cover hidden lg:block" data-aos="fade-right" data-aos-duration="500" data-aos-delay="500" />
    <img id="leftLight" src="/hero/4_Left_Light.avif" alt="" class="absolute inset-0 w-full h-full object-cover opacity-0" />
    {{-- <img src="/hero/5_Right_Light.avif" alt="" class="absolute inset-0 w-full h-full object-cover" data-aos="zoom-in" data-aos-duration="1500" data-aos-delay="800" /> --}}
    <img id="leftBeam" src="/hero/6_Left_Light_Beam.avif" alt="" class="absolute inset-0 w-full h-full object-cover opacity-0" />
    {{-- <img src="/hero/7_Right_Light_Beam.avif" alt="" class="absolute inset-0 w-full h-full object-cover" data-aos="fade-down-left" data-aos-duration="2000" data-aos-delay="1000" /> --}}

    <!-- Dark overlay/vignette -->
    <div class="absolute inset-0 bg-gradient-to-r from-black/80 via-transparent to-black/80"></div>
    <div class="absolute inset-0 bg-gradient-to-b from-black/60 via-transparent to-black/60"></div>

    <!-- Main content -->
    <div class="hero-content relative lg:absolute lg:right-8 xl:right-16 2xl:right-24 lg:top-16 xl:top-20 2xl:top-24 flex-col items-end gap-8 sm:gap-10 md:gap-12 w-full max-w-full lg:max-w-2xl xl:max-w-3xl z-10 px-4 sm:px-6 md:px-8 lg:px-0 py-12 sm:py-16 lg:py-0">
      <div class="flex flex-col gap-6 sm:gap-8">
        

        <!-- Main heading -->
        <div>
          <h1 class="w-full text-center md:text-right" data-aos="fade-up" data-aos-duration="500">
            <span class="text-primary text-4xl sm:text-5xl md:text-6xl lg:text-7xl xl:text-8xl font-normal font-heading uppercase leading-tight">CSE </span>
            <span class="text-white dark:text-base-content text-4xl sm:text-5xl md:text-6xl lg:text-7xl xl:text-8xl font-normal font-heading uppercase leading-tight">Hit </span>
            <span class="text-primary text-4xl sm:text-5xl md:text-6xl lg:text-7xl xl:text-8xl font-normal font-heading uppercase leading-tight">The </span>
            <span class="text-white dark:text-base-content text-4xl sm:text-5xl md:text-6xl lg:text-7xl xl:text-8xl font-normal font-heading uppercase leading-tight">Grounds </span>
          </h1>
          <img src="/cse.avif" alt="CSE" class="block md:hidden mt-2 w-32 mx-auto" data-aos="fade-up" data-aos-duration="500" data-aos-delay="100" />
        </div>

        <!-- Description -->
        <p class="text-white dark:text-base-content text-xs sm:text-sm md:text-base lg:text-lg xl:text-xl font-medium leading-relaxed max-w-prose text-center md:text-right" data-aos="zoom-in" >
          Join us for Hit the Grounds – the annual cricket tournament organized by the Department of Computer Science and Engineering of the University of Moratuwa. Where the spirit from the university meets the drive from the industry, it's more than just a game, it's where passion and excellence collide!
        </p>

        <!-- Buttons -->
        <div class="flex flex-col justify-end sm:flex-row gap-4 sm:gap-6">
          <x-mary-button label="View Timeline" link="{{ route('timeline') }}" class="btn btn-neutral btn-md sm:btn-md lg:btn-lg w-full sm:w-auto min-h-12" />
          @php
            $token = request()->cookie('company_token');
            $user = null;
            $isAdmin = false;

            if ($token) {
                $jwtService = new App\Services\JWTService();
                $user = $jwtService->getUserFromToken($token);
                if ($user) {
                    $isAdmin = $user->isAdmin();
                }
            }
          @endphp

          @if($user)
            @if($user->isAdmin())
              <x-mary-button label="Admin Dashboard" link="{{ route('admin.dashboard') }}" class="btn btn-primary btn-md sm:btn-md lg:btn-lg" />
            @elseif($user->isScorer())
              <x-mary-button label="Scorer Portal" link="/scorer/dashboard" class="btn btn-primary btn-md sm:btn-md lg:btn-lg" />
            @else
              <x-mary-button label="Dashboard" link="{{ route('company.dashboard') }}" class="btn btn-primary btn-md sm:btn-md lg:btn-lg" />
            @endif
          @else
            <x-mary-button label="Register" link="{{ route('register') }}" class="btn btn-primary btn-md sm:btn-md lg:btn-lg" />
          @endif
        </div>
      </div>

      <!-- Stats section -->
      <div class="stats stats-vertical sm:stats-horizontal shadow-xl bg-base-100/10 backdrop-blur-sm w-full sm:w-auto lg:min-w-2xl">
        <div class="stat place-items-center py-4 sm:py-6">
          <div class="stat-value text-white font-title text-3xl sm:text-4xl lg:text-5xl" data-count="20">0+</div>
          <div class="stat-title text-white dark:text-base-content text-xs sm:text-sm">Teams</div>
        </div>
        <div class="stat place-items-center py-4 sm:py-6">
          <div class="stat-value text-white font-title text-3xl sm:text-4xl lg:text-5xl" data-count="300">0+</div>
          <div class="stat-title text-white dark:text-base-content text-xs sm:text-sm">Participants</div>
        </div>
        <div class="stat place-items-center py-4 sm:py-6">
          <div class="stat-value text-white font-title text-3xl sm:text-4xl lg:text-5xl" data-count="100">0%</div>
          <div class="stat-title text-white dark:text-base-content text-xs sm:text-sm">Entertainment</div>
        </div>
      </div>
    </div>

  </div>

  <script>
document.addEventListener('DOMContentLoaded', function() {
  // Glitch animation for left light and beam
  setTimeout(() => {
    const leftLight = document.getElementById('leftLight');
    const leftBeam = document.getElementById('leftBeam');

    if (leftLight && leftBeam) {
      // Glitch sequence: flicker a few times before staying on
      const glitchSequence = [
        { time: 0, opacity: 1 },      // Flash on
        { time: 100, opacity: 0 },    // Flash off
        { time: 250, opacity: 1 },    // Flash on
        { time: 350, opacity: 0 },    // Flash off
        { time: 500, opacity: 1 },    // Flash on
        { time: 550, opacity: 0 },    // Flash off
        { time: 700, opacity: 1 },    // Stay on
      ];

      glitchSequence.forEach(({ time, opacity }) => {
        setTimeout(() => {
          leftLight.style.opacity = opacity;
          leftBeam.style.opacity = opacity;
        }, time);
      });
    }
  }, 1200); // Start glitch after 1200ms delay


  function animateCounter(element) {
    const target = parseInt(element.getAttribute('data-count'));
    const isPercentage = element.textContent.includes('%');
    const hasPlus = element.textContent.includes('+');
    let current = 0;
    const increment = target / 50; // Duration control
    const timer = setInterval(() => {
      current += increment;
      if (current >= target) {
        current = target;
        clearInterval(timer);
      }
      
      const suffix = isPercentage ? '%' : (hasPlus ? '+' : '');
      element.textContent = Math.floor(current) + suffix;
    }, 40); // Speed control (40ms = smooth animation)
  }

  // Intersection Observer for triggering animation when in view
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const counters = entry.target.querySelectorAll('[data-count]');
        counters.forEach(counter => {
          if (!counter.classList.contains('animated')) {
            counter.classList.add('animated');
            animateCounter(counter);
          }
        });
      }
    });
  }, { threshold: 0.5 });

  // Observe the stats container
  const statsContainer = document.querySelector('.stats');
  if (statsContainer) {
    observer.observe(statsContainer);
  }
});
</script>