@php
    // Tier => logo tiles: [name, file, width, height, tile classes]. The first tier spans the full width.
    $tiers = [
        'Title sponsor' => [
            ['GTN', 'gtn', 1100, 495, 'h-28 sm:h-40 lg:h-48'],
        ],
        'Silver sponsors' => [
            ['DirectFN', 'directfn', 900, 154, 'h-9 sm:h-11'],
            ['Thomson Reuters', 'thomson-reuters', 900, 300, 'h-14 sm:h-[4.5rem]'],
        ],
        'Bronze sponsors' => [
            ['99x', '99x', 900, 461, 'h-14 sm:h-16'],
            ['PickMe', 'pickme', 900, 218, 'h-9 sm:h-11'],
        ],
    ];
@endphp

<section class="relative overflow-hidden bg-base-200">
  <x-PageContainer :floating-squares="false">
    <div class="reveal text-center mb-12 md:mb-16">
      <h2 class="font-heading text-[clamp(2.25rem,6vw,4rem)] text-base-content">
        Our <span class="text-primary">sponsors.</span>
      </h2>
      <p class="mx-auto mt-4 max-w-[48ch] text-base sm:text-lg leading-relaxed text-base-content/75">
        The partners who put Hit the Grounds 2026 on the field.
      </p>
    </div>

    <div class="grid grid-cols-1 gap-x-16 gap-y-12 md:grid-cols-2">
      @foreach ($tiers as $tier => $logos)
        <div @class(['reveal border-t border-base-content pt-6 text-center', 'md:col-span-2' => $loop->first, 'reveal-d1' => $loop->last])>
          <h3 class="font-heading text-2xl sm:text-3xl text-base-content">{{ $tier }}</h3>
          <div @class(['mx-auto mt-6 grid max-w-3xl grid-cols-1 gap-3', 'sm:grid-cols-2' => count($logos) > 1])>
            @foreach ($logos as [$name, $file, $w, $h, $size])
              <div @class(['flex items-center justify-center px-5 py-6 transition-transform duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:-translate-y-1 motion-reduce:transition-none motion-reduce:hover:translate-y-0', 'sm:py-10' => $loop->parent->first])>
                <img src="{{ asset('sponsors/'.$file.'.webp') }}" alt="{{ $name }}" width="{{ $w }}" height="{{ $h }}" loading="lazy" decoding="async"
                     class="{{ $size }} w-auto max-w-full object-contain">
              </div>
            @endforeach
          </div>
        </div>
      @endforeach
    </div>
  </x-PageContainer>
</section>
