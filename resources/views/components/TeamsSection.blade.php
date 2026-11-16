@props(['variant' => 'desktop'])
@php
    // Final team list: [name, logo file]. A null logo means a CSE batch, drawn as a "CSE | 21" lockup.
    // Order is deliberate: the CSE teams are spread between the company logos.
    $teams = [
        ['99x', '99x.webp'],
        ['CSE 18', null],
        ['Aayu Technologies', 'aayu.svg'],
        ['Axonect', 'axonect.webp'],
        ['Creative Software', 'creative-software.webp'],
        ['CSE 21', null],
        ['Enlear', 'enlear.svg'],
        ['DirectFN', 'directfn.webp'],
        ['GTN', 'gtn.webp'],
        ['CSE 22', null],
        ['IronOne Technologies', 'ironone.webp'],
        ['PickMe', 'pickme.webp'],
        ['CSE 23', null],
        ['SenzMate', 'senzmate.webp'],
        ['Short Circuit', 'short-circuit.webp'],
        ['Synopsys', 'synopsys.webp'],
        ['CSE 24', null],
        ['Thomson Reuters', 'thomson-reuters.webp'],
        ['WSO2', 'wso2.webp'],
        ['Xitricon', 'xitricon.webp'],
        ['CSE 25', null],
        ['Yaala Labs', 'yaala.webp'],
    ];
@endphp

{{-- Scroll-driven: tiles start as one stack and fly out to the grid (see resources/js/teams-stack.js).
     Without JS, or with reduced motion, this is just the finished grid. --}}
<section data-teams="{{ $variant }}" class="relative bg-base-100">
  <div data-teams-pin class="flex min-h-[100dvh] items-center">
    <x-PageContainer class="w-full !py-20 lg:!py-16">
      <div class="mb-6 text-center sm:mb-8 lg:mb-10">
        <h2 class="font-heading text-[clamp(2.2rem,9vw,4rem)] text-base-content">
          The <span class="text-primary">teams.</span>
        </h2>
        <p class="mx-auto mt-3 max-w-[44ch] text-base leading-relaxed text-base-content/75 sm:text-lg">
          Industry sides and CSE batches, one field.
        </p>
      </div>

      <ul data-teams-field class="relative mx-auto flex max-w-[1400px] flex-wrap justify-center gap-2 sm:gap-3 lg:gap-4">
        @foreach ($teams as [$name, $logo])
          <li data-team
              class="group flex h-[clamp(3.25rem,8dvh,4.75rem)] w-[calc((100%-1rem)/3)] items-center justify-center rounded-xl bg-white p-2 shadow-[0_1px_0_rgb(31_27_22/.06),0_4px_8px_-4px_rgb(31_27_22/.22)] sm:w-[calc((100%-2.25rem)/4)] sm:p-3 lg:h-[clamp(4rem,11dvh,7rem)] lg:w-[calc((100%-5rem)/6)] lg:p-4">
            @if ($logo)
              <img src="/images/teams/{{ $logo }}" alt="{{ $name }}" decoding="async" fetchpriority="low"
                   class="max-h-full max-w-full object-contain transition-transform duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] group-hover:scale-105 motion-reduce:transition-none motion-reduce:group-hover:scale-100">
            @else
              <span role="img" aria-label="{{ $name }}" class="flex items-center gap-[0.3em] font-heading text-[1.1rem] leading-none transition-transform duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] group-hover:scale-105 motion-reduce:transition-none motion-reduce:group-hover:scale-100 sm:text-2xl lg:text-[clamp(1.5rem,2.7vw,2.25rem)]">
                <span class="text-base-content">CSE</span>
                <span class="h-[0.95em] w-[0.09em] rounded-full bg-primary"></span>
                <span class="text-primary">{{ substr($name, 4) }}</span>
              </span>
            @endif
          </li>
        @endforeach
      </ul>
    </x-PageContainer>
  </div>
</section>
