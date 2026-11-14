<?php

use App\Services\Tournament\ScorerAccountService;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $name = '';
    public string $email = '';
    public string $notice = '';

    public function with(): array
    {
        return ['scorers' => app(ScorerAccountService::class)->listing()];
    }

    public function createScorer(): void
    {
        $this->resetErrorBag();
        $this->notice = '';
        $user = app(ScorerAccountService::class)->create($this->name, $this->email);
        $this->reset('name', 'email');
        $this->resetPage();
        $this->notice = 'Scorer account created for '.$user->email.'. They can sign in using the existing email-code login.';
    }

    public function revokeScorer(int $userId): void
    {
        $this->resetErrorBag();
        $this->notice = '';
        app(ScorerAccountService::class)->revoke($userId);
        $this->resetPage();
        $this->notice = 'Scorer access removed. The account and recorded results have been kept.';
    }
}; ?>

<div class="space-y-6">
    <div class="space-y-2 text-center">
        <h1 class="text-2xl font-bold sm:text-3xl">Scorer accounts</h1>
        <p class="text-sm text-base-content/70">Create a separate account for each scorer. Existing accounts will not be converted.</p>
    </div>

    <div aria-live="polite">
        @if($notice)
            <p role="status" class="alert alert-success">{{ $notice }}</p>
        @endif
        @error('tournament')
            <p role="alert" class="alert alert-error">{{ $message }}</p>
        @enderror
    </div>

    <section class="card border border-base-300 bg-base-100 shadow-xs" aria-labelledby="create-scorer-heading">
        <div class="card-body">
            <h2 id="create-scorer-heading" class="text-xl font-bold text-center">Create scorer account</h2>
            <form wire:submit="createScorer" class="space-y-4">
                <div>
                    <label for="scorer-name" class="block text-sm font-semibold mb-2">Name</label>
                    <input id="scorer-name" wire:model="name" type="text" maxlength="255" required autocomplete="name" class="input input-bordered w-full" aria-describedby="scorer-name-error">
                    <p id="scorer-name-error" role="alert" class="mt-1 text-sm text-error">@error('name'){{ $message }}@enderror</p>
                </div>
                <div>
                    <label for="scorer-email" class="block text-sm font-semibold mb-2">Email address</label>
                    <input id="scorer-email" wire:model="email" type="email" maxlength="255" required autocomplete="email" class="input input-bordered w-full" aria-describedby="scorer-email-error">
                    <p id="scorer-email-error" role="alert" class="mt-1 text-sm text-error">@error('email'){{ $message }}@enderror</p>
                </div>
                <p class="text-sm text-base-content/70">This account will have scorer access only, with no company association or admin access. No password or email code is created here. The scorer requests their code from the existing Login page.</p>
                <button type="submit" wire:loading.attr="disabled" class="btn btn-primary w-full sm:w-auto">Create scorer</button>
                <p wire:loading wire:target="createScorer" role="status" class="text-sm text-base-content/70">Creating account…</p>
            </form>
        </div>
    </section>

    <section class="card border border-base-300 bg-base-100 shadow-xs" aria-labelledby="scorer-list-heading">
        <div class="card-body space-y-4">
            <h2 id="scorer-list-heading" class="text-xl font-bold text-center">Active scorer accounts</h2>
            <p class="text-sm text-base-content/70 text-center">{{ $scorers->total() }} accounts with scorer access. Removing access prevents future scoring requests; it does not delete an account or its recorded results.</p>
            <div class="overflow-x-auto">
                <table class="table table-zebra">
                    <caption class="sr-only">Dedicated accounts with active scorer permission</caption>
                    <thead>
                        <tr><th scope="col">Name</th><th scope="col">Email</th><th scope="col">Email verification</th><th scope="col">Actions</th></tr>
                    </thead>
                    <tbody>
                        @forelse($scorers as $scorer)
                            <tr wire:key="scorer-account-{{ $scorer->id }}">
                                <td class="font-semibold">{{ $scorer->name }}</td>
                                <td class="break-all">{{ $scorer->email }}</td>
                                <td>
                                    @if($scorer->email_verified_at)
                                        <span class="badge badge-success badge-sm">Verified</span>
                                    @else
                                        <span class="badge badge-ghost badge-sm">Not yet verified</span>
                                    @endif
                                </td>
                                <td>
                                    <button type="button" wire:click="revokeScorer({{ $scorer->id }})" wire:confirm="Remove scorer access? The account and recorded results will be kept." wire:loading.attr="disabled" class="btn btn-outline btn-error btn-sm" aria-label="Remove scorer access for {{ $scorer->email }}">Remove access</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-base-content/70">No active scorer accounts.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $scorers->links() }}
        </div>
    </section>
</div>
