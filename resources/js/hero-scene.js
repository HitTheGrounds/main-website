import * as THREE from 'three';
import { GLTFLoader } from 'three/examples/jsm/loaders/GLTFLoader.js';
import { MeshoptDecoder } from 'three/examples/jsm/libs/meshopt_decoder.module.js';
import { OrbitControls } from 'three/examples/jsm/controls/OrbitControls.js';
import { RoomEnvironment } from 'three/examples/jsm/environments/RoomEnvironment.js';

const mount = document.getElementById('hero-3d');

// Scene layout: tweak these to re-pose the models.
const LAYOUT = {
    throne: { pos: [0, 0, 0], scale: 1 },
    cricketer: { pos: [0, -0.1, 0.75], scale: 0.88 },
};

function webglAvailable() {
    try {
        const c = document.createElement('canvas');
        return !!(window.WebGLRenderingContext && (c.getContext('webgl2') || c.getContext('webgl')));
    } catch (e) {
        return false;
    }
}

async function init() {
    if (!mount || !webglAvailable()) return;

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const stage = mount.querySelector('[data-stage]');

    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'high-performance' });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.05;
    renderer.domElement.className = 'absolute inset-0 h-full w-full touch-pan-y cursor-grab active:cursor-grabbing opacity-0 transition-opacity duration-1000 ease-[cubic-bezier(0.32,0.72,0,1)]';
    renderer.domElement.setAttribute('aria-label', 'Interactive 3D cricketer on a throne of bats. Drag to rotate.');
    renderer.domElement.setAttribute('role', 'img');
    stage.appendChild(renderer.domElement);

    const scene = new THREE.Scene();
    const pmrem = new THREE.PMREMGenerator(renderer);
    scene.environment = pmrem.fromScene(new RoomEnvironment(), 0.04).texture;
    scene.environmentIntensity = 0.7;

    const camera = new THREE.PerspectiveCamera(30, 1, 0.1, 50);
    camera.position.set(0, 0.3, 5.6);

    // Warm key from the front, orange rim from behind to tie into the theme
    const key = new THREE.DirectionalLight(0xfff1e0, 2.2);
    key.position.set(2.5, 3.5, 4);
    scene.add(key);
    const rim = new THREE.DirectionalLight(0xff6a13, 2.6);
    rim.position.set(-3.5, 2, -3);
    scene.add(rim);

    // Soft contact shadow on the floor (no real shadow maps: cheap and clean)
    const shadowCanvas = document.createElement('canvas');
    shadowCanvas.width = shadowCanvas.height = 128;
    const sctx = shadowCanvas.getContext('2d');
    const grad = sctx.createRadialGradient(64, 64, 4, 64, 64, 62);
    grad.addColorStop(0, 'rgba(31,27,22,0.45)');
    grad.addColorStop(1, 'rgba(31,27,22,0)');
    sctx.fillStyle = grad;
    sctx.fillRect(0, 0, 128, 128);
    const shadow = new THREE.Mesh(
        new THREE.PlaneGeometry(3.6, 2.4),
        new THREE.MeshBasicMaterial({ map: new THREE.CanvasTexture(shadowCanvas), transparent: true, depthWrite: false })
    );
    shadow.rotation.x = -Math.PI / 2;
    shadow.position.y = -0.96;

    const pivot = new THREE.Group();
    scene.add(pivot);
    pivot.add(shadow);

    const loader = new GLTFLoader();
    loader.setMeshoptDecoder(MeshoptDecoder);
    const load = (url) => loader.loadAsync(url);

    let throne, cricketer;
    try {
        [throne, cricketer] = await Promise.all([load('/models/Throne.glb'), load('/models/Cricketer.glb')]);
    } catch (e) {
        renderer.dispose();
        renderer.domElement.remove();
        return; // fallback card stays visible
    }

    for (const [gltf, cfg] of [[throne, LAYOUT.throne], [cricketer, LAYOUT.cricketer]]) {
        const obj = gltf.scene;
        obj.position.set(...cfg.pos);
        obj.scale.setScalar(cfg.scale);
        pivot.add(obj);
    }

    const controls = new OrbitControls(camera, renderer.domElement);
    controls.enableZoom = false;
    controls.enablePan = false;
    controls.enableDamping = true;
    controls.dampingFactor = 0.07;
    controls.rotateSpeed = 0.7;
    controls.minPolarAngle = Math.PI * 0.38;
    controls.maxPolarAngle = Math.PI * 0.56;
    controls.target.set(0, 0, 0);

    let lastInput = -Infinity;
    const hint = mount.querySelector('[data-hint]');
    controls.addEventListener('start', () => {
        lastInput = performance.now();
        if (hint) hint.style.opacity = '0';
    });
    controls.addEventListener('end', () => { lastInput = performance.now(); });

    function resize() {
        const { clientWidth: w, clientHeight: h } = stage;
        if (!w || !h) return;
        renderer.setSize(w, h, false);
        camera.aspect = w / h;
        camera.updateProjectionMatrix();
    }
    new ResizeObserver(resize).observe(stage);
    resize();

    // Only render while visible
    let visible = true;
    new IntersectionObserver(([e]) => { visible = e.isIntersecting; }, { threshold: 0.05 }).observe(mount);

    const clock = new THREE.Clock();
    function frame() {
        requestAnimationFrame(frame);
        if (!visible || document.hidden) return;
        const t = clock.getElapsedTime();
        const idle = performance.now() - lastInput > 2500;
        if (!reduceMotion && idle) pivot.rotation.y = Math.sin(t * 0.45) * 0.35;
        controls.update();
        renderer.render(scene, camera);
    }
    frame();

    renderer.domElement.style.opacity = '1';
    mount.dataset.ready = 'true';
}

init();
