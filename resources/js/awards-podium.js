import * as THREE from 'three';
import { RoomEnvironment } from 'three/examples/jsm/environments/RoomEnvironment.js';
import { RoundedBoxGeometry } from 'three/examples/jsm/geometries/RoundedBoxGeometry.js';

const mount = document.getElementById('awards-podium');

// Podium blocks: x position, height, label. The trophy images are pre-rendered, so each one is a
// camera-facing card that stands on its block while the blocks turn underneath.
const BLOCKS = [
    { x: 0, h: 1.5, w: 1.3, num: '1', label: 'CHAMPION', img: '/award/champion.avif', visible: 1.75, pad: [0.063, 0.012], glow: 0xffb347 },
    { x: -1.5, h: 1.0, w: 1.3, num: '2', label: '1ST RUNNERS-UP', img: '/award/1st-runners-up.avif', visible: 1.4, pad: [0.060, 0.030], glow: 0xcfd8e3 },
    { x: 1.5, h: 0.62, w: 1.3, num: 'MVP', label: 'MOST VALUABLE PLAYER', img: '/award/mvp_male.avif', visible: 1.4, pad: [0.034, 0.014], glow: 0xd9824a },
];

const STONE = 0x17120f;
const GOLD = 0xd9a21b;

function webglAvailable() {
    try {
        const c = document.createElement('canvas');
        return !!(window.WebGLRenderingContext && (c.getContext('webgl2') || c.getContext('webgl')));
    } catch (e) {
        return false;
    }
}

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

function labelTexture(num, text) {
    const c = document.createElement('canvas');
    c.width = c.height = 512;
    const g = c.getContext('2d');
    g.textAlign = 'center';
    g.textBaseline = 'middle';
    const big = num.length > 1 ? 150 : 250;
    g.font = `800 ${big}px "Plus Jakarta Sans", system-ui, sans-serif`;
    const grad = g.createLinearGradient(0, 120, 0, 340);
    grad.addColorStop(0, '#FFE9A8');
    grad.addColorStop(0.5, '#E8A317');
    grad.addColorStop(1, '#9A6208');
    g.fillStyle = grad;
    g.fillText(num, 256, 230);
    g.font = '700 34px "Plus Jakarta Sans", system-ui, sans-serif';
    g.letterSpacing = '6px';
    g.fillStyle = 'rgba(244, 241, 234, 0.82)';
    g.fillText(text, 256, 380);
    const t = new THREE.CanvasTexture(c);
    t.colorSpace = THREE.SRGBColorSpace;
    t.anisotropy = 8;
    return t;
}

