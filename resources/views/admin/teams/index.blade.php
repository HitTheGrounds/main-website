<x-layouts.admin>
    <div>
        <div class="mb-6 flex justify-between items-start">
            <div>
                <h1 class="text-3xl font-bold text-base-content">Teams</h1>
                <p class="mt-2 text-base-content/70">Review and approve teams</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('admin.teams.export', ['type' => 'locked']) }}" target="_blank" class="btn btn-primary btn-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Export Locked Teams
                </a>
                <a href="{{ route('admin.teams.export', ['type' => 'all']) }}" target="_blank" class="btn btn-secondary btn-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Export All Teams
                </a>
            </div>
        </div>

        <livewire:admin.teams-list />
    </div>
</x-layouts.admin>
