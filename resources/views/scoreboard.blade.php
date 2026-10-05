<x-layouts.app>
    <div class="container mx-auto px-4 py-8">
        <div class="text-center mb-10">
            <h1 class="text-4xl font-extrabold text-base-content tracking-tight">Tournament Scoreboard</h1>
            <p class="mt-3 text-lg text-base-content/70">Live updates and match results</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2">
                <livewire:public.match-cards />
            </div>
            <div class="lg:col-span-1">
                <livewire:public.group-standings />
            </div>
        </div>
    </div>
</x-layouts.app>
