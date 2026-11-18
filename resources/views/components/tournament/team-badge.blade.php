@props(['name'])
@php
    // Resolve a team's badge from config/tournament-teams.php. Decorative only:
    // the team name is always printed next to it, so the badge is hidden from
    // assistive technology.
    $config = config('tournament-teams');
    $normalise = fn (string $value) => mb_strtolower(preg_replace('/\s+/u', ' ', trim($value)));
    $teams = collect($config['teams'] ?? [])->mapWithKeys(fn ($key, $team) => [$normalise($team) => $key]);
    $key = $teams->get($normalise((string) $name));

    if ($key === null && ($config['demo_badges'] ?? false) && app()->environment('local')
        && preg_match('/^DEMO \d{4} ([A-Z])(\d+)$/', (string) $name, $demo)) {
        $keys = array_keys($config['badges'] ?? []);
        $index = (ord($demo[1]) - 65) * 4 + ((int) $demo[2] - 1);
        $key = $keys ? $keys[$index % count($keys)] : null;
    }

    $badge = $key !== null ? ($config['badges'][$key] ?? null) : null;
    $initials = collect(preg_split('/\s+/u', trim((string) $name)))
        ->filter()->take(2)->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->implode('');
@endphp
<span {{ $attributes->class(['tc-badge', 'tc-badge-cse' => isset($badge['cse']), 'tc-badge-initials' => $badge === null]) }} aria-hidden="true">
    @if (isset($badge['logo']))
        <img src="{{ asset('images/teams/'.$badge['logo']) }}" alt="" width="56" height="40" loading="lazy" decoding="async">
    @elseif (isset($badge['cse']))
        <span>CSE</span><i></i><span>{{ $badge['cse'] }}</span>
    @else
        {{ $initials !== '' ? $initials : '?' }}
    @endif
</span>
