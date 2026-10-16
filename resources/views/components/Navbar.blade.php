 <header class="sticky top-0 z-40 px-3 sm:px-6 pt-3 sm:pt-4 pointer-events-none">
  <div class="navbar pointer-events-auto w-full rounded-full bg-[#FBF9F4]/90 backdrop-blur-xl ring-1 ring-black/5 shadow-[0_12px_32px_-16px_rgba(31,27,22,0.25)] px-3 sm:px-5 min-h-0 py-1.5">
    <div class="navbar-start">
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
</header>

{{-- Mobile bottom tab bar (replaces the old hamburger menu). Awards, Past Seasons and Timeline live on the home page on mobile. --}}
@php
  $tabs = [
    ['home', 'Home', 'M3 11l9-8 9 8M5 10v10h5v-6h4v6h5V10'],
    ['rules', 'Rules', 'M7 3h8l4 4v14H7zM14 3v5h5M10 13h6M10 17h6'],
    ['memories', 'Memories', 'M4 5h16v14H4zM4 15l5-5 4 4 3-3 4 4M9 9h.01'],
  ];
@endphp
<nav aria-label="Primary" class="lg:hidden fixed inset-x-3 bottom-3 z-40 rounded-full bg-[#FBF9F4]/95 backdrop-blur-xl ring-1 ring-black/5 shadow-[0_12px_32px_-12px_rgba(31,27,22,0.4)] px-2 py-1.5 pb-[max(0.375rem,env(safe-area-inset-bottom))]">
  <ul class="grid grid-cols-3 gap-1">
    @foreach ($tabs as [$name, $label, $icon])
      @php $on = request()->routeIs($name); @endphp
      <li>
        <a href="{{ route($name) }}" @if($on) aria-current="page" @endif
           class="flex flex-col items-center gap-0.5 rounded-full py-1.5 text-[11px] font-semibold transition {{ $on ? 'bg-primary text-primary-content' : 'text-base-content/70' }}">
          <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icon }}"/></svg>
          {{ $label }}
        </a>
      </li>
    @endforeach
  </ul>
</nav>
