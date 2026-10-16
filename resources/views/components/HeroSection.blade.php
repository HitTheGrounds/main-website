@php
  $token = request()->cookie('company_token');
  $user = null;
  $isAdmin = false;

  if ($token) {
      $jwtService = new App\Services\JWTService();
      $user = $jwtService->getUserFromToken($token);
      if ($user) {
          $isAdmin = $user->is_admin == true;
      }
  }
@endphp

<section class="relative overflow-hidden bg-base-100 min-h-[calc(100dvh-5rem)] flex items-center">
  <script>document.documentElement.classList.add('js')</script>

  <x-PageContainer class="grid gap-6 lg:grid-cols-[1.1fr_1fr] lg:gap-10 xl:gap-16 items-center lg:!py-8">
    <!-- Column 1: copy and actions -->
    <div class="order-2 flex flex-col text-center lg:order-1">

      <h1 class="reveal reveal-d1 mt-6 font-heading text-[clamp(3rem,9vw,6rem)] text-base-content">
        <span class="sr-only">CSE </span>Hit the<br>
        <span class="text-primary">Grounds.</span>
      </h1>

      <p class="order-3 lg:order-none reveal reveal-d2 mx-auto mt-6 max-w-[56ch] text-base sm:text-lg leading-relaxed text-base-content/75">
        The annual cricket tournament of the Department of Computer Science and Engineering. Where the spirit from the university meets the drive from the industry, it's more than just a game, it's where passion and excellence collide.
      </p>

      <div class="reveal reveal-d3 mt-9 flex flex-col sm:flex-row justify-center gap-3 sm:gap-4">
        @if($user)
          <a href="{{ $isAdmin ? route('admin.dashboard') : route('company.dashboard') }}" class="btn-island btn-island-solid group justify-between sm:justify-start">
            {{ $isAdmin ? 'Admin Dashboard' : 'Dashboard' }}
            <span class="btn-dot"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17 17 7M8 7h9v9"/></svg></span>
          </a>
        @else
          <a href="{{ route('login') }}" class="btn-island btn-island-line group justify-between sm:justify-start lg:hidden">
            Login
            <span class="btn-dot"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17 17 7M8 7h9v9"/></svg></span>
          </a>
          <a href="{{ route('register') }}" class="btn-island btn-island-solid group justify-between sm:justify-start">
            Register
            <span class="btn-dot"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17 17 7M8 7h9v9"/></svg></span>
          </a>
        @endif
        {{-- Mobile only: these sections sit on this page, so link straight to them. --}}
        <div class="grid w-full grid-cols-2 gap-3 sm:w-auto lg:hidden">
          @foreach (['awards' => 'Awards', 'past-seasons' => 'Past Seasons', 'timeline' => 'Timeline', 'committee' => 'Committee'] as $id => $label)
            <a href="#{{ $id }}" class="btn-island-line rounded-full px-4 py-3 text-center text-sm font-bold transition hover:bg-base-200">{{ $label }}</a>
          @endforeach
        </div>
        <a href="{{ route('timeline') }}" class="btn-island btn-island-line group max-lg:!hidden justify-between sm:justify-start">
          View Timeline
          <span class="btn-dot"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
        </a>
      </div>

      {{-- Stat card, hidden for now:
      <div class="hero-stats reveal reveal-d3 mx-auto mt-10 flex max-w-sm justify-center gap-12 border-t border-base-300 pt-6 sm:gap-16">
        <div>
          <div class="font-heading text-4xl tabular-nums text-base-content sm:text-5xl" data-count="20">0+</div>
          <div class="mt-1 text-[11px] font-semibold uppercase tracking-[0.12em] text-base-content/70">Teams</div>
        </div>
        <div>
          <div class="font-heading text-4xl tabular-nums text-primary-ink sm:text-5xl" data-count="300">0+</div>
          <div class="mt-1 text-[11px] font-semibold uppercase tracking-[0.12em] text-base-content/70">Participants</div>
        </div>
      </div>
      --}}
    </div>

    <!-- Column 2: interactive 3D canvas (falls back to the field diagram) -->
    {{-- Crop only the bottom of the mobile field; retain the full stage size for the 3D camera. --}}
    <div class="relative aspect-[16/17] overflow-hidden order-1 reveal reveal-d2 lg:aspect-auto lg:overflow-visible lg:order-2">
      <div id="hero-3d" class="absolute inset-x-0 top-0 aspect-[4/5] lg:relative lg:aspect-auto" style="background: radial-gradient(60% 50% at 50% 60%, rgb(255 106 19 / 0.10), transparent 70%);">
        <div data-stage class="absolute inset-x-0 top-0 aspect-[4/5] w-full lg:relative lg:aspect-auto lg:h-[calc(100dvh-9.5rem)] lg:min-h-[400px] lg:max-h-[780px] lg:[--stage-h:calc(100dvh-9.5rem)]">
          <img src="/hero/field.webp" alt="" aria-hidden="true" class="pointer-events-none absolute left-1/2 top-1/2 lg:top-[48.73%] w-[104%] lg:w-[calc(min(max(var(--stage-h),400px),780px)*1.058)] max-w-none -translate-x-1/2 -translate-y-1/2 select-none">
          <svg data-fallback viewBox="0 0 400 300" class="absolute inset-0 m-auto w-4/5 h-auto text-base-content transition-opacity duration-700" fill="none" role="img" aria-label="Cricket field diagram">
            <circle cx="200" cy="150" r="140" stroke="currentColor" stroke-opacity=".35" stroke-width="1.5" stroke-dasharray="2 7" stroke-linecap="round"/>
            <circle cx="200" cy="150" r="88" stroke="currentColor" stroke-opacity=".55" stroke-width="1.5"/>
            <rect x="184" y="62" width="32" height="176" rx="3" fill="#EBE6DB" stroke="currentColor" stroke-width="1.5"/>
            <path d="M178 84h44M178 216h44" stroke="currentColor" stroke-width="1.5"/>
            <path d="M200 216C262 196 290 120 316 96" stroke="#FF6A13" stroke-width="2" stroke-dasharray="1 7" stroke-linecap="round"/>
            <circle cx="316" cy="96" r="11" fill="#FF6A13"/>
          </svg>
        </div>
      </div>
    </div>
  </x-PageContainer>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
  // Scroll reveal
  const revealObserver = new IntersectionObserver((entries) => {
    entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('is-in'); revealObserver.unobserve(e.target); } });
  }, { threshold: 0.15 });
  document.querySelectorAll('.reveal').forEach(el => revealObserver.observe(el));

  function animateCounter(element) {
    const target = parseInt(element.getAttribute('data-count'));
    const isPercentage = element.textContent.includes('%');
    const hasPlus = element.textContent.includes('+');
    let current = 0;
    const increment = target / 50;
    const timer = setInterval(() => {
      current += increment;
      if (current >= target) {
        current = target;
        clearInterval(timer);
      }
      const suffix = isPercentage ? '%' : (hasPlus ? '+' : '');
      element.textContent = Math.floor(current) + suffix;
    }, 40);
  }

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.querySelectorAll('[data-count]').forEach(counter => {
          if (!counter.classList.contains('animated')) {
            counter.classList.add('animated');
            animateCounter(counter);
          }
        });
      }
    });
  }, { threshold: 0.5 });

  const statsContainer = document.querySelector('.hero-stats');
  if (statsContainer) observer.observe(statsContainer);
});
</script>

@vite(['resources/js/hero-scene.js'])
