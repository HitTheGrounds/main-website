
<footer class="bg-base-300 text-base-content border-t-2 border-secondary">
  <div class="w-full px-4 sm:px-6 lg:px-12 py-10 sm:py-16">
    <div class="flex flex-row items-center justify-center gap-5 sm:gap-8 md:gap-12">
      <!-- HTG Logo -->
      <img src="{{ asset('navbar-logo.svg') }}" alt="HIT THE GROUNDS" class="h-11 sm:h-14 md:h-16 w-auto min-w-0">

      <!-- CSE Logo -->
      <img src="/cse.avif" alt="CSE" class="h-11 sm:h-14 md:h-16 w-auto min-w-0">

      <!-- CSESS Logo -->
      <img src="/csess.avif" alt="CSESS" class="h-11 sm:h-14 md:h-16 w-auto min-w-0">
    </div>

    <p class="mt-10 text-center text-sm text-base-content/70">
      &copy; {{ now()->year }} Department of Computer Science and Engineering, University of Moratuwa. All Rights Reserved.
    </p>
  </div>
</footer>
