import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

// Deterministic pseudo-random so the pile looks the same after every resize/refresh.
const rand = (i) => {
    const x = Math.sin(i * 12.9898 + 78.233) * 43758.5453;
    return x - Math.floor(x);
};

const mm = gsap.matchMedia();

mm.add(
    {
        desktop: '(min-width: 1024px)',
        mobile: '(max-width: 1023.98px)',
        reduce: '(prefers-reduced-motion: reduce)',
    },
    (ctx) => {
        const { desktop, reduce } = ctx.conditions;
        if (reduce) return; // static grid

        // Two copies live in the page (one per breakpoint); animate the visible one.
        const root = document.querySelector(`[data-teams="${desktop ? 'desktop' : 'mobile'}"]`);
        if (!root) return;

        if (root.dataset.teamsDone) return; // already played once: stay a plain grid

        const pin = root.querySelector('[data-teams-pin]');
        const field = root.querySelector('[data-teams-field]');
        const tiles = gsap.utils.toArray('[data-team]', field);
        if (!pin || !field || !tiles.length) return;

        // Play once: when the visitor scrolls past the finished grid, drop the pin for good
        // so scrolling back up never replays it. Removing the pin spacer shortens the page
        // above the viewport, so scroll up by the same distance to keep everything in place.
        let tl;
        const finish = (st) => {
            if (!tl || root.dataset.teamsDone) return;
            root.dataset.teamsDone = '1';
            const distance = st.end - st.start;
            const y = window.scrollY - distance;
            tl.progress(1);
            st.kill(true);
            tl.kill();
            gsap.set(tiles, { clearProps: 'transform' });
            window.scrollTo({ top: Math.max(0, y), behavior: 'instant' });
            ScrollTrigger.refresh();
        };

        tl = gsap.timeline({
            scrollTrigger: {
                trigger: root,
                start: 'top top',
                end: desktop ? '+=140%' : '+=120%',
                pin,
                scrub: 0.8,
                anticipatePin: 1,
                invalidateOnRefresh: true,
                onLeave: finish,
                // opened or reloaded already scrolled below the section
                onRefresh: (st) => {
                    if (st.scroll() > st.end) finish(st);
                },
            },
        });

        tl.to({}, { duration: 0.18 }) // let the stack sit for a beat
            .fromTo(
                tiles,
                {
                    // layout position (offsetLeft/Top ignore transforms) -> centre of the field
                    x: (i, el) => field.offsetWidth / 2 - (el.offsetLeft + el.offsetWidth / 2) + (rand(i) - 0.5) * 14,
                    y: (i, el) => field.offsetHeight / 2 - (el.offsetTop + el.offsetHeight / 2) + (rand(i + 7) - 0.5) * 12 - i * 0.5,
                    rotation: (i) => (rand(i + 3) - 0.5) * 24,
                    scale: desktop ? 2 : 1.7, // the pile is bigger than the grid tiles, so logos read clearly
                },
                {
                    x: 0,
                    y: 0,
                    rotation: 0,
                    scale: 1,
                    ease: 'power3.inOut',
                    duration: 0.7,
                    stagger: { each: 0.045, from: 'end' }, // top of the pile leaves first
                },
            )
            .to({}, { duration: 0.3 }); // hold the finished grid before unpinning

        // reloaded while already scrolled below the section
        if (tl.scrollTrigger.scroll() > tl.scrollTrigger.end) finish(tl.scrollTrigger);

        return () => gsap.set(tiles, { clearProps: 'transform' });
    },
);

window.addEventListener('load', () => ScrollTrigger.refresh());