async function init() {
    if (!mount || !webglAvailable() || (mount.closest('[data-mobile-only]') && matchMedia('(min-width: 1024px)').matches)) return;

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    try { await document.fonts.load('800 200px "Plus Jakarta Sans"'); } catch (e) { /* fall back to system font */ }

    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'high-performance' });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
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
    const pmrem = new THREE.PMREMGenerator(renderer);
    scene.environment = pmrem.fromScene(new RoomEnvironment(), 0.04).texture;
    scene.environmentIntensity = 0.55;

    const camera = new THREE.PerspectiveCamera(28, 1, 0.1, 60);
    const lookAt = new THREE.Vector3(0, 1.25, 0);

    // ---- lights ----
    const key = new THREE.SpotLight(0xffe6c4, 190, 40, Math.PI / 6.2, 0.65, 1.1);
    key.position.set(0.6, 10, 3.4);
    key.target.position.set(0, 0.8, 0);
    key.castShadow = true;
    key.shadow.mapSize.set(1024, 1024);
    key.shadow.bias = -0.0004;
    key.shadow.radius = 5;
    scene.add(key, key.target);
    const rimWarm = new THREE.PointLight(0xff7a2b, 20, 22, 1.7);
    rimWarm.position.set(-5, 3, -4);
    scene.add(rimWarm);
    const rimCool = new THREE.PointLight(0xbcd2ff, 12, 22, 1.8);
    rimCool.position.set(5.5, 2.5, 3);
    scene.add(rimCool);
    scene.add(new THREE.HemisphereLight(0xffe9d2, 0x120c08, 0.28));

    // ---- floor ----
    const floor = new THREE.Mesh(
        new THREE.CircleGeometry(8, 128),
        new THREE.MeshStandardMaterial({ color: 0x120e0b, roughness: 0.3, metalness: 0.6 }),
    );
    floor.rotation.x = -Math.PI / 2;
    floor.receiveShadow = true;
    scene.add(floor);
    const floorGlow = new THREE.Mesh(
        new THREE.CircleGeometry(5.2, 96),
        new THREE.MeshBasicMaterial({
            map: radialTexture([[0, 'rgba(255,140,60,0.34)'], [0.45, 'rgba(255,106,19,0.1)'], [1, 'rgba(255,106,19,0)']]),
            transparent: true, depthWrite: false, blending: THREE.AdditiveBlending,
        }),
    );
    floorGlow.rotation.x = -Math.PI / 2;
    floorGlow.position.y = 0.004;
    scene.add(floorGlow);
    const ringMat = new THREE.MeshStandardMaterial({ color: 0xffa24a, emissive: 0xff7a1f, emissiveIntensity: 1.6, metalness: 0.6, roughness: 0.3 });
    const ring = new THREE.Mesh(new THREE.TorusGeometry(3.15, 0.014, 10, 220), ringMat);
    ring.rotation.x = Math.PI / 2;
    ring.position.y = 0.012;
    scene.add(ring);
    const ring2 = new THREE.Mesh(new THREE.TorusGeometry(3.55, 0.007, 8, 220), ringMat);
    ring2.rotation.x = Math.PI / 2;
    ring2.position.y = 0.01;
    scene.add(ring2);

    // ---- turntable podium ----
    const podium = new THREE.Group();
    scene.add(podium);
    const stone = new THREE.MeshPhysicalMaterial({ color: STONE, roughness: 0.22, metalness: 0.2, clearcoat: 1, clearcoatRoughness: 0.12, envMapIntensity: 0.9 });
    const gold = new THREE.MeshStandardMaterial({ color: GOLD, metalness: 1, roughness: 0.26, envMapIntensity: 1.3 });
    const goldBright = new THREE.MeshStandardMaterial({ color: 0xf5c542, metalness: 1, roughness: 0.16, envMapIntensity: 1.4 });
    const slab = new THREE.MeshPhysicalMaterial({ color: 0x0f0c0a, roughness: 0.16, metalness: 0.35, clearcoat: 1, clearcoatRoughness: 0.06, envMapIntensity: 0.8 });
    const shadowTex = radialTexture([[0, 'rgba(0,0,0,0.65)'], [0.6, 'rgba(0,0,0,0.25)'], [1, 'rgba(0,0,0,0)']]);

    const loader = new THREE.TextureLoader();
    const trophies = [];

    const topY = (b) => b.h + 0.07;
    BLOCKS.forEach((b) => {
        const body = new THREE.Mesh(new RoundedBoxGeometry(b.w, b.h, b.w, 5, 0.035), stone);
        body.position.set(b.x, b.h / 2, 0);
        body.castShadow = body.receiveShadow = true;
        podium.add(body);

        const plinth = new THREE.Mesh(new RoundedBoxGeometry(b.w + 0.18, 0.13, b.w + 0.18, 4, 0.04), gold);
        plinth.position.set(b.x, 0.065, 0);
        plinth.castShadow = plinth.receiveShadow = true;
        podium.add(plinth);

        // top slab: dark polished stone with a thin gold frame, so the spotlight does not flatten it
        const cap = new THREE.Mesh(new RoundedBoxGeometry(b.w + 0.12, 0.09, b.w + 0.12, 4, 0.04), slab);
        cap.position.set(b.x, b.h + 0.01, 0);
        cap.castShadow = cap.receiveShadow = true;
        podium.add(cap);
        const half = (b.w + 0.12) / 2;
        [[0, half, b.w + 0.12, 0.035], [0, -half, b.w + 0.12, 0.035], [half, 0, 0.035, b.w + 0.12], [-half, 0, 0.035, b.w + 0.12]].forEach(([fx, fz, fw, fd]) => {
            const edge = new THREE.Mesh(new RoundedBoxGeometry(fw, 0.05, fd, 2, 0.012), goldBright);
            edge.position.set(b.x + fx, b.h + 0.045, fz);
            edge.castShadow = true;
            podium.add(edge);
        });

        // thin inlay line under the cap
        const inlay = new THREE.Mesh(new RoundedBoxGeometry(b.w + 0.012, 0.022, b.w + 0.012, 2, 0.01), gold);
        inlay.position.set(b.x, b.h - 0.12, 0);
        podium.add(inlay);

        // engraved number and name on all four faces
        const tex = labelTexture(b.num, b.label);
        const size = Math.min(b.w * 0.74, b.h * 0.8);
        const faceMat = new THREE.MeshStandardMaterial({ map: tex, transparent: true, metalness: 0.5, roughness: 0.4, emissive: 0x2a1a05, emissiveMap: tex, emissiveIntensity: 0.5, depthWrite: false });
        for (let k = 0; k < 4; k++) {
            const p = new THREE.Mesh(new THREE.PlaneGeometry(size, size), faceMat);
            const a = (k * Math.PI) / 2;
            p.rotation.y = a;
            p.position.set(b.x + Math.sin(a) * (b.w / 2 + 0.004), b.h * 0.5 - 0.02, Math.cos(a) * (b.w / 2 + 0.004));
            podium.add(p);
        }

        // contact shadow and soft glow on the top
        const sh = new THREE.Mesh(new THREE.PlaneGeometry(b.w * 0.95, b.w * 0.95), new THREE.MeshBasicMaterial({ map: shadowTex, transparent: true, depthWrite: false }));
        sh.rotation.x = -Math.PI / 2;
        sh.position.set(b.x, topY(b) - 0.024, 0);
        podium.add(sh);
        const gl = new THREE.Mesh(
            new THREE.PlaneGeometry(b.w * 1.1, b.w * 1.1),
            new THREE.MeshBasicMaterial({
                map: radialTexture([[0, 'rgba(255,255,255,0.5)'], [1, 'rgba(255,255,255,0)']]), color: b.glow, transparent: true, opacity: 0.4,
                depthWrite: false, blending: THREE.AdditiveBlending,
            }),
        );
        gl.rotation.x = -Math.PI / 2;
        gl.position.set(b.x, topY(b) - 0.02, 0);
        podium.add(gl);

        // the trophy card
        loader.load(b.img, (t) => {
            t.colorSpace = THREE.SRGBColorSpace;
            t.anisotropy = 8;
            const aspect = t.image.width / t.image.height;
            const full = b.visible / (1 - b.pad[0] - b.pad[1]);
            const mesh = new THREE.Mesh(
                new THREE.PlaneGeometry(full * aspect, full),
                new THREE.MeshBasicMaterial({ map: t, transparent: true, alphaTest: 0.02, depthWrite: true, toneMapped: false }),
            );
            mesh.userData = { block: b, baseY: topY(b) - 0.035 + full * (0.5 - b.pad[0]) };
            trophies.push(mesh);
            scene.add(mesh);
            if (reduceMotion && typeof frame === 'function') requestAnimationFrame(frame);
        });
    });

    // ---- spotlight cone ----
    const cone = new THREE.Mesh(
        new THREE.ConeGeometry(3.3, 9, 72, 1, true),
        new THREE.ShaderMaterial({
            transparent: true, depthWrite: false, blending: THREE.AdditiveBlending, side: THREE.DoubleSide,
            uniforms: { uColor: { value: new THREE.Color(0xffb878) } },
            vertexShader: `varying float vY; varying vec3 vN; varying vec3 vV;
                void main(){ vY = position.y; vec4 mv = modelViewMatrix * vec4(position, 1.0);
                vN = normalize(normalMatrix * normal); vV = normalize(-mv.xyz); gl_Position = projectionMatrix * mv; }`,
            fragmentShader: `uniform vec3 uColor; varying float vY; varying vec3 vN; varying vec3 vV;
                void main(){ float h = clamp((vY + 4.5) / 9.0, 0.0, 1.0);
                float edge = pow(abs(dot(normalize(vN), normalize(vV))), 1.6);
                gl_FragColor = vec4(uColor, (0.02 + 0.2 * h * h) * edge); }`,
        }),
    );
    cone.position.set(0.6 * 0.0, 5.0, 0);
    scene.add(cone);

    // ---- floating gold dust ----
    const N = 150;
    const pos = new Float32Array(N * 3);
    const seed = new Float32Array(N);
    for (let i = 0; i < N; i++) {
        const a = Math.random() * Math.PI * 2, r = 0.6 + Math.random() * 3.2;
        pos.set([Math.cos(a) * r, Math.random() * 5.2, Math.sin(a) * r], i * 3);
        seed[i] = Math.random() * 10;
    }
    const dustGeo = new THREE.BufferGeometry();
    dustGeo.setAttribute('position', new THREE.BufferAttribute(pos, 3));
    const dust = new THREE.Points(dustGeo, new THREE.PointsMaterial({
        size: 0.075, map: radialTexture([[0, 'rgba(255,225,150,1)'], [1, 'rgba(255,200,100,0)']], 64), color: 0xffd58a,
        transparent: true, opacity: 0.8, depthWrite: false, blending: THREE.AdditiveBlending, sizeAttenuation: true,
    }));
    scene.add(dust);

    // ---- sizing, interaction, loop ----
    let w = 1, h = 1;
    function resize() {
        w = mount.clientWidth || 1;
        h = mount.clientHeight || 1;
        renderer.setSize(w, h, false);
        camera.aspect = w / h;
        // Pull the camera back on narrow stages so the whole podium always fits
        const dist = Math.max(10.6, 10.6 * (1.1 / Math.min(camera.aspect, 1.1)) * 0.95);
        camera.position.set(0, 2.9, dist);
        camera.lookAt(lookAt);
        camera.updateProjectionMatrix();
    }
    new ResizeObserver(resize).observe(mount);
    resize();

    let spin = 0.35;              // current angle
    let velocity = 0.2;           // rad/s, eases back to the idle speed
    const IDLE = 0.22;
    let dragging = false, lastX = 0;
    let px = 0, py = 0;           // pointer parallax
    canvas.addEventListener('pointerdown', (e) => { dragging = true; lastX = e.clientX; canvas.setPointerCapture(e.pointerId); canvas.classList.replace('cursor-grab', 'cursor-grabbing'); });
    canvas.addEventListener('pointermove', (e) => {
        const r = canvas.getBoundingClientRect();
        px = ((e.clientX - r.left) / r.width - 0.5) * 2;
        py = ((e.clientY - r.top) / r.height - 0.5) * 2;
        if (!dragging) return;
        const dx = e.clientX - lastX;
        lastX = e.clientX;
        spin += dx * 0.008;
        velocity = dx * 0.35;
    });
    const release = () => { dragging = false; canvas.classList.replace('cursor-grabbing', 'cursor-grab'); };
    canvas.addEventListener('pointerup', release);
    canvas.addEventListener('pointercancel', release);

    const tmp = new THREE.Vector3();
    const clock = new THREE.Clock();
    let visible = true, raf = 0;

    function frame() {
        const dt = Math.min(clock.getDelta(), 0.05);
        const t = clock.elapsedTime;
        if (!reduceMotion && !dragging) {
            velocity += (IDLE - velocity) * Math.min(1, dt * 1.4);
            spin += velocity * dt;
        }
        podium.rotation.y = spin;
        ring.rotation.z = -spin * 0.6;

        camera.position.x += (px * 0.7 - camera.position.x) * 0.04;
        camera.position.y += (2.9 - py * 0.35 - camera.position.y) * 0.04;
        camera.lookAt(lookAt);

        trophies.forEach((m, i) => {
            const b = m.userData.block;
            tmp.set(b.x, 0, 0).applyMatrix4(podium.matrixWorld);
            const bob = reduceMotion ? 0 : Math.sin(t * 1.3 + i * 1.7) * 0.03;
            m.position.set(tmp.x, m.userData.baseY + bob, tmp.z);
            // face the camera around the vertical axis only
            m.rotation.y = Math.atan2(camera.position.x - m.position.x, camera.position.z - m.position.z);
        });

        const a = dustGeo.attributes.position;
        for (let i = 0; i < N; i++) {
            let y = a.getY(i) + dt * (0.12 + (seed[i] % 1) * 0.16);
            if (y > 5.3) y = 0.1;
            a.setY(i, y);
            a.setX(i, a.getX(i) + Math.sin(t * 0.5 + seed[i]) * dt * 0.05);
        }
        a.needsUpdate = true;
        dust.rotation.y = t * 0.03;

        renderer.render(scene, camera);
        if (visible && !reduceMotion && !document.hidden) raf = requestAnimationFrame(frame);
        else raf = 0;
    }
    function start() { if (!raf) { clock.getDelta(); raf = requestAnimationFrame(frame); } }

    new IntersectionObserver(([e]) => { visible = e.isIntersecting; if (visible) start(); }, { threshold: 0.05 }).observe(mount);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) start(); });
    if (reduceMotion) new ResizeObserver(() => frame()).observe(mount);

    frame();
    start();
    requestAnimationFrame(() => { canvas.classList.remove('opacity-0'); mount.dataset.ready = '1'; });
}

init();
