<x-layouts.public title="Admin approval required">
    <x-PageContainer>
        <section class="relative mx-auto w-full max-w-3xl rounded-2xl border border-base-300 bg-base-100 p-5 text-center shadow-sm sm:p-8 lg:p-10" aria-labelledby="approval-heading">
            <img
                src="{{ asset('errors/403_Unauthorized.jpg') }}"
                alt="A guard indicating that administrator access is restricted"
                width="1662"
                height="946"
                class="mx-auto h-auto w-full max-w-xl rounded-xl"
            />

            <div class="mt-6 space-y-4">
                <span class="badge badge-warning badge-outline">Approval required</span>
                <h1 id="approval-heading" class="text-2xl font-bold text-base-content sm:text-3xl">Your admin access is awaiting approval</h1>
                <p class="mx-auto max-w-xl text-base-content/70">
                    Please contact the Web Development Team in the Organizing Committee of Hit The Ground
                    and share your account email below to request admin access.
                </p>
                <div class="mx-auto max-w-xl rounded-xl border border-primary/20 bg-primary/5 p-4">
                    <p class="text-sm text-base-content/70">Your account email</p>
                    <p class="mt-1 break-all font-semibold text-base-content">{{ $user->email }}</p>
                </div>
                <p class="text-sm text-base-content/70">The committee will enable your admin access after approval.</p>
            </div>

            <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row sm:flex-wrap">
                <a href="{{ route('admin.dashboard') }}" class="btn btn-primary">Check approval status</a>
                <a href="{{ route('home') }}" class="btn btn-outline">Back to home</a>
                <form method="POST" action="{{ route('company.logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-ghost w-full">Log out</button>
                </form>
            </div>
        </section>
    </x-PageContainer>
</x-layouts.public>
