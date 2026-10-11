@props(['member'])

<article class="card border border-base-300 bg-base-200 shadow-sm" data-aos="fade-up" data-aos-duration="1000">
    <div class="card-body items-center text-center">
        <img src="{{ $member['photo'] }}" alt="{{ $member['name'] }}" width="112" height="112" loading="lazy" class="mb-3 size-28 rounded-full object-cover ring ring-primary/30 ring-offset-4 ring-offset-base-200">
        <h4 class="card-title justify-center text-xl text-base-content">{{ $member['name'] }}</h4>
        <p class="text-sm leading-relaxed text-base-content/70">{{ $member['role'] }}</p>
        <div class="mt-3 flex w-full flex-col items-center gap-1.5 border-t border-base-300 pt-3 text-sm">
            <a href="mailto:{{ $member['email'] }}" class="break-all font-semibold text-base-content transition hover:text-primary-ink">{{ $member['email'] }}</a>
            <a href="tel:{{ preg_replace('/\s+/', '', $member['phone']) }}" class="font-semibold text-base-content/80 transition hover:text-primary-ink">{{ $member['phone'] }}</a>
        </div>
    </div>
</article>
