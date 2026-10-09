<?php

use App\Models\User;
use App\Models\VerificationCode;
use App\Notifications\VerificationCodeNotification;
use App\Services\JWTService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component {
    public string $name = '';
    public string $email = '';
    public string $verification_code = '';
    public string $turnstile_token = '';

    public bool $showVerification = false;
    public ?int $userId = null;

    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'turnstile_token' => ['required', 'string'],
        ]);

        // Verify Turnstile token
        $response = \Illuminate\Support\Facades\Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'secret' => config('turnstile.secret_key'),
            'response' => $validated['turnstile_token'],
        ]);

        if (!$response->successful() || !$response->json('success')) {
            throw ValidationException::withMessages([
                'turnstile_token' => 'Captcha verification failed. Please try again.',
            ]);
        }

        // Rate limiting: max 3 attempts per 5 minutes per email
        $key = 'admin-register:' . strtolower($validated['email']);
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'email' => "Too many registration attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        RateLimiter::hit($key, 300); // 5 minutes

        // Create admin user (no company)
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        // Explicitly set company_id and is_admin using update to bypass fillable restriction
        $user->update([
            'company_id' => null,
            'is_admin' => false,
        ]);

        $this->userId = $user->id;

        // Generate 6-digit verification code
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store verification code
        VerificationCode::create([
            'user_id' => $user->id,
            'code' => $code,
            'expires_at' => now()->addMinutes(15),
        ]);

        // Send verification email
        try {
            Notification::route('mail', $user->email)
                ->notify(new VerificationCodeNotification($code));
        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Failed to send verification email', [
                'email' => $user->email,
                'error' => $e->getMessage()
            ]);

            // Clean up created user
            $user->delete();

            throw ValidationException::withMessages([
                'email' => 'Failed to send verification email. Please check your email address and try again later.',
            ]);
        }

        $this->showVerification = true;
    }

    public function verify(): void
    {
        $this->validate([
            'verification_code' => ['required', 'string', 'size:6'],
        ]);

        $verificationCode = VerificationCode::where('user_id', $this->userId)
            ->where('code', $this->verification_code)
            ->where('is_used', false)
            ->where('expires_at', '>', now())
            ->first();

        if (!$verificationCode) {
            $this->addError('verification_code', 'Invalid or expired verification code.');
            return;
        }

        // Mark code as used
        $verificationCode->update(['is_used' => true]);

        // Mark email as verified
        $user = User::find($this->userId);
        $user->update(['email_verified_at' => now()]);

        // Generate JWT token
        $jwtService = new JWTService();
        $token = $jwtService->generateToken($user);

        // Store JWT token in cookie (httpOnly, secure)
        cookie()->queue('company_token', $token, config('app.jwt_ttl'), '/', null, true, true, false, 'strict');

        // Redirect to admin dashboard
        $this->redirect('/admin/dashboard', navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    @if (!$showVerification)
        <form wire:submit="register" class="flex flex-col gap-6" id="admin-registration-form">
            <x-mary-input
                wire:model="name"
                label="{{ __('Full Name') }}"
                type="text"
                required
                autofocus
                placeholder="Enter your full name"
            />

            <x-mary-input
                wire:model="email"
                label="{{ __('Email') }}"
                type="email"
                required
                autocomplete="email"
                placeholder="admin@example.com"
            />

            @error('turnstile_token')
                <x-mary-alert icon="o-exclamation-triangle" class="alert-error">
                    {{ $message }}
                </x-mary-alert>
            @enderror

            <div class="flex justify-center">
                <div id="admin-turnstile-widget"></div>
            </div>
            <input type="hidden" wire:model="turnstile_token" id="admin-turnstile-token">

            <div class="flex items-center justify-end">
                <x-mary-button type="submit" class="btn-primary w-full">
                    {{ __('Register as Admin') }}
                </x-mary-button>
            </div>
        </form>

        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?onload=onloadAdminTurnstileCallback" async defer></script>
        <script>
            window.onloadAdminTurnstileCallback = function () {
                turnstile.render('#admin-turnstile-widget', {
                    sitekey: '{{ config('turnstile.site_key') }}',
                    theme: 'light',
                    size: 'normal',
                    appearance: 'always',
                    callback: function(token) {
                        document.getElementById('admin-turnstile-token').value = token;
                        @this.set('turnstile_token', token);
                    }
                });
            };
        </script>
    @else
        <!-- Verification Form -->
        <div class="text-center">
            <h1 class="text-2xl font-bold text-primary-ink">{{ __('Verify Your Email') }}</h1>
            <p class="mt-1 text-sm text-base-content/70">
                {{ __("We've sent a 6-digit verification code to") }} {{ $email }}
            </p>
        </div>

        <form wire:submit="verify" class="flex flex-col gap-6">
            <x-mary-input
                wire:model="verification_code"
                label="{{ __('Verification Code') }}"
                type="text"
                required
                autofocus
                placeholder="000000"
                maxlength="6"
                class="text-center text-2xl tracking-widest"
            />

            <div class="flex items-center justify-end">
                <x-mary-button type="submit" class="btn-primary w-full">
                    {{ __('Verify & Continue') }}
                </x-mary-button>
            </div>
        </form>

        <div class="text-center">
            <p class="text-sm text-base-content/70">
                {{ __('Code expires in 15 minutes') }}
            </p>
        </div>
    @endif
</div>
