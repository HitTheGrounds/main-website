<p class="mt-4 text-center text-sm text-base-content/80">
    {{ $publicationLabel }}:
    @if ($publicationTime !== null)
        <time datetime="{{ $publicationTime }}">{{ \Carbon\CarbonImmutable::parse($publicationTime)->setTimezone(config('tournament.timezone'))->format('j M Y, g:i:s A') }}</time> (Sri Lanka time)
    @else
        Publication time unavailable.
    @endif
</p>
