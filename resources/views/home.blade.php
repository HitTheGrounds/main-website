<x-layouts.public>
    <x-HeroSection />
    <x-MoreThanGameSection />
    <x-AboutSection />
    <x-MobileHighlights />
    {{-- <x-ContendersSection /> --}}
    <x-CommitteeSection />
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'SportsEvent',
            'name' => 'Hit the Grounds 2026',
            'description' => 'The annual cricket tournament of the Department of Computer Science and Engineering, University of Moratuwa, where university teams and industry teams compete in a one-day showcase.',
            'sport' => 'Cricket',
            'startDate' => '2026-11-21',
            'endDate' => '2026-11-21',
            'eventStatus' => 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'url' => route('home'),
            'image' => asset('og-cover-2.png'),
            'location' => ['@type' => 'Place', 'name' => 'University of Moratuwa grounds', 'address' => ['@type' => 'PostalAddress', 'addressLocality' => 'Moratuwa', 'addressCountry' => 'LK']],
            'organizer' => ['@type' => 'CollegeOrUniversity', 'name' => 'Department of Computer Science and Engineering, University of Moratuwa'],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}
    </script>
    @vite(['resources/js/awards-podium.js', 'resources/js/past-seasons-3d.js'])
</x-layouts.public>
