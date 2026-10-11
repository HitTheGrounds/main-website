@props(['id', 'label', 'dome', 'brim', 'band', 'ink', 'seam' => 'rgba(31,27,22,.22)'])

{{-- Stylised cricket cap. $dome = [light, mid, dark] gradient stops, $brim = [top, bottom]. --}}
<svg {{ $attributes }} viewBox="0 0 330 250" role="img" aria-label="{{ $label }}">
    <defs>
        <linearGradient id="dome-{{ $id }}" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" style="stop-color: {{ $dome[0] }}"/>
            <stop offset=".5" style="stop-color: {{ $dome[1] }}"/>
            <stop offset="1" style="stop-color: {{ $dome[2] }}"/>
        </linearGradient>
        <linearGradient id="brim-{{ $id }}" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" style="stop-color: {{ $brim[0] }}"/>
            <stop offset="1" style="stop-color: {{ $brim[1] }}"/>
        </linearGradient>
    </defs>
    <path d="M52 176 C38 84 100 24 170 24 C242 24 292 88 282 176 Z" fill="url(#dome-{{ $id }})"/>
    <path d="M170 24 C150 70 148 124 160 176" fill="none" stroke="{{ $seam }}" stroke-width="2.5"/>
    <path d="M170 24 C214 66 232 120 226 176" fill="none" stroke="{{ $seam }}" stroke-width="2.5"/>
    <path d="M170 24 C122 60 96 118 100 176" fill="none" stroke="{{ $seam }}" stroke-width="2.5"/>
    <path d="M86 116 C94 70 126 44 164 36" fill="none" stroke="rgba(255,255,255,.6)" stroke-width="8" stroke-linecap="round"/>
    <path d="M52 176 L282 176 L286 194 C236 206 100 206 48 194 Z" fill="{{ $band }}"/>
    <path d="M214 178 C264 166 318 176 324 198 C284 218 232 210 202 192 Z" fill="url(#brim-{{ $id }})"/>
    <circle cx="170" cy="24" r="10" fill="{{ $band }}"/>
    <text x="168" y="128" text-anchor="middle" font-family="Plus Jakarta Sans, sans-serif" font-size="30" font-weight="800" fill="{{ $ink }}" opacity=".88">HTG</text>
</svg>
