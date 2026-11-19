@props(['name'])
{{-- Thin-line icons for the scorer portal. Decorative: every use sits next to
     visible text or an aria-label, so the SVG is hidden from assistive tech. --}}
<svg {{ $attributes->class('scorer-icon') }} xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
     stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    @switch($name)
        @case('plus')<path d="M12 5v14M5 12h14"/>@break
        @case('pencil')<path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L8 18l-4 1 1-4Z"/><path d="m14.5 5.5 3 3"/>@break
        @case('scoreboard')<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M12 5v14M7 10v4M15.5 10h2v2h-2v2h2"/>@break
        @case('users')<circle cx="9" cy="8" r="3.25"/><path d="M3.5 19a5.5 5.5 0 0 1 11 0"/><path d="M15.5 4.9a3.25 3.25 0 0 1 0 6.2M17.5 14.2a5.5 5.5 0 0 1 3 4.8"/>@break
        @case('filter')<path d="M4 5h16l-6 7.5V18l-4 2v-7.5Z"/>@break
        @case('x')<path d="M6 6l12 12M18 6 6 18"/>@break
        @case('refresh')<path d="M20 11a8 8 0 0 0-14.3-4.9L4 8"/><path d="M4 4v4h4"/><path d="M4 13a8 8 0 0 0 14.3 4.9L20 16"/><path d="M20 20v-4h-4"/>@break
        @case('clock')<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>@break
        @case('check')<path d="m5 12.5 4.5 4.5L19 7.5"/>@break
        @case('calendar')<rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/>@break
        @case('pin')<path d="M12 21s-6.5-5.6-6.5-11a6.5 6.5 0 0 1 13 0C18.5 15.4 12 21 12 21Z"/><circle cx="12" cy="10" r="2.25"/>@break
        @case('ball')<circle cx="12" cy="12" r="8.5"/><path d="M8.2 4.4c1.8 2.1 2.8 4.7 2.8 7.6s-1 5.5-2.8 7.6M15.8 4.4c-1.8 2.1-2.8 4.7-2.8 7.6s1 5.5 2.8 7.6"/>@break
        @case('rotate-ccw')<path d="M4 4v5h5"/><path d="M5.1 15a8 8 0 1 0 1.9-8.3L4 9"/>@break
        @case('arrow-left')<path d="M19 12H5M11 6l-6 6 6 6"/>@break
        @case('eye')<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="2.75"/>@break
        @case('save')<path d="M5 4h11l3 3v13H5Z"/><path d="M8 4v5h7V4M8 20v-6h8v6"/>@break
        @case('trash')<path d="M4 7h16M9 7V4.5h6V7M6.5 7l1 13h9l1-13"/>@break
        @case('send')<path d="M21 3 10 14M21 3l-7 18-4-7-7-4Z"/>@break
        @case('trophy')<path d="M8 4h8v5a4 4 0 0 1-8 0Z"/><path d="M8 6H5a3 3 0 0 0 3 4M16 6h3a3 3 0 0 1-3 4M12 13v4M8.5 20h7M10 17h4"/>@break
    @endswitch
</svg>
