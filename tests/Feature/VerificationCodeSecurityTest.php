<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Volt\Volt;
use Tests\TestCase;

class VerificationCodeSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        RateLimiter::clear('send-code:test@example.com');
        RateLimiter::clear('verify-code:1');
        RateLimiter::clear('register:test@example.com');
    }

    public function test_verification_codes_are_stored_in_database(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);

        $code = '123456';

        VerificationCode::create([
            'user_id' => $user->id,
            'code' => \Illuminate\Support\Facades\Hash::make($code),
            'expires_at' => now()->addMinutes(15),
        ]);

        $storedCode = VerificationCode::where('user_id', $user->id)->first();

        // Verify code is stored as a hash
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check($code, $storedCode->code));
    }

    public function test_send_code_rate_limiting_prevents_spam(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);

        // Hit the rate limit
        for ($i = 0; $i < 3; $i++) {
            RateLimiter::hit('send-code:test@example.com', 300);
        }

        // Next attempt should be rate limited
        $component = Volt::test('company-login-form')
            ->set('contact_email', 'test@example.com')
            ->call('sendCode')
            ->assertHasErrors(['contact_email']);
    }

    public function test_verify_code_rate_limiting_prevents_brute_force(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);

        $code = '123456';

        VerificationCode::create([
            'user_id' => $user->id,
            'code' => \Illuminate\Support\Facades\Hash::make($code),
            'expires_at' => now()->addMinutes(15),
        ]);

        // Hit the rate limit
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit('verify-code:'.$user->id, 900);
        }

        // Next attempt should be rate limited
        $component = Volt::test('company-login-form')
            ->set('userId', $user->id)
            ->set('showVerification', true)
            ->set('verification_code', '000000')
            ->call('verify')
            ->assertHasErrors(['verification_code']);
    }

    public function test_expired_codes_cannot_be_used(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);

        $code = '123456';

        // Create expired code
        VerificationCode::create([
            'user_id' => $user->id,
            'code' => \Illuminate\Support\Facades\Hash::make($code),
            'expires_at' => now()->subMinutes(1), // Expired 1 minute ago
        ]);

        Volt::test('company-login-form')
            ->set('userId', $user->id)
            ->set('showVerification', true)
            ->set('verification_code', $code)
            ->call('verify')
            ->assertHasErrors(['verification_code']);
    }

    public function test_used_codes_cannot_be_reused(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);

        $code = '123456';

        // Create used code
        VerificationCode::create([
            'user_id' => $user->id,
            'code' => \Illuminate\Support\Facades\Hash::make($code),
            'expires_at' => now()->addMinutes(15),
            'is_used' => true,
        ]);

        Volt::test('company-login-form')
            ->set('userId', $user->id)
            ->set('showVerification', true)
            ->set('verification_code', $code)
            ->call('verify')
            ->assertHasErrors(['verification_code']);
    }

    public function test_user_enumeration_is_prevented_with_generic_error(): void
    {
        // Test with non-existent user
        Volt::test('company-login-form')
            ->set('contact_email', 'nonexistent@example.com')
            ->call('sendCode')
            ->assertHasErrors(['contact_email' => 'If an account exists with this email, a verification code will be sent.']);
    }

    public function test_registration_rate_limiting_prevents_spam(): void
    {
        // Hit the rate limit for a specific email
        for ($i = 0; $i < 3; $i++) {
            RateLimiter::hit('register:spam@example.com', 300);
        }

        // Next registration attempt with same email should be rate limited
        $component = Volt::test('company-registration-form')
            ->set('company_name', 'Test Company')
            ->set('contact_person_name', 'Test Person')
            ->set('contact_email', 'spam@example.com')
            ->set('contact_person_phone', '+1234567890')
            ->set('turnstile_token', 'dummy-token')
            ->call('register')
            ->assertHasErrors(['contact_email']);
    }

    public function test_cleanup_command_removes_expired_codes(): void
    {
        $user = User::factory()->create();

        // Create expired code
        $expiredCode = VerificationCode::create([
            'user_id' => $user->id,
            'code' => \Illuminate\Support\Facades\Hash::make('111111'),
            'expires_at' => now()->subMinutes(1),
        ]);

        // Create used code
        $usedCode = VerificationCode::create([
            'user_id' => $user->id,
            'code' => \Illuminate\Support\Facades\Hash::make('222222'),
            'expires_at' => now()->addMinutes(15),
            'is_used' => true,
        ]);

        // Create valid code
        $validCode = VerificationCode::create([
            'user_id' => $user->id,
            'code' => \Illuminate\Support\Facades\Hash::make('333333'),
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->artisan('verification-codes:cleanup')
            ->expectsOutput('Deleted 2 expired/used verification codes.')
            ->assertExitCode(0);

        // Verify only valid code remains
        $this->assertDatabaseMissing('verification_codes', ['id' => $expiredCode->id]);
        $this->assertDatabaseMissing('verification_codes', ['id' => $usedCode->id]);
        $this->assertDatabaseHas('verification_codes', ['id' => $validCode->id]);
    }
}
