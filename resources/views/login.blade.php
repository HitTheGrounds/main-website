<x-layouts.public title="Sign in">
    <x-PageContainer class="min-h-screen flex items-center justify-center">
        <div class="max-w-md w-full">
            <div class="text-center mb-8">
                <h2 class="text-3xl font-bold text-base-content">Sign in</h2>
                <p class="mt-2 text-base-content/70">For university and company representatives, scorers and administrators.</p>
                <p class="mt-2 text-base-content/70">Enter your email to receive a verification code</p>
            </div>

            <livewire:company-login-form />
        </div>
    </x-PageContainer>
</x-layouts.public>
