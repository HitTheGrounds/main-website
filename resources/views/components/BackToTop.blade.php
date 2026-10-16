{{-- Back to top: a round button whose ring fills as you scroll down the page. --}}
<button type="button" id="back-to-top" aria-label="Back to top" tabindex="-1"
        class="group fixed bottom-6 right-4 z-40 grid size-14 translate-y-4 place-items-center rounded-full bg-secondary text-primary opacity-0 pointer-events-none shadow-[0_14px_30px_-10px_rgba(31,27,22,0.55)] transition duration-300 ease-out data-[show]:translate-y-0 data-[show]:opacity-100 data-[show]:pointer-events-auto hover:-translate-y-0.5 active:scale-95 lg:bottom-8 lg:right-8">
    <svg class="absolute inset-0 -rotate-90" viewBox="0 0 56 56" fill="none" aria-hidden="true">
        <circle cx="28" cy="28" r="25" stroke="currentColor" stroke-opacity=".2" stroke-width="3"/>
        <circle id="back-to-top-ring" cx="28" cy="28" r="25" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-dasharray="157.08" stroke-dashoffset="157.08"/>
    </svg>
    <svg class="relative size-5 transition-transform duration-300 group-hover:-translate-y-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
</button>
<script>
    (() => {
        const btn = document.getElementById('back-to-top');
        const ring = document.getElementById('back-to-top-ring');
        const C = 157.08;
        let ticking = false;
        const update = () => {
            ticking = false;
            const max = document.documentElement.scrollHeight - innerHeight;
            const shown = scrollY > 600;
            btn.toggleAttribute('data-show', shown);
            btn.tabIndex = shown ? 0 : -1;
            ring.style.strokeDashoffset = C * (1 - Math.min(1, max > 0 ? scrollY / max : 0));
        };
        addEventListener('scroll', () => { if (!ticking) { ticking = true; requestAnimationFrame(update); } }, { passive: true });
        btn.addEventListener('click', () => scrollTo({ top: 0, behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' }));
        update();
    })();
</script>
