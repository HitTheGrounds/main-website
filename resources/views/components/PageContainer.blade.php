@props(['floatingSquares' => true])

<div {{ $attributes->class(['relative isolate w-full px-4 sm:px-6 lg:px-12 py-12 sm:py-16 lg:py-20']) }}>
    @if ($floatingSquares)
        <x-FloatingSquares />
    @endif
    {{ $slot }}
</div>
