<x-layouts.company>
    @php
        $user = \App\Helpers\CompanyAuth::user();
    @endphp

    <div>
        <!-- Header -->
        <div class="mb-6 bg-primary -mx-4 -mt-4 lg:-mx-6 lg:-mt-6 px-4 lg:px-6 pt-4 lg:pt-6 pb-4 lg:pb-4 border-b-2 border-base-300">
            <h1 class="text-3xl font-bold text-accent">Company Profile</h1>
            <p class="mt-2 text-accent">Customize your company profile and information</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Edit Section -->
            <div class="lg:col-span-1">
                <livewire:company.edit-company-profile :company="$user->company" />
            </div>

            <!-- Preview Section -->
            <div class="lg:col-span-2">
                <livewire:company.company-profile-preview :company="$user->company" />
            </div>
        </div>
    </div>
</x-layouts.company>
