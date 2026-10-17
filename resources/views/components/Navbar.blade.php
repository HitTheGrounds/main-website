 <header x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false" class="sticky top-0 z-40 px-3 sm:px-6 pt-3 sm:pt-4 pointer-events-none">
  <div class="navbar pointer-events-auto w-full rounded-full bg-[#FBF9F4]/90 backdrop-blur-xl ring-1 ring-black/5 shadow-[0_12px_32px_-16px_rgba(31,27,22,0.25)] px-3 sm:px-5 min-h-0 py-1.5">
    <div class="navbar-start">
      <!-- Mobile menu button -->
      <button type="button" class="btn btn-ghost btn-circle lg:hidden" aria-label="Menu" aria-controls="mobile-menu" :aria-expanded="open" @click="open = !open">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" />
        </svg>
      </button>

      <!-- Logo -->
      <a href="/" class="px-1 sm:px-4 flex items-center gap-2 cursor-pointer">
        <img src="{{ asset('navbar-logo.svg') }}" alt="HIT THE GROUNDS" class="h-8 w-16 sm:h-10 sm:w-20 md:h-12 md:w-24 object-contain block">
      </a>
    </div>

    <!-- Desktop Menu -->
    <div class="navbar-center hidden lg:flex">
      <ul class="menu menu-horizontal px-1 gap-2 xl:gap-4">
        <li><a href="/" class="{{ request()->routeIs('home') ? 'active font-bold underline decoration-primary decoration-[3px] underline-offset-8 bg-transparent' : 'text-base-content font-semibold' }}">Home</a></li>
          {{-- <li><a href="{{ route("registrations") }}" class="{{ request()->routeIs('registrations') ? 'active font-bold underline decoration-primary decoration-[3px] underline-offset-8 bg-transparent' : 'text-base-content font-semibold' }}">Registrations</a></li> --}}
          <li><a href="{{ route("rules") }}" class="{{ request()->routeIs('rules') ? 'active font-bold underline decoration-primary decoration-[3px] underline-offset-8 bg-transparent' : 'text-base-content font-semibold' }}">Rules</a></li>
          <li><a href="{{ route("timeline") }}" class="{{ request()->routeIs('timeline') ? 'active font-bold underline decoration-primary decoration-[3px] underline-offset-8 bg-transparent' : 'text-base-content font-semibold' }}">Timeline</a></li>
          <li><a href="{{ route("awards") }}" class="{{ request()->routeIs('awards') ? 'active font-bold underline decoration-primary decoration-[3px] underline-offset-8 bg-transparent' : 'text-base-content font-semibold' }}">Awards</a></li>
          <li><a href="{{ route("seasons") }}" class="{{ request()->routeIs('seasons') ? 'active font-bold underline decoration-primary decoration-[3px] underline-offset-8 bg-transparent' : 'text-base-content font-semibold' }}">Past Seasons</a></li>
          {{-- <li><a href="{{ route("partners") }}" class="{{ request()->routeIs('partners') ? 'active font-bold underline decoration-primary decoration-[3px] underline-offset-8 bg-transparent' : 'text-base-content font-semibold' }}">Partners</a></li> --}}
          <li><a href="{{ route("memories") }}" class="{{ request()->routeIs('memories') ? 'active font-bold underline decoration-primary decoration-[3px] underline-offset-8 bg-transparent' : 'text-base-content font-semibold' }}">Memories</a></li>
      </ul>
    </div>

    <!-- CTA Button -->
    <div class="navbar-end">
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

      @if($user)
        @if($isAdmin)
          <a href="{{ route('admin.dashboard') }}" class="btn btn-primary rounded-full btn-sm sm:btn-md text-xs sm:text-sm md:text-base">
            <span class="hidden sm:inline">Admin Dashboard</span>
            <span class="sm:hidden">Admin</span>
          </a>
        @else
          <a href="{{ route('company.dashboard') }}" class="btn btn-primary rounded-full btn-sm sm:btn-md text-xs sm:text-sm md:text-base">
            <span class="hidden sm:inline">Dashboard</span>
            <span class="sm:hidden">Dashboard</span>
          </a>
        @endif
      @else
        <a href="{{ route('login') }}" class="btn btn-primary rounded-full btn-sm sm:btn-md text-xs sm:text-sm md:text-base">
          <span class="hidden sm:inline">Company Login</span>
          <span class="sm:hidden">Login</span>
        </a>
      @endif
    </div>
  </div>
  {{-- Mobile menu: a small card under the top bar --}}
  @php
    $menu = ['home' => 'Home', 'rules' => 'Rules', 'timeline' => 'Timeline', 'awards' => 'Awards', 'seasons' => 'Past Seasons', 'memories' => 'Memories', 'about' => 'About'];
  @endphp
  <nav id="mobile-menu" aria-label="Menu" x-show="open" x-cloak x-transition.origin.top.duration.150ms
       class="pointer-events-auto absolute inset-x-3 top-full mt-2 rounded-3xl bg-[#FBF9F4] p-2 shadow-[0_16px_40px_-16px_rgba(31,27,22,0.35)] ring-1 ring-black/5 sm:inset-x-6 lg:hidden">
    <ul>
      @foreach ($menu as $name => $label)
        @php $on = request()->routeIs($name); @endphp
        <li>
          <a href="{{ route($name) }}" @click="open = false" @if($on) aria-current="page" @endif
             class="block rounded-2xl px-4 py-2.5 text-base {{ $on ? 'bg-primary/10 font-bold text-primary-ink' : 'font-semibold text-base-content' }}">{{ $label }}</a>
        </li>
      @endforeach
    </ul>
  </nav>
</header>
