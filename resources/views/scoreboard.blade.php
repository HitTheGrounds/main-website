<x-layouts.app>
    <div class="container mx-auto px-4 py-8">
        <div class="text-center mb-10">
            <h1 class="text-4xl font-extrabold text-base-content tracking-tight">Tournament Scoreboard</h1>
            <p class="mt-3 text-lg text-base-content/70">Live updates and match results</p>
        </div>

        <div x-data="{ tab: 'matches' }">
            <div class="flex justify-center mb-8">
                <div class="tabs tabs-boxed bg-base-200 shadow-sm">
                    <a class="tab tab-lg font-bold" :class="{ 'tab-active text-primary': tab === 'matches' }" @click="tab = 'matches'">Matches</a>
                    <a class="tab tab-lg font-bold" :class="{ 'tab-active text-primary': tab === 'bracket' }" @click="tab = 'bracket'">Knockout Bracket</a>
                    <a class="tab tab-lg font-bold" :class="{ 'tab-active text-primary': tab === 'standings' }" @click="tab = 'standings'">Group Standings</a>
                </div>
            </div>

            <div x-show="tab === 'matches'" class="grid grid-cols-1 gap-8 animate-fade-in">
                <livewire:public.match-cards />
            </div>
            
            <div x-show="tab === 'bracket'" x-cloak class="animate-fade-in">
                <livewire:public.tournament-bracket />
            </div>

            <div x-show="tab === 'standings'" x-cloak class="max-w-4xl mx-auto animate-fade-in">
                <livewire:public.group-standings />
            </div>
        </div>
    </div>
</x-layouts.app>
