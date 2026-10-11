@props(['flip' => false])

<svg {{ $attributes->merge(['class' => 'ps-laurel']) }} viewBox="0 0 24 32" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true" @if ($flip) style="transform: scaleX(-1)" @endif>
    <path d="M18 29 C8 25 4 16 6 4"/>
    <path d="M9 22 C5 22 3 19 3 16 C7 16 9 18 9 22 Z M8 14 C4 13 3 10 3 7 C7 8 8 10 8 14 Z M12 8 C9 6 9 3 10 1 C13 3 13 5 12 8 Z M14 25 C10 26 8 24 7 22"/>
</svg>
