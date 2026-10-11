<section class="relative overflow-hidden bg-base-200 py-24 md:py-32">
  <!-- Interactive dot-grid background -->
  <canvas id="beyond-bg" aria-hidden="true" class="absolute inset-0 h-full w-full"></canvas>

  <div class="relative mx-auto max-w-6xl px-4 sm:px-6">
    <h2 class="reveal font-heading text-[clamp(2.25rem,6vw,4rem)] text-base-content mb-14 md:mb-20">
      Beyond the <span class="text-primary">boundary.</span>
    </h2>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-16 gap-y-12">
      <div class="reveal border-t border-base-content pt-6">
        <h3 class="font-heading text-2xl sm:text-3xl text-base-content">Championship clash</h3>
        <p class="mt-3 max-w-[40ch] text-base sm:text-lg leading-relaxed text-base-content/75">
          Where university teams face off against industry teams in a thrilling battle for the title.
        </p>
      </div>

      <div class="reveal reveal-d1 border-t border-base-content pt-6">
        <h3 class="font-heading text-2xl sm:text-3xl text-base-content">The grand arena</h3>
        <p class="mt-3 max-w-[40ch] text-base sm:text-lg leading-relaxed text-base-content/75">
          Played on the iconic University of Moratuwa grounds, where every match carries pride and tradition.
        </p>
      </div>

      <div class="reveal border-t border-base-content pt-6">
        <h3 class="font-heading text-2xl sm:text-3xl text-base-content">Connections that count</h3>
        <p class="mt-3 max-w-[40ch] text-base sm:text-lg leading-relaxed text-base-content/75">
          Forge lasting ties with leaders, innovators, and the next generation of talent.
        </p>
      </div>

      <div class="reveal reveal-d1 border-t border-base-content pt-6">
        <h3 class="font-heading text-2xl sm:text-3xl text-base-content">Bridges through cricket</h3>
        <p class="mt-3 max-w-[40ch] text-base sm:text-lg leading-relaxed text-base-content/75">
          Bringing academia and industry together through the shared spirit and passion for the game.
        </p>
      </div>
    </div>
  </div>
</section>

<script>
(function () {
  const canvas = document.getElementById('beyond-bg');
  if (!canvas) return;
  const section = canvas.parentElement;
  const ctx = canvas.getContext('2d');
  const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const GAP = 30, RADIUS = 150;
  let dots = [], w = 0, h = 0, dpr = 1;
  const pointer = { x: -9999, y: -9999, active: false, last: 0 };
  let visible = true;

  function build() {
    dpr = Math.min(window.devicePixelRatio || 1, 2);
    w = section.clientWidth; h = section.clientHeight;
    canvas.width = w * dpr; canvas.height = h * dpr;
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    dots = [];
    for (let y = GAP / 2; y < h; y += GAP)
      for (let x = GAP / 2; x < w; x += GAP) dots.push({ x, y, ox: 0, oy: 0 });
    draw(0);
  }

  function draw(t) {
    ctx.clearRect(0, 0, w, h);
    // idle: a soft virtual ball drifts across the grid
    let px = pointer.x, py = pointer.y;
    if (!pointer.active || t - pointer.last > 1800) {
      px = w * (0.5 + 0.38 * Math.sin(t * 0.00031));
      py = h * (0.5 + 0.34 * Math.sin(t * 0.00047 + 1.3));
    }
    for (const d of dots) {
      const dx = d.x - px, dy = d.y - py;
      const dist = Math.hypot(dx, dy);
      const f = reduce ? 0 : Math.max(0, 1 - dist / RADIUS);
      const push = f * f * 16;
      const tx = dist ? (dx / dist) * push : 0, ty = dist ? (dy / dist) * push : 0;
      d.ox += (tx - d.ox) * 0.12; d.oy += (ty - d.oy) * 0.12;
      const r = 1.3 + f * 2.4;
      ctx.beginPath();
      ctx.arc(d.x + d.ox, d.y + d.oy, r, 0, 6.2832);
      ctx.fillStyle = f > 0.02
        ? `rgba(255,106,19,${0.25 + f * 0.7})`
        : 'rgba(31,27,22,0.16)';
      ctx.fill();
    }
  }

  function loop(t) {
    requestAnimationFrame(loop);
    if (visible && !document.hidden && !reduce) draw(t);
  }

  section.addEventListener('pointermove', (e) => {
    const r = section.getBoundingClientRect();
    pointer.x = e.clientX - r.left; pointer.y = e.clientY - r.top;
    pointer.active = true; pointer.last = performance.now();
  }, { passive: true });
  section.addEventListener('pointerleave', () => { pointer.active = false; });

  new ResizeObserver(build).observe(section);
  new IntersectionObserver(([e]) => { visible = e.isIntersecting; }).observe(section);
  build();
  requestAnimationFrame(loop);
})();
</script>
