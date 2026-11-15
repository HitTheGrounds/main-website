@php
    // Logo tiles: [name, file, width, height, tile classes]
    $title = ['GTN', 'gtn', 1100, 495];
    $silver = [
        ['DirectFN', 'directfn', 900, 154, 'h-9 sm:h-11'],
        ['Thomson Reuters', 'thomson-reuters', 900, 300, 'h-14 sm:h-[4.5rem]'],
    ];
    $bronze = [
        ['99x', '99x', 900, 461, 'h-14 sm:h-16'],
        ['PickMe', 'pickme', 900, 218, 'h-9 sm:h-11'],
    ];
@endphp

<section class="relative overflow-hidden bg-base-200">
  <x-PageContainer>
    <div class="reveal text-center mb-12 md:mb-16">
      <h2 class="font-heading text-[clamp(2.25rem,6vw,4rem)] text-base-content">
        Our <span class="text-primary">sponsors.</span>
      </h2>
      <p class="mx-auto mt-4 max-w-[48ch] text-base sm:text-lg leading-relaxed text-base-content/75">
        The partners who put Hit the Grounds 2026 on the field.
      </p>
    </div>

    {{-- Title sponsor --}}
    <div class="reveal border-t border-base-content pt-6 text-center">
      <h3 class="font-heading text-2xl sm:text-3xl text-base-content">Title sponsor</h3>
      <div class="mx-auto mt-6 flex max-w-3xl items-center justify-center rounded-2xl bg-white px-6 py-8 sm:py-12 transition-transform duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:-translate-y-1 motion-reduce:transition-none motion-reduce:hover:translate-y-0">
        <img src="{{ asset('sponsors/'.$title[1].'.webp') }}" alt="{{ $title[0] }}" width="{{ $title[2] }}" height="{{ $title[3] }}" loading="lazy" decoding="async"
             class="h-28 w-auto max-w-full object-contain sm:h-40 lg:h-48">
      </div>
    </div>

    <div class="mt-12 grid grid-cols-1 gap-x-16 gap-y-12 md:mt-16 md:grid-cols-2">
      {{-- Silver --}}
      <div class="reveal border-t border-base-content pt-6 text-center">
        <h3 class="font-heading text-2xl sm:text-3xl text-base-content">Silver sponsors</h3>
        <div class="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-2">
          @foreach ($silver as [$name, $file, $w, $h, $size])
            <div class="flex items-center justify-center rounded-2xl bg-white px-5 py-8 transition-transform duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:-translate-y-1 motion-reduce:transition-none motion-reduce:hover:translate-y-0">
              <img src="{{ asset('sponsors/'.$file.'.webp') }}" alt="{{ $name }}" width="{{ $w }}" height="{{ $h }}" loading="lazy" decoding="async"
                   class="{{ $size }} w-auto max-w-full object-contain">
            </div>
          @endforeach
        </div>
      </div>

      {{-- Bronze --}}
      <div class="reveal reveal-d1 border-t border-base-content pt-6 text-center">
        <h3 class="font-heading text-2xl sm:text-3xl text-base-content">Bronze sponsors</h3>
        <div class="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-2">
          @foreach ($bronze as [$name, $file, $w, $h, $size])
            <div class="flex items-center justify-center rounded-2xl bg-white px-5 py-8 transition-transform duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:-translate-y-1 motion-reduce:transition-none motion-reduce:hover:translate-y-0">
              <img src="{{ asset('sponsors/'.$file.'.webp') }}" alt="{{ $name }}" width="{{ $w }}" height="{{ $h }}" loading="lazy" decoding="async"
                   class="{{ $size }} w-auto max-w-full object-contain">
            </div>
          @endforeach
        </div>
      </div>
    </div>
  </x-PageContainer>
</section>
