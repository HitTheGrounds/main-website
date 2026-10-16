import * as THREE from 'three';
import { RoomEnvironment } from 'three/examples/jsm/environments/RoomEnvironment.js';
import { RoundedBoxGeometry } from 'three/examples/jsm/geometries/RoundedBoxGeometry.js';

const root = document.querySelector('[data-podium]');
const mount = root && root.querySelector('[data-stage3d]');

function webglAvailable() {
    try {
        const c = document.createElement('canvas');
        return !!(window.WebGLRenderingContext && (c.getContext('webgl2') || c.getContext('webgl')));
    } catch (e) {
        return false;
    }
}

const TAU = Math.PI * 2;
const R = 5.4;                 // ring radius
const DELAY = 7000;            // autoplay, ms
const wrap = (a) => Math.atan2(Math.sin(a), Math.cos(a));

function radialTexture(stops, size = 256) {
    const c = document.createElement('canvas');
    c.width = c.height = size;
    const g = c.getContext('2d');
    const grad = g.createRadialGradient(size / 2, size / 2, 0, size / 2, size / 2, size / 2);
    stops.forEach(([o, col]) => grad.addColorStop(o, col));
    g.fillStyle = grad;
    g.fillRect(0, 0, size, size);
    const t = new THREE.CanvasTexture(c);
    t.colorSpace = THREE.SRGBColorSpace;
    return t;
}

const imgCache = new Map();
function loadImage(src) {
    if (!imgCache.has(src)) {
        imgCache.set(src, new Promise((res, rej) => {
            const i = new Image();
            i.onload = () => res(i);
            i.onerror = rej;
            i.src = src;
        }));
    }
    return imgCache.get(src);
}

function roundRect(g, x, y, w, h, r) {
    g.beginPath();
    g.moveTo(x + r, y);
    g.arcTo(x + w, y, x + w, y + h, r);
    g.arcTo(x + w, y + h, x, y + h, r);
    g.arcTo(x, y + h, x, y, r);
    g.arcTo(x, y, x + w, y, r);
    g.closePath();
}

async function logoCardTexture(src, dark) {
    const c = document.createElement('canvas');
    c.width = 1024;
    c.height = 683;
    const g = c.getContext('2d');
    const grad = g.createLinearGradient(0, 0, 1024, 683);
    if (dark) { grad.addColorStop(0, '#2A3860'); grad.addColorStop(1, '#1F2A44'); }
    else { grad.addColorStop(0, '#FFFFFF'); grad.addColorStop(1, '#E9E4DA'); }
    g.fillStyle = grad;
    g.fillRect(0, 0, 1024, 683);
    try {
        const img = await loadImage(src);
        const maxW = 1024 * 0.8, maxH = 683 * 0.7;
        const k = Math.min(maxW / img.naturalWidth, maxH / img.naturalHeight);
        const w = img.naturalWidth * k, h = img.naturalHeight * k;
        g.drawImage(img, (1024 - w) / 2, (683 - h) / 2, w, h);
    } catch (e) { /* leave the plain card */ }
    const t = new THREE.CanvasTexture(c);
    t.colorSpace = THREE.SRGBColorSpace;
    t.anisotropy = 8;
    return t;
}

function labelTexture(kicker, name, gold) {
    const c = document.createElement('canvas');
    c.width = 512;
    c.height = 256;
    const g = c.getContext('2d');
    g.textAlign = 'center';
    g.textBaseline = 'middle';
    g.letterSpacing = '7px';
    g.font = `800 ${gold ? 44 : 38}px "Plus Jakarta Sans", system-ui, sans-serif`;
    if (gold) {
        const grad = g.createLinearGradient(0, 70, 0, 130);
        grad.addColorStop(0, '#FFE9A8'); grad.addColorStop(1, '#E8A317');
        g.fillStyle = grad;
    } else g.fillStyle = '#D9D2C6';
    g.fillText(kicker, 256, 92);
    g.letterSpacing = '0px';
    g.font = `600 ${name.length > 14 ? 40 : 52}px "Plus Jakarta Sans", system-ui, sans-serif`;
    g.fillStyle = 'rgba(244,241,234,0.9)';
    g.fillText(name, 256, 168);
    const t = new THREE.CanvasTexture(c);
    t.colorSpace = THREE.SRGBColorSpace;
    t.anisotropy = 8;
    return t;
}

