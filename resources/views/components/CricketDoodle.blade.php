@props(['kind'])
<svg {{ $attributes }} viewBox="0 0 120 160" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
    @if ($kind === 'ball')
        <circle cx="60" cy="80" r="34" />
        <path d="M42 51Q78 80 42 109M49 48Q85 80 49 112" stroke-dasharray="3 5" />
        <path d="m15 48-8-7m10 27H5m98 26 10 7" stroke-width="2" />
    @elseif ($kind === 'bat')
        <g transform="rotate(-32 60 80)">
            <path d="M54 12h12v51H54z" fill="currentColor" />
            <path d="M45 63h30l4 76q-19 12-38 0z" />
            <path d="M52 76v57M60 76v62M68 76v57" stroke-width="1.5" />
            <path d="m54 23 12 5m-12 5 12 5m-12 5 12 5" stroke="var(--color-base-200)" stroke-width="2" />
        </g>
    @else
        {{-- A batter in a forward stance, with helmet, gloves, pads and raised bat. --}}
        <path d="M57 32a12 12 0 1 0-24 0v6h25M35 38h23l-2 10H44l-8-10M46 39v8m6-8v8" />
        <path d="M35 51 24 79l21 14 18-25-10-15z" fill="currentColor" stroke="none" />
        <path d="m49 57 16 7 17-20m-45 18 23 15 24-26" stroke-width="8" />
        <path d="m28 84-9 31-13 25h17l16-23 9-19m-3-6 20 20 15 27h18l-18-35-22-24" fill="currentColor" stroke="none" />
        <path d="m20 117 10 6m-14 0 10 6m46-11 9-5m-5 13 9-5" stroke="var(--color-base-200)" stroke-width="2" />
        <path d="m83 44 9-17m-3 3 15-24 8 5-14 26z" />
        <circle cx="103" cy="79" r="6" /><path d="m101 74 4 10" stroke-width="1.5" />
        <path d="M5 145h96" stroke-width="1.5" />
    @endif
</svg>
