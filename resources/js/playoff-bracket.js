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
        const draw = () => {
            const bounds = bracket.getBoundingClientRect();
            const scale = window.devicePixelRatio || 1;
            canvas.width = Math.round(bounds.width * scale);
            canvas.height = Math.round(bounds.height * scale);
            context.scale(scale, scale);
            context.strokeStyle = getComputedStyle(canvas).color;
            context.lineWidth = 1.5;
            context.beginPath();

            // The card inside a slot (a champion badge may sit above it).
            const cardOf = slot => slot.querySelector('.tc-match, .tc-empty-slot') ?? slot.firstElementChild;
            // Vertical centre of an element, relative to the bracket.
            const middle = box => box.top + box.height / 2 - bounds.top;

            for (const target of slots) {
                const to = cardOf(target).getBoundingClientRect();
                const sources = target.dataset.sources.split(' ').filter(Boolean);
                const entersFinal = target.closest('[data-stage]').dataset.stage === 'F';
                for (const [index, id] of sources.entries()) {
                    const source = matches.get(id);
                    if (!source) continue;
                    const sourceCard = cardOf(source);
                    const from = sourceCard.getBoundingClientRect();
                    const leftToRight = from.left < to.left;
                    const start = leftToRight ? from.right : from.left;
                    const end = !leftToRight ? to.right : to.left;
                    // Start at the winner's row once a winner is published, else the card middle.
                    const winnerRow = sourceCard.querySelector('.tc-team-winner');
                    const startY = middle((winnerRow ?? sourceCard).getBoundingClientRect());
                    // End at the row where that team appears in the next card, when known.
                    const landingRow = cardOf(target).querySelector(`.tc-team[data-from="${id}"]`);
                    const endFraction = entersFinal || sources.length === 1 ? .5 : (index + 1) / 3;
                    let endY = landingRow ? middle(landingRow.getBoundingClientRect()) : to.top + to.height * endFraction - bounds.top;
                    // Into the Final: run straight across from the semi-final winner, on
                    // both halves, as long as that height still meets the Final card.
                    if (entersFinal && startY > to.top - bounds.top + 8 && startY < to.bottom - bounds.top - 8) {
                        endY = startY;
                    }
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
        };
        // Redraw on size changes and also on load, when the tab becomes visible
        // and after logos load: a ResizeObserver alone never fires if the page
        // first renders while hidden, which left the canvas blank.
        const observer = new ResizeObserver(draw);
        observer.observe(bracket);
        window.addEventListener('load', draw);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) draw(); });
        for (const image of bracket.querySelectorAll('img')) image.addEventListener('load', draw, { once: true });
        requestAnimationFrame(draw);
        for (const slot of slots) observer.observe(slot);
    }
}
