@props(['name', 'upload' => null])
{{-- Team badge. The logo is chosen by App\Support\TeamLogo (config map, then the
     company's uploaded logo, then initials), the same lookup the bracket uses.
     Decorative only: the team name is always printed next to it. --}}
@php($logo = \App\Support\TeamLogo::resolve($name, $upload))
<span {{ $attributes->class(['tc-badge', 'tc-badge-cse' => $logo['type'] === 'cse', 'tc-badge-initials' => $logo['type'] === 'initials']) }} aria-hidden="true">
    @if ($logo['type'] === 'logo')
        <img src="{{ $logo['url'] }}" alt="" width="56" height="40" loading="lazy" decoding="async">
    @elseif ($logo['type'] === 'cse')
        <span>CSE</span><i></i><span>{{ $logo['batch'] }}</span>
    @else
        {{ $logo['initials'] }}
    @endif
</span>
