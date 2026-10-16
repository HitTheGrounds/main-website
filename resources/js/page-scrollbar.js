// Only replace the page scrollbar once its native-scroll companion is ready.
let disposeScrollbar = () => {};

function initPageScrollbar() {
    disposeScrollbar();
    const rail = document.querySelector('[data-page-scrollbar]');
    if (!rail) return;

    const root = document.documentElement;
    const thumb = rail.querySelector('[data-scroll-thumb]');
    const progressLine = rail.querySelector('[data-scroll-progress]');
    const contrast = matchMedia('(forced-colors: active)');
    const events = new AbortController();
    let frame = 0;
    let drag = null;
    let range = 0;
    let travel = 0;

    function update() {
        frame = 0;
        range = Math.max(0, root.scrollHeight - root.clientHeight);
        const enabled = range > 1 && !contrast.matches;
        rail.hidden = !enabled;
        root.classList.toggle('has-page-scrollbar', enabled);
        if (!enabled) return;

        const height = rail.clientHeight;
        const thumbHeight = 16;
        // Stop at the bails so the ball strikes the wicket, leaving the stumps visible.
        travel = Math.max(0, height - thumbHeight - 18);
        const progress = Math.max(0, Math.min(1, window.scrollY / range));
        thumb.style.transform = `translateY(${progress * travel}px)`;
        thumb.firstElementChild.style.transform = `rotate(${progress * 720}deg)`;
        progressLine.style.height = `${progress * travel + thumbHeight / 2}px`;
        const isOut = range - window.scrollY <= 2;
        rail.classList.toggle('is-out', isOut);
        rail.setAttribute('aria-valuenow', Math.round(progress * 100));
        rail.setAttribute('aria-valuetext', isOut ? '100%, wicket! End of page' : `${Math.round(progress * 100)}% of page`);
    }

    function schedule() {
        if (!frame) frame = requestAnimationFrame(update);
    }

    function finishDrag() {
        drag = null;
        rail.classList.remove('is-dragging');
    }

    rail.addEventListener('pointerdown', (event) => {
        if (!event.isPrimary || event.button !== 0) return;
        update();
        const bounds = thumb.getBoundingClientRect();
        const offset = thumb.contains(event.target) ? event.clientY - bounds.top : bounds.height / 2;
        drag = { offset };
        rail.setPointerCapture(event.pointerId);
        rail.classList.add('is-dragging');
        rail.focus({ preventScroll: true });
        move(event);
        event.preventDefault();
    }, { signal: events.signal });

    function move(event) {
        if (!drag || !travel) return;
        const position = event.clientY - rail.getBoundingClientRect().top - drag.offset;
        window.scrollTo({ top: Math.max(0, Math.min(1, position / travel)) * range, behavior: 'instant' });
    }

    rail.addEventListener('pointermove', move, { signal: events.signal });
    rail.addEventListener('pointerup', finishDrag, { signal: events.signal });
    rail.addEventListener('pointercancel', finishDrag, { signal: events.signal });
    rail.addEventListener('lostpointercapture', finishDrag, { signal: events.signal });
    rail.addEventListener('keydown', (event) => {
        const steps = {
            ArrowDown: 40, ArrowUp: -40,
            PageDown: root.clientHeight * .9, PageUp: -root.clientHeight * .9,
            Home: -range, End: range,
            ' ': root.clientHeight * (event.shiftKey ? -.9 : .9),
        };
        if (!(event.key in steps)) return;
        event.preventDefault();
        window.scrollBy({ top: steps[event.key], behavior: 'instant' });
    }, { signal: events.signal });

    window.addEventListener('scroll', schedule, { passive: true, signal: events.signal });
    window.addEventListener('resize', schedule, { passive: true, signal: events.signal });
    document.addEventListener('load', schedule, { capture: true, signal: events.signal });
    contrast.addEventListener('change', schedule, { signal: events.signal });
    const resize = new ResizeObserver(schedule);
    resize.observe(document.body);
    resize.observe(root);
    update();

    disposeScrollbar = () => {
        events.abort();
        resize.disconnect();
        cancelAnimationFrame(frame);
        root.classList.remove('has-page-scrollbar');
        rail.hidden = true;
    };
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPageScrollbar, { once: true });
} else {
    initPageScrollbar();
}
document.addEventListener('livewire:navigated', initPageScrollbar);
