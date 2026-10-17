{{-- 3D podium (resources/js/awards-podium.js). The 2D trophies below are the fallback when WebGL is off. --}}
<div id="awards-podium" role="img" aria-label="A rotating 3D podium with the champion, runners-up and Most Valuable Player trophies"
     class="group relative h-[400px] w-full overflow-hidden rounded-[2rem] shadow-[0_30px_60px_-30px_rgb(31_27_22_/_.6)] sm:h-[520px] lg:h-[620px] lg:flex-1"
     style="background: radial-gradient(70% 60% at 50% 38%, #3a2113 0%, #14100d 62%, #0b0907 100%)" data-aos="fade-up" data-aos-delay="120">
    <div data-fallback class="absolute inset-0 transition-opacity duration-700" aria-hidden="true">
        <div class="aw-float absolute bottom-10 left-[4%] sm:bottom-14"><img src="/award/1st-runners-up.avif" alt="" class="h-[110px] w-auto sm:h-[220px]"></div>
        <div class="aw-float absolute bottom-10 right-[4%] sm:bottom-14"><img src="/award/mvp_male.avif" alt="" width="1122" height="1402" class="h-[130px] w-auto sm:h-[250px]"></div>
        <div class="aw-float absolute inset-x-0 bottom-6 flex justify-center"><img src="/award/champion.avif" alt="" class="h-[250px] w-auto sm:h-[430px]"></div>
    </div>
    <p class="pointer-events-none absolute bottom-3 left-0 right-0 text-center text-[11px] font-bold uppercase tracking-[0.2em] text-base-100/45 opacity-0 transition-opacity duration-500 group-data-[ready]:opacity-100" aria-hidden="true">Drag to spin</p>
</div>
