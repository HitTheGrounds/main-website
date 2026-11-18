const bracket = document.querySelector('[data-playoff-bracket]');

if (bracket) {
    const canvas = bracket.querySelector('canvas');
    const context = canvas.getContext('2d');
    const slots = [...bracket.querySelectorAll('[data-match]')];
    const matches = new Map(slots.filter(slot => slot.dataset.match).map(slot => [slot.dataset.match, slot]));
    const scrollRegion = bracket.parentElement;
    const updateScrollFocus = () => {
        if (scrollRegion.scrollWidth > scrollRegion.clientWidth) {
            scrollRegion.tabIndex = 0;
        } else {
            scrollRegion.removeAttribute('tabindex');
        }
    };
    const scrollObserver = new ResizeObserver(updateScrollFocus);
    scrollObserver.observe(scrollRegion);
    scrollObserver.observe(bracket);
    updateScrollFocus();

    for (const logo of bracket.querySelectorAll('.tc-team-logo img')) {
        const hide = () => { logo.hidden = true; };
        logo.addEventListener('error', hide, { once: true });
        if (logo.complete && !logo.naturalWidth) hide();
    }

    if (context) {
        const observer = new ResizeObserver(() => {
            const bounds = bracket.getBoundingClientRect();
            const scale = window.devicePixelRatio || 1;
            canvas.width = Math.round(bounds.width * scale);
            canvas.height = Math.round(bounds.height * scale);
            context.scale(scale, scale);
            context.strokeStyle = getComputedStyle(canvas).color;
            context.lineWidth = 1.5;
            context.beginPath();

            for (const target of slots) {
                const to = target.firstElementChild.getBoundingClientRect();
                const sources = target.dataset.sources.split(' ').filter(Boolean);
                const entersFinal = target.closest('[data-stage]').dataset.stage === 'F';
                for (const [index, id] of sources.entries()) {
                    const source = matches.get(id);
                    if (!source) continue;
                    const from = source.firstElementChild.getBoundingClientRect();
                    const leftToRight = from.left < to.left;
                    const start = leftToRight ? from.right : from.left;
                    const end = !leftToRight ? to.right : to.left;
                    const startY = from.top + from.height / 2 - bounds.top;
                    const endFraction = entersFinal || sources.length === 1 ? .5 : (index + 1) / 3;
                    const endY = to.top + to.height * endFraction - bounds.top;
                    const lane = (start + end) / 2 - bounds.left;
                    const startX = start - bounds.left;
                    const endX = end - bounds.left;
                    const radius = Math.min(10, Math.abs(startX - lane), Math.abs(endX - lane), Math.abs(endY - startY) / 2);

                    context.moveTo(startX, startY);
                    context.arcTo(lane, startY, lane, endY, radius);
                    context.arcTo(lane, endY, endX, endY, radius);
                    context.lineTo(endX, endY);

                    const arrowBackX = endX + (leftToRight ? -7 : 7);
                    context.moveTo(arrowBackX, endY - 4);
                    context.lineTo(endX, endY);
                    context.lineTo(arrowBackX, endY + 4);
                }
            }

            context.stroke();
        });
        observer.observe(bracket);
        for (const slot of slots) observer.observe(slot);
    }
}