function yearTexture(year) {
    const c = document.createElement('canvas');
    c.width = 512;
    c.height = 128;
    const g = c.getContext('2d');
    g.textAlign = 'center';
    g.textBaseline = 'middle';
    g.font = '800 96px "Plus Jakarta Sans", system-ui, sans-serif';
    const grad = g.createLinearGradient(0, 20, 0, 110);
    grad.addColorStop(0, '#FFE9A8'); grad.addColorStop(0.55, '#E8A317'); grad.addColorStop(1, '#9A6208');
    g.fillStyle = grad;
    g.fillText(String(year), 256, 68);
    const t = new THREE.CanvasTexture(c);
    t.colorSpace = THREE.SRGBColorSpace;
    t.anisotropy = 8;
    return t;
}

async function init() {
    if (!root || !mount || !webglAvailable() || (mount.closest('[data-mobile-only]') && matchMedia('(min-width: 1024px)').matches)) return;
    const seasons = JSON.parse(root.dataset.seasons || '[]');
    if (!seasons.length) return;

    const reduceMotion = matchMedia('(prefers-reduced-motion: reduce)').matches;
    try { await document.fonts.load('800 80px "Plus Jakarta Sans"'); } catch (e) { /* system font */ }

    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'high-performance' });
    renderer.setPixelRatio(Math.min(devicePixelRatio, 2));
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.0;
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = THREE.PCFSoftShadowMap;
    renderer.setClearColor(0x000000, 0);
    const canvas = renderer.domElement;
    canvas.className = 'absolute inset-0 h-full w-full cursor-grab touch-pan-y opacity-0 transition-opacity duration-1000';
    canvas.setAttribute('aria-hidden', 'true');
    mount.appendChild(canvas);

    const scene = new THREE.Scene();
    scene.fog = new THREE.Fog(0x0b0907, 17, 34);
    const pmrem = new THREE.PMREMGenerator(renderer);
    scene.environment = pmrem.fromScene(new RoomEnvironment(), 0.04).texture;
    scene.environmentIntensity = 0.5;

    const camera = new THREE.PerspectiveCamera(30, 1, 0.1, 80);
    const lookAt = new THREE.Vector3(0, 0.75, 3.1);

    // lights: one spot on the front of the stage, warm rims, so each podium lights up as it arrives
    const key = new THREE.SpotLight(0xffe6c4, 340, 50, Math.PI / 5.4, 0.7, 1.0);
    key.position.set(0.5, 11, 12.5);
    key.target.position.set(0, 0.6, R);
    key.castShadow = true;
    key.shadow.mapSize.set(1536, 1536);
    key.shadow.bias = -0.0004;
    key.shadow.radius = 5;
    scene.add(key, key.target);
    const warm = new THREE.PointLight(0xff7a2b, 40, 30, 1.6);
    warm.position.set(-8, 3.5, 2);
    scene.add(warm);
    const cool = new THREE.PointLight(0xbcd2ff, 16, 26, 1.8);
    cool.position.set(8.5, 3, 6);
    scene.add(cool);
    scene.add(new THREE.HemisphereLight(0xffe9d2, 0x120c08, 0.3));

    // ---- stage floor (fixed) ----
    const floor = new THREE.Mesh(new THREE.CircleGeometry(24, 96), new THREE.MeshStandardMaterial({ color: 0x0d0a08, roughness: 0.4, metalness: 0.5 }));
    floor.rotation.x = -Math.PI / 2;
    floor.position.y = -0.3;
    floor.receiveShadow = true;
    scene.add(floor);

    // ---- turntable (rotates) ----
    const table = new THREE.Group();
    scene.add(table);
    const gold = new THREE.MeshStandardMaterial({ color: 0xd9a21b, metalness: 1, roughness: 0.26, envMapIntensity: 1.3 });
    const goldBright = new THREE.MeshStandardMaterial({ color: 0xf5c542, metalness: 1, roughness: 0.16, envMapIntensity: 1.4 });
    const stone = new THREE.MeshPhysicalMaterial({ color: 0x17120f, roughness: 0.22, metalness: 0.2, clearcoat: 1, clearcoatRoughness: 0.12, envMapIntensity: 0.9 });
    const slab = new THREE.MeshPhysicalMaterial({ color: 0x0f0c0a, roughness: 0.16, metalness: 0.35, clearcoat: 1, clearcoatRoughness: 0.06, envMapIntensity: 0.8 });
    const deckMat = new THREE.MeshPhysicalMaterial({ color: 0x1b1511, roughness: 0.3, metalness: 0.55, clearcoat: 0.6, clearcoatRoughness: 0.2, envMapIntensity: 0.9 });
    const glowMat = new THREE.MeshStandardMaterial({ color: 0xffa24a, emissive: 0xff7a1f, emissiveIntensity: 1.7, metalness: 0.6, roughness: 0.3 });

    const deck = new THREE.Mesh(new THREE.CylinderGeometry(R + 2.6, R + 2.8, 0.3, 160), deckMat);
    deck.position.y = -0.15;
    deck.castShadow = deck.receiveShadow = true;
    table.add(deck);
    const rimGold = new THREE.Mesh(new THREE.TorusGeometry(R + 2.7, 0.07, 14, 220), gold);
    rimGold.rotation.x = Math.PI / 2;
    rimGold.position.y = 0.0;
    table.add(rimGold);
    [R + 1.7, R - 1.75].forEach((r) => {
        const ring = new THREE.Mesh(new THREE.TorusGeometry(r, 0.018, 10, 240), glowMat);
        ring.rotation.x = Math.PI / 2;
        ring.position.y = 0.012;
        table.add(ring);
    });
    // central hub
    const hub = new THREE.Mesh(new THREE.CylinderGeometry(1.4, 1.65, 0.42, 72), stone);
    hub.position.y = 0.21;
    hub.castShadow = hub.receiveShadow = true;
    table.add(hub);
    const hubTop = new THREE.Mesh(new THREE.CylinderGeometry(1.2, 1.2, 0.06, 72), goldBright);
    hubTop.position.y = 0.45;
    table.add(hubTop);
    const hubGlow = new THREE.Mesh(new THREE.TorusGeometry(1.0, 0.025, 10, 100), glowMat);
    hubGlow.rotation.x = Math.PI / 2;
    hubGlow.position.y = 0.5;
    table.add(hubGlow);
    // radial spokes toward each podium
    seasons.forEach((s, i) => {
        const a = (i / seasons.length) * TAU;
        const spoke = new THREE.Mesh(new THREE.BoxGeometry(0.05, 0.02, R - 2.4), glowMat);
        spoke.position.set(Math.sin(a) * (R / 2 + 0.75), 0.012, Math.cos(a) * (R / 2 + 0.75));
        spoke.rotation.y = a;
        table.add(spoke);
    });

    const shadowTex = radialTexture([[0, 'rgba(0,0,0,0.65)'], [0.6, 'rgba(0,0,0,0.25)'], [1, 'rgba(0,0,0,0)']]);
    const crownTex = new THREE.TextureLoader().load('/images/crown.avif', (t) => { t.colorSpace = THREE.SRGBColorSpace; t.anisotropy = 8; });

    // ---- one podium set per season ----
    const sets = [];
    seasons.forEach((s, i) => {
        const a = (i / seasons.length) * TAU;
        const set = new THREE.Group();
        set.position.set(Math.sin(a) * R, 0, Math.cos(a) * R);
        set.rotation.y = a;                   // local +z points outward, toward the camera when this season is in front
        table.add(set);

        const blocks = [
            { x: -1.15, h: 0.95, w: 1.5, champ: false, name: s.runner, logo: s.runnerLogo, dark: s.runnerDark, kicker: '1ST RUNNER UP' },
            { x: 0.95, h: 1.45, w: 1.6, champ: true, name: s.champion, logo: s.championLogo, dark: s.championDark, kicker: 'CHAMPION' },
        ];
        blocks.forEach((b) => {
            const body = new THREE.Mesh(new RoundedBoxGeometry(b.w, b.h, b.w, 5, 0.035), stone);
            body.position.set(b.x, b.h / 2, 0);
            body.castShadow = body.receiveShadow = true;
            set.add(body);
            const plinth = new THREE.Mesh(new RoundedBoxGeometry(b.w + 0.16, 0.12, b.w + 0.16, 4, 0.04), gold);
            plinth.position.set(b.x, 0.06, 0);
            plinth.castShadow = plinth.receiveShadow = true;
            set.add(plinth);
            const cap = new THREE.Mesh(new RoundedBoxGeometry(b.w + 0.1, 0.09, b.w + 0.1, 4, 0.04), slab);
            cap.position.set(b.x, b.h + 0.01, 0);
            cap.castShadow = cap.receiveShadow = true;
            set.add(cap);
            const half = (b.w + 0.1) / 2;
            [[0, half, b.w + 0.1, 0.035], [0, -half, b.w + 0.1, 0.035], [half, 0, 0.035, b.w + 0.1], [-half, 0, 0.035, b.w + 0.1]].forEach(([fx, fz, fw, fd]) => {
                const edge = new THREE.Mesh(new RoundedBoxGeometry(fw, 0.05, fd, 2, 0.012), b.champ ? goldBright : gold);
                edge.position.set(b.x + fx, b.h + 0.045, fz);
                edge.castShadow = true;
                set.add(edge);
            });
            const lab = new THREE.Mesh(
                new THREE.PlaneGeometry(b.w * 0.86, b.w * 0.43),
                new THREE.MeshStandardMaterial({ map: labelTexture(b.kicker, b.name, b.champ), transparent: true, emissive: 0x2a1a05, emissiveMap: null, roughness: 0.5, metalness: 0.3, depthWrite: false }),
            );
            lab.position.set(b.x, b.h * 0.55, b.w / 2 + 0.004);
            set.add(lab);
            if (b.champ) {
                const glowTop = new THREE.Mesh(
                    new THREE.PlaneGeometry(b.w * 1.3, b.w * 1.3),
                    new THREE.MeshBasicMaterial({ map: radialTexture([[0, 'rgba(255,255,255,0.55)'], [1, 'rgba(255,255,255,0)']]), color: 0xffb347, transparent: true, opacity: 0.45, depthWrite: false, blending: THREE.AdditiveBlending }),
                );
                glowTop.rotation.x = -Math.PI / 2;
                glowTop.position.set(b.x, b.h + 0.07, 0);
                set.add(glowTop);
            }
            // contact shadow
            const sh = new THREE.Mesh(new THREE.PlaneGeometry(b.w * 1.05, b.w * 1.05), new THREE.MeshBasicMaterial({ map: shadowTex, transparent: true, depthWrite: false }));
            sh.rotation.x = -Math.PI / 2;
            sh.position.set(b.x, 0.01, 0);
            set.add(sh);

            // the logo card, tilted, floating above the block (a real thin slab, visible from behind too)
            const cw = b.champ ? 1.8 : 1.5, ch = cw * 2 / 3;
            const edgeM = new THREE.MeshStandardMaterial({ color: 0xe9e4da, roughness: 0.4, metalness: 0.05 });
            const backM = new THREE.MeshStandardMaterial({ color: 0x1a1612, roughness: 0.5, metalness: 0.4 });
            const faceM = new THREE.MeshBasicMaterial({ color: 0xffffff, toneMapped: false });
            const card = new THREE.Mesh(new RoundedBoxGeometry(cw, ch, 0.07, 4, 0.05), [edgeM, edgeM, edgeM, edgeM, faceM, faceM]);
            card.position.set(b.x, b.h + (b.champ ? 0.95 : 0.78), 0);
            card.rotation.z = b.champ ? -0.1 : 0.12;
            card.rotation.y = b.champ ? 0.05 : -0.05;
            card.castShadow = true;
            card.userData.bob = { base: card.position.y, phase: i * 1.3 + (b.champ ? 0 : 2), champ: b.champ };
            set.add(card);
            logoCardTexture(b.logo, b.dark).then((tex) => {
                faceM.map = tex;
                faceM.needsUpdate = true;
            });
            b.card = card;
            if (b.champ) {
                const crown = new THREE.Sprite(new THREE.SpriteMaterial({ map: crownTex, transparent: true, depthWrite: false }));
                crown.scale.set(0.7, 0.8, 1);
                crown.position.set(0, ch / 2 + 0.22, 0.05);
                card.add(crown);
            }
        });

        // year plate on a low step in front of the podiums
        const step = new THREE.Mesh(new RoundedBoxGeometry(3.7, 0.36, 0.7, 4, 0.04), stone);
        step.position.set(0, 0.18, 1.7);
        step.castShadow = step.receiveShadow = true;
        set.add(step);
        const stepEdge = new THREE.Mesh(new RoundedBoxGeometry(3.74, 0.03, 0.74, 2, 0.01), gold);
        stepEdge.position.set(0, 0.365, 1.7);
        set.add(stepEdge);
        const yr = new THREE.Mesh(new THREE.PlaneGeometry(2.1, 0.525), new THREE.MeshBasicMaterial({ map: yearTexture(s.year), transparent: true, depthWrite: false, toneMapped: false }));
        yr.position.set(0, 0.18, 2.056);
        set.add(yr);

        sets.push(set);
    });

    // ---- spotlight cone + dust ----
    const cone = new THREE.Mesh(
        new THREE.ConeGeometry(3.4, 10, 72, 1, true),
        new THREE.ShaderMaterial({
            transparent: true, depthWrite: false, blending: THREE.AdditiveBlending, side: THREE.DoubleSide,
            uniforms: { uColor: { value: new THREE.Color(0xffb878) } },
            vertexShader: 'varying float vY; varying vec3 vN; varying vec3 vV; void main(){ vY = position.y; vec4 mv = modelViewMatrix * vec4(position, 1.0); vN = normalize(normalMatrix * normal); vV = normalize(-mv.xyz); gl_Position = projectionMatrix * mv; }',
            fragmentShader: 'uniform vec3 uColor; varying float vY; varying vec3 vN; varying vec3 vV; void main(){ float h = clamp((vY + 5.0) / 10.0, 0.0, 1.0); float e = pow(abs(dot(normalize(vN), normalize(vV))), 1.6); gl_FragColor = vec4(uColor, (0.015 + 0.16 * h * h) * e); }',
        }),
    );
    cone.position.set(0, 5.2, R);
    scene.add(cone);

    const N = 220;
    const pos = new Float32Array(N * 3), seed = new Float32Array(N);
    for (let i = 0; i < N; i++) {
        const a = Math.random() * TAU, r = Math.random() * (R + 3);
        pos.set([Math.cos(a) * r, Math.random() * 6, Math.sin(a) * r + 0.0], i * 3);
        seed[i] = Math.random() * 10;
    }
    const dustGeo = new THREE.BufferGeometry();
    dustGeo.setAttribute('position', new THREE.BufferAttribute(pos, 3));
    scene.add(new THREE.Points(dustGeo, new THREE.PointsMaterial({
        size: 0.08, map: radialTexture([[0, 'rgba(255,225,150,1)'], [1, 'rgba(255,200,100,0)']], 64), color: 0xffd58a,
        transparent: true, opacity: 0.8, depthWrite: false, blending: THREE.AdditiveBlending,
    })));

    // ---- HTML controls (shared with the markup) ----
    const tabs = [...root.querySelectorAll('[data-tab]')];
    const bar = root.querySelector('[data-progress]');
    const overlayYear = root.querySelector('[data-overlay-year]');
    const overlayBadge = root.querySelector('[data-overlay-badge]');
    const step = TAU / seasons.length;
    let active = 0;
    let target = 0;       // table angle we are heading to
    let angle = 0;        // current table angle
    let spinVel = 0;
    let dragging = false, held = false, lastX = 0, px = 0, py = 0;
    let timer = null;

    const setActive = (n) => {
        if (n === active) return;
        active = n;
        tabs.forEach((t, k) => { t.classList.toggle('is-active', k === n); t.setAttribute('aria-selected', k === n); });
        if (overlayYear) overlayYear.textContent = seasons[n].year;
        if (overlayBadge) overlayBadge.classList.toggle('hidden', n !== 0);
    };
    const restart = () => {
        clearTimeout(timer);
        if (!bar) return;
        bar.style.animation = 'none'; void bar.offsetWidth;
        if (reduceMotion || held || dragging) return;
        bar.style.animation = `ps-fill ${DELAY}ms linear forwards`;
        timer = setTimeout(() => goTo(active + 1, 1), DELAY);
    };
    function goTo(n, dir = 0) {
        const idx = ((n % seasons.length) + seasons.length) % seasons.length;
        // table angle that puts season idx in front; take the shortest turn from where we are
        const wanted = -idx * step;
        let d = wrap(wanted - angle);
        if (dir > 0 && d > 0.001) d -= TAU;
        if (dir < 0 && d < -0.001) d += TAU;
        target = angle + d;
        setActive(idx);
        restart();
    }

    root.querySelector('[data-next]')?.addEventListener('click', () => goTo(active + 1, 1));
    root.querySelector('[data-prev]')?.addEventListener('click', () => goTo(active - 1, -1));
    tabs.forEach((t, k) => t.addEventListener('click', () => goTo(k, 0)));
    root.addEventListener('mouseenter', () => { held = true; clearTimeout(timer); if (bar) bar.style.animation = 'none'; });
    root.addEventListener('mouseleave', () => { held = false; restart(); });
    root.addEventListener('focusin', () => { held = true; clearTimeout(timer); if (bar) bar.style.animation = 'none'; });
    root.addEventListener('focusout', () => { held = false; restart(); });

    canvas.addEventListener('pointerdown', (e) => { dragging = true; lastX = e.clientX; spinVel = 0; canvas.setPointerCapture(e.pointerId); canvas.classList.replace('cursor-grab', 'cursor-grabbing'); clearTimeout(timer); });
    canvas.addEventListener('pointermove', (e) => {
        const r = canvas.getBoundingClientRect();
        px = ((e.clientX - r.left) / r.width - 0.5) * 2;
        py = ((e.clientY - r.top) / r.height - 0.5) * 2;
        if (!dragging) return;
        const dx = e.clientX - lastX;
        lastX = e.clientX;
        angle += dx * 0.0075;
        target = angle;
        spinVel = dx * 0.0075;
        const near = Math.round(-angle / step);
        setActive(((near % seasons.length) + seasons.length) % seasons.length);
    });
    const release = () => {
        if (!dragging) return;
        dragging = false;
        canvas.classList.replace('cursor-grabbing', 'cursor-grab');
        // fling a little, then settle on the nearest season
        const settle = Math.round((-(angle + spinVel * 14)) / step);
        goTo(((settle % seasons.length) + seasons.length) % seasons.length, 0);
    };
    canvas.addEventListener('pointerup', release);
    canvas.addEventListener('pointercancel', release);

    // ---- sizing and loop ----
    function resize() {
        const w = mount.clientWidth || 1, h = mount.clientHeight || 1;
        renderer.setSize(w, h, false);
        camera.aspect = w / h;
        const dist = 15.5 * Math.pow(1.5 / Math.min(camera.aspect, 1.5), 0.5);
        camera.position.set(0, 5.0, R + dist * 0.7);
        camera.lookAt(lookAt);
        camera.updateProjectionMatrix();
    }
    new ResizeObserver(resize).observe(mount);
    resize();

    const clock = new THREE.Clock();
    let visible = true, raf = 0;
    function frame() {
        const dt = Math.min(clock.getDelta(), 0.05);
        const t = clock.elapsedTime;
        if (!dragging) {
            if (reduceMotion) angle = target;
            else angle += (target - angle) * Math.min(1, dt * 2.6);
        }
        table.rotation.y = angle;

        camera.position.x += (px * 1.1 - camera.position.x) * 0.04;
        camera.position.y += (5.0 - py * 0.5 - camera.position.y) * 0.04;
        camera.lookAt(lookAt);

        sets.forEach((set) => set.children.forEach((c) => {
            const bob = c.userData && c.userData.bob;
            if (bob && !reduceMotion) c.position.y = bob.base + Math.sin(t * 1.2 + bob.phase) * (bob.champ ? 0.05 : 0.04);
        }));
        const a = dustGeo.attributes.position;
        for (let i = 0; i < N; i++) {
            let y = a.getY(i) + dt * (0.12 + (seed[i] % 1) * 0.16);
            if (y > 6.2) y = 0.1;
            a.setY(i, y);
        }
        a.needsUpdate = true;
        renderer.render(scene, camera);
        raf = (visible && !document.hidden) ? requestAnimationFrame(frame) : 0;
    }
    const start = () => { if (!raf) { clock.getDelta(); raf = requestAnimationFrame(frame); } };
    new IntersectionObserver(([e]) => { visible = e.isIntersecting; if (visible) start(); }, { threshold: 0.05 }).observe(mount);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) start(); });

    root.classList.add('is-3d');
    root.dataset.ready3d = '1';
    frame();
    start();
    restart();
    requestAnimationFrame(() => canvas.classList.remove('opacity-0'));
}

init();
