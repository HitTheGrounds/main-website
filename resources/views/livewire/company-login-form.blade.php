<?php

use App\Models\User;
use App\Models\VerificationCode;
use App\Notifications\VerificationCodeNotification;
use App\Services\JWTService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component {
    public string $contact_email = '';
    public string $verification_code = '';

    public bool $showVerification = false;
    public ?int $userId = null;

    public function sendCode(): void
    {
        $this->validate([
            'contact_email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
        ]);

        // Rate limiting: max 3 attempts per 5 minutes per email
        $key = 'send-code:' . strtolower($this->contact_email);
        
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'contact_email' => "Too many verification code requests. Please try again in {$seconds} seconds.",
            ]);
        }

        // Find user by email
        $user = User::where('email', $this->contact_email)->first();

        if (!$user) {
            // Prevent user enumeration with generic error message
            $this->addError('contact_email', 'If an account exists with this email, a verification code will be sent.');
            RateLimiter::hit($key, 300); // 5 minutes
            return;
        }

        RateLimiter::hit($key, 300); // 5 minutes
        $this->userId = $user->id;

        // Generate 6-digit verification code (plain-text sent to user, hash stored in DB)
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store hashed verification code — never store the plain-text OTP
        VerificationCode::create([
            'user_id'    => $user->id,
            'code'       => Hash::make($code),
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

            throw ValidationException::withMessages([
                'contact_email' => 'Failed to send verification email. Please try again later or contact support.',
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

        // Fetch valid (unused, unexpired) codes for this user and check via Hash::check
        // This is necessary because bcrypt hashes cannot be queried directly
        $verificationCode = VerificationCode::where('user_id', $this->userId)
            ->where('is_used', false)
            ->where('expires_at', '>', now())
            ->get()
            ->first(fn ($record) => Hash::check($this->verification_code, $record->code));

        if (!$verificationCode) {
            RateLimiter::hit($key, 900); // 15 minutes
            $this->addError('verification_code', 'Invalid or expired verification code.');
            return;
        }
        
        // Clear rate limit on successful verification
        RateLimiter::clear($key);

        // Mark code as used
        $verificationCode->update(['is_used' => true]);

        // Get user
        $user = User::find($this->userId);

        // Mark email as verified if not already verified
        if (is_null($user->email_verified_at)) {
            $user->update(['email_verified_at' => now()]);
        }

        // Generate JWT token
        $jwtService = new JWTService();
        $token = $jwtService->generateToken($user);

        // Store JWT token in cookie (httpOnly, secure based on environment)
        $secure = config('app.env') !== 'local'; // Only use secure in production
        cookie()->queue('company_token', $token, config('app.jwt_ttl'), '/', null, $secure, true, false, 'strict');

        // Role-based redirect
        $destination = match ($user->role ?? 'company') {
            'admin'  => route('admin.dashboard'),
            'scorer' => route('scorer.dashboard'),
            default  => route('company.dashboard'),
        };

        $this->redirect($destination, navigate: true);
    }

    public function back(): void
    {
        $this->showVerification = false;
        $this->verification_code = '';
        $this->userId = null;
    }
}; ?>

<div class="flex flex-col gap-6">
    @if (!$showVerification)
        <form wire:submit="sendCode" class="flex flex-col gap-6">
            <x-mary-input
                wire:model="contact_email"
                label="{{ __('Email Address') }}"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="contact@company.com"
            />

            <div class="flex items-center justify-end">
                <x-mary-button type="submit" class="btn-primary w-full">
                    {{ __('Send Verification Code') }}
                </x-mary-button>
            </div>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-base-content/70">
            <span>{{ __("Don't have an account?") }}</span>
            <a href="{{ route('register') }}" wire:navigate class="link-primary btn-link">
                {{ __('Register here') }}
            </a>
        </div>
    @else
        <!-- Verification Form -->
        <div class="text-center">
            <h1 class="text-2xl font-bold text-primary">{{ __('Verify Your Email') }}</h1>
            <p class="mt-1 text-sm text-base-content/70">
                {{ __("We've sent a 6-digit verification code to") }} {{ $contact_email }}
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

            <div class="flex flex-col gap-3">
                <x-mary-button type="submit" class="btn-primary w-full">
                    {{ __('Verify & Login') }}
                </x-mary-button>

                <x-mary-button type="button" wire:click="back" class="btn-ghost w-full">
                    {{ __('Back to Previous Step') }}
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
