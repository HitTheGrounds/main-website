@props(['member'])
@php $phone = preg_replace('/\s+/', '', $member['phone']); @endphp

<article class="card border border-base-300 bg-base-200 shadow-sm" data-aos="fade-up" data-aos-duration="1000">
    <div class="card-body items-center p-2 text-center sm:p-6">
        <img src="{{ $member['photo'] }}" alt="{{ $member['name'] }}" width="112" height="112" loading="lazy" class="mb-1 size-14 rounded-full object-cover ring ring-primary/30 ring-offset-2 ring-offset-base-200 sm:mb-3 sm:size-28 sm:ring-offset-4">
        <h4 class="card-title justify-center text-xs leading-tight text-base-content sm:text-xl">{{ $member['name'] }}</h4>
        <p class="text-[10px] leading-snug text-base-content/70 sm:text-sm sm:leading-relaxed">{{ $member['role'] }}</p>

        {{-- Narrow cards: contact as tap icons --}}
        <div class="mt-1 flex gap-2 sm:hidden">
            <a href="mailto:{{ $member['email'] }}" aria-label="Email {{ $member['name'] }}" class="grid size-8 place-items-center rounded-full bg-base-100 text-base-content">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18v12H3zM3 7l9 6 9-6"/></svg>
            </a>
            <a href="tel:{{ $phone }}" aria-label="Call {{ $member['name'] }}" class="grid size-8 place-items-center rounded-full bg-base-100 text-base-content">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 005 5L15 13l5 2v4a2 2 0 01-2 2A16 16 0 013 6a2 2 0 012-2z"/></svg>
            </a>
        </div>

        <div class="mt-3 hidden w-full flex-col items-center gap-1.5 border-t border-base-300 pt-3 text-sm sm:flex">
            <a href="mailto:{{ $member['email'] }}" class="break-all font-semibold text-base-content transition hover:text-primary-ink">{{ $member['email'] }}</a>
            <a href="tel:{{ $phone }}" class="font-semibold text-base-content/80 transition hover:text-primary-ink">{{ $member['phone'] }}</a>
        </div>
    </div>
</article>
