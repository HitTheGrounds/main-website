<?php

use App\Models\User;
use App\Models\Company;
use App\Models\VerificationCode;
use App\Notifications\VerificationCodeNotification;
use App\Services\JWTService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component {
    public string $company_name = '';
    public string $contact_person_name = '';
    public string $contact_email = '';
    public string $contact_person_phone = '';
    public string $verification_code = '';
    public string $turnstile_token = '';

    public bool $showVerification = false;
    public ?int $userId = null;
    public bool $showClosedModal = false;

    public function mount(): void
    {
        if (!config('registration.enabled')) {
            $this->showClosedModal = true;
        }
    }

    public function register(): void
    {
        if (!config('registration.enabled')) {
            throw ValidationException::withMessages([
                'contact_email' => 'Registrations are currently closed.',
            ]);
        }

        $validated = $this->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'contact_person_name' => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'contact_person_phone' => ['required', 'string', 'max:20'],
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

        // Rate limiting: max 3 registration attempts per 5 minutes per email
        $key = 'register:' . strtolower($validated['contact_email']);
        
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'contact_email' => "Too many registration attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        RateLimiter::hit($key, 300); // 5 minutes

        // Create company
        $company = Company::create([
            'name' => $validated['company_name'],
            'phone' => $validated['contact_person_phone'],
        ]);

        // Create user (company representative)
        $user = User::create([
            'name' => $validated['contact_person_name'],
            'email' => $validated['contact_email'],
            'company_id' => $company->id,
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

            // Clean up created records
            $user->delete();
            $company->delete();

            throw ValidationException::withMessages([
                'contact_email' => 'Failed to send verification email. Please check your email address and try again later.',
            ]);
        }

        $this->showVerification = true;
    }

    public function verify(): void
    {
        $this->validate([
            'verification_code' => ['required', 'string', 'size:6'],
        ]);

        // Rate limiting: max 5 verification attempts per 15 minutes per user
        $key = 'verify-code:' . $this->userId;
        
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'verification_code' => "Too many failed attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        $verificationCode = VerificationCode::where('user_id', $this->userId)
            ->where('code', $this->verification_code)
            ->where('is_used', false)
            ->where('expires_at', '>', now())
            ->first();

        if (!$verificationCode) {
            RateLimiter::hit($key, 900); // 15 minutes
            $this->addError('verification_code', 'Invalid or expired verification code.');
            return;
        }
        
        // Clear rate limit on successful verification
        RateLimiter::clear($key);

        // Mark code as used
        $verificationCode->update(['is_used' => true]);

        // Mark email as verified
        $user = User::find($this->userId);
        $user->update(['email_verified_at' => now()]);

        // Generate JWT token
        $jwtService = new JWTService();
        $token = $jwtService->generateToken($user);

        // Store JWT token in cookie (httpOnly, secure based on environment)
        $secure = config('app.env') !== 'local'; // Only use secure in production
        cookie()->queue('company_token', $token, config('app.jwt_ttl'), '/', null, $secure, true, false, 'strict');

        // Redirect to dashboard
        $this->redirect('/company/dashboard', navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    {{-- Registration Closed Modal --}}
    <x-mary-modal wire:model="showClosedModal" title="Registrations Closed" class="backdrop-blur">
        <div class="flex flex-col items-center gap-4 py-4">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-warning" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <p class="text-center text-base-content">
                Company registrations are currently closed. Please check back later.
            </p>
        </div>

        <x-slot:actions>
            <x-mary-button label="Go to Login" link="{{ route('login') }}" class="btn-primary" />
        </x-slot:actions>
    </x-mary-modal>

    @if (!$showVerification)
        <form wire:submit="register" class="flex flex-col gap-6" id="company-registration-form">
            <x-mary-input
                wire:model="company_name"
                label="{{ __('Company Name') }}"
                type="text"
                required
                autofocus
                placeholder="Enter company name"
            />

            <x-mary-input
                wire:model="contact_person_name"
                label="{{ __('Contact Person Name') }}"
                type="text"
                required
                placeholder="Enter contact person name"
            />

            <x-mary-input
                wire:model="contact_email"
                label="{{ __('Contact Email') }}"
                type="email"
                required
                autocomplete="email"
                placeholder="contact@company.com"
            />

            <x-mary-input
                wire:model="contact_person_phone"
                label="{{ __('Contact Person Phone') }}"
                type="tel"
                required
                placeholder="+1 (555) 123-4567"
            />

            @error('turnstile_token')
                <x-mary-alert icon="o-exclamation-triangle" class="alert-error">
                    {{ $message }}
                </x-mary-alert>
            @enderror

            <div class="flex justify-center">
                <div id="turnstile-widget"></div>
            </div>
            <input type="hidden" wire:model="turnstile_token" id="turnstile-token">

            <div class="flex items-center justify-end">
                <x-mary-button type="submit" class="btn-primary w-full">
                    {{ __('Register') }}
                </x-mary-button>
            </div>
        </form>

        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?onload=onloadTurnstileCallback" async defer></script>
        <script>
            window.onloadTurnstileCallback = function () {
                turnstile.render('#turnstile-widget', {
                    sitekey: '{{ config('turnstile.site_key') }}',
                    theme: 'light',
                    size: 'normal',
                    appearance: 'always',
                    callback: function(token) {
                        document.getElementById('turnstile-token').value = token;
                        @this.set('turnstile_token', token);
                    }
                });
            };
        </script>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-base-content/70">
            <span>{{ __('Already have an account?') }}</span>
            <a href="{{ route('login') }}" wire:navigate class="link-primary btn-link">
                {{ __('Login here') }}
            </a>
        </div>
    @else
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
