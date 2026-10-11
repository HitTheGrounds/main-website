@php
    // Interleaved so neighbouring tiles come from different matches
    $memories = [
        [10, 'The champions trophy on its plinth'],
        [6, 'A fielder in blue smiling and clapping'],
        [1, 'A bowler in mid-delivery stride'],
        [11, 'Players working on a wooden installation'],
        [2, 'A batter swinging at the crease'],
        [12, 'A match in progress on the ground'],
        [3, 'Darts stuck in a dartboard'],
        [13, 'Players in purple jerseys on the sidelines'],
        [14, 'The blue-jersey team taking a selfie'],
        [5, 'A blue-jersey team group photo'],
        [15, 'The purple-jersey team after dark'],
        [16, 'The teal-jersey team posing together'],
        [7, 'Bamboo decoration beside a sponsor backdrop'],
        [17, 'Players walking across the field'],
        [8, 'Players gathered at the scoreboard'],
        [4, 'The maroon-jersey team posing together'],
        [18, 'The wicketkeeper and umpire at the stumps'],
        [9, 'A batter playing a shot'],
        [19, 'Players in orange watching from the bank'],
        [20, 'A batter in a blue jersey driving through a big shot'],
        [21, 'A blue-jersey team group photo at the backdrop'],
        [22, 'Officials seated at a table under a tent'],
        [23, 'A large group posing together under a blue tent'],
        [24, 'A batter at the crease with spectators behind the stumps'],
        [42, 'Players walking across the ground'],
        [25, 'A wicketkeeper in a black jersey numbered 95'],
        [43, 'Players in teal jerseys resting by the boundary'],
        [26, 'A photographer in a bucket hat lining up a shot'],
        [44, 'Three women in navy polo shirts and sun hats smiling'],
        [27, 'A batter playing a shot in front of the sidelines'],
        [45, 'A team in purple jerseys seated under a tent'],
        [28, 'A photographer in a bucket hat with a camera strap'],
        [46, 'A team in navy and orange jerseys posing together under a tent'],
        [29, 'Two players in maroon University of Moratuwa shirts'],
        [47, 'Volunteers in navy polos lined up in front of the sponsor backdrop'],
        [30, 'Fielders and a batter at the stumps in low light'],
        [48, 'Three men in navy polos posing under a blue tent'],
        [31, 'A batter in a pink and navy kit playing a shot'],
        [49, 'A large group in blue shirts posing in front of a banner'],
        [32, 'A batter at the stumps with the keeper crouched behind'],
        [50, 'Three men in blue polos seated in the shade'],
        [33, 'A bowler in dark kit delivering the ball with an umpire behind'],
        [51, 'A batter taking guard beside the stumps'],
        [34, 'Three players in maroon and orange jerseys walking across the grass'],
        [52, 'A large group in navy and blue shirts posing on the field'],
        [35, 'A team in blue and purple jerseys posing in front of the sponsor backdrop'],
        [53, 'A batter swinging at a delivery'],
        [36, 'Players and a supporter in front of a pink food truck'],
        [54, 'Players spread across the field'],
        [37, 'A team in blue jerseys seated under a tent'],
        [55, 'A team in blue gradient jerseys in front of the sponsor backdrop'],
        [38, 'A group in white and navy shirts posing in front of the sponsor backdrop'],
        [56, 'A team in blue jerseys with arms around each other in front of the backdrop'],
        [39, 'Players relaxing in a green tent'],
        [57, 'A fielder running in on the ground'],
        [40, 'A batter waiting at the stumps on a dry pitch'],
        [58, 'Two women in blue shirts smiling in front of the sponsor backdrop'],
        [41, 'A man in a black shirt holding a bat in front of the sponsor backdrop'],
    ];
    $sizes = [7 => [800, 1200], 10 => [800, 1200], 18 => [800, 1200], 40 => [800, 1200], 41 => [800, 1200], 43 => [800, 1200], 51 => [800, 1200]];
@endphp

<x-layouts.public>
    <section class="bg-base-100">
        <x-PageContainer>
            <h1 class="mb-12 text-center font-heading text-[clamp(2.75rem,8vw,5.5rem)] md:mb-16" data-aos="fade-up">
                <span class="text-base-content">Hit the Grounds </span><span class="text-primary">Memories</span>
            </h1>

            <div class="mem-grid columns-2 gap-4 md:columns-3 xl:columns-4">
                @foreach ($memories as $i => [$n, $alt])
                    @php [$w, $h] = $sizes[$n] ?? [1200, 800]; @endphp
                    <div class="mem-item mb-4 break-inside-avoid" data-r="{{ $h / $w }}" data-aos="fade-up" data-aos-delay="{{ ($i % 4) * 80 }}">
                        <figure class="mem-tile" style="aspect-ratio: {{ $w }} / {{ $h }}">
                            <img src="/images/memories/memory-{{ sprintf('%02d', $n) }}.avif" alt="{{ $alt }}" width="{{ $w }}" height="{{ $h }}" loading="lazy" decoding="async">
                        </figure>
                    </div>
                @endforeach
            </div>
        </x-PageContainer>
    </section>

    <script>
        // Fill the collage left to right: each photo goes into the shortest column,
        // so the first photos in the list sit in the top row.
        (() => {
            const grid = document.querySelector('.mem-grid');
            const items = [...grid.querySelectorAll('.mem-item')];
            let current = 0;
            const count = () => (innerWidth >= 1280 ? 4 : innerWidth >= 768 ? 3 : 2);
            const layout = () => {
                const n = count();
                if (n === current) return;
                current = n;
                const cols = Array.from({ length: n }, () => {
                    const c = document.createElement('div');
                    c.className = 'mem-col';
                    c.height = 0;
                    return c;
                });
                items.forEach((item) => {
                    const target = cols.reduce((a, b) => (b.height < a.height ? b : a));
                    target.append(item);
                    target.height += parseFloat(item.dataset.r);
                });
                grid.replaceChildren(...cols);
                grid.dataset.js = '';
            };
            layout();
            addEventListener('resize', layout);
        })();

        // Point the light at the cursor
        document.querySelectorAll('.mem-tile').forEach((tile) => {
            const follow = (e) => {
                const r = tile.getBoundingClientRect();
                tile.style.setProperty('--mem-x', (e.clientX - r.left) + 'px');
                tile.style.setProperty('--mem-y', (e.clientY - r.top) + 'px');
            };
            tile.addEventListener('pointerenter', follow);
            tile.addEventListener('pointermove', follow);
        });
    </script>
</x-layouts.public>
