@php
    // Whole days left, counted in Sri Lanka time so the number changes at local midnight.
    $today = now('Asia/Colombo')->startOfDay();
    $regDays = (int) $today->diffInDays(\Carbon\Carbon::create(2026, 11, 8, 0, 0, 0, 'Asia/Colombo'), false);
@endphp

<x-TimelineSection />

<section class="bg-base-200">
  <x-PageContainer>
    <div class="grid grid-cols-1 gap-10 md:grid-cols-3 md:gap-12">

      <div class="border-t-2 border-base-content pt-6 text-center" data-aos="fade-up">
        <h3 class="text-lg font-semibold text-base-content">Tournament Duration</h3>
        <p class="mt-3 font-heading text-5xl sm:text-6xl text-primary">1 Day</p>
        <p class="mt-3 text-base-content/75">Full day of cricket action</p>
      </div>

      <div class="border-t-2 border-base-content pt-6 text-center" data-aos="fade-up">
        <h3 class="text-lg font-semibold text-base-content">{{ $regDays >= 0 ? 'Registration Closes In' : 'Registration' }}</h3>
        <p class="mt-3 font-heading text-5xl sm:text-6xl text-primary">
          {{ $regDays > 1 ? $regDays . ' Days' : ($regDays === 1 ? '1 Day' : ($regDays === 0 ? 'Last Day' : 'Closed')) }}
        </p>
        <p class="mt-3 text-base-content/75">{{ $regDays >= 0 ? 'Until November 8, 2026' : 'November 8, 2026' }}</p>
      </div>

      <div class="border-t-2 border-base-content pt-6 text-center" data-aos="fade-up">
        <h3 class="text-lg font-semibold text-base-content">Tournament Date</h3>
        <p class="mt-3 font-heading text-5xl sm:text-6xl text-primary">Nov 21</p>
        <p class="mt-3 text-base-content/75">November 21, 2026</p>
      </div>

    </div>
  </x-PageContainer>
</section>
