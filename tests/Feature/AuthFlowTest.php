<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Models\VerificationCode;
use App\Notifications\VerificationCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        RateLimiter::clear('verify-code:1');
        Http::preventStrayRequests();
    }

    private function captcha(bool $passes): void
    {
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => $passes])]);
    }

    private function codeFor(User $user, string $code = '123456'): void
    {
        VerificationCode::create(['user_id' => $user->id, 'code' => $code, 'expires_at' => now()->addMinutes(15)]);
    }

    public function test_requesting_a_code_stores_it_and_sends_the_notification(): void
    {
        $user = User::factory()->create(['email' => 'devil@hell.com']);

        Volt::test('company-login-form')
            ->set('contact_email', 'devil@hell.com')
            ->call('sendCode')
            ->assertHasNoErrors()
            ->assertSet('showVerification', true);

        $this->assertDatabaseHas('verification_codes', ['user_id' => $user->id, 'is_used' => false]);
        Notification::assertSentOnDemand(VerificationCodeNotification::class);
    }

    public function test_invalid_email_is_rejected(): void
    {
        Volt::test('company-login-form')->set('contact_email', 'not-an-email')->call('sendCode')->assertHasErrors(['contact_email']);
    }

    public function test_company_user_lands_on_the_company_dashboard_after_login(): void
    {
        $user = User::factory()->create(['company_id' => Company::factory()->create()->id]);
        $this->codeFor($user);

        Volt::test('company-login-form')
            ->set('userId', $user->id)
            ->set('verification_code', '123456')
            ->call('verify')
            ->assertRedirect('/company/dashboard');

        $this->assertNotNull($user->fresh()->jwt_token);
        $this->assertTrue(VerificationCode::first()->is_used);
    }

    public function test_admin_lands_on_the_admin_dashboard_after_login(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->codeFor($admin);

        Volt::test('company-login-form')
            ->set('userId', $admin->id)
            ->set('verification_code', '123456')
            ->call('verify')
            ->assertRedirect('/admin/dashboard');
    }

    public function test_login_marks_the_email_as_verified(): void
    {
        $user = User::factory()->unverified()->create();
        $this->codeFor($user);

        Volt::test('company-login-form')->set('userId', $user->id)->set('verification_code', '123456')->call('verify');

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_wrong_code_does_not_log_in(): void
    {
        $user = User::factory()->create();
        $this->codeFor($user);

        Volt::test('company-login-form')
            ->set('userId', $user->id)
            ->set('verification_code', '000000')
            ->call('verify')
            ->assertHasErrors(['verification_code'])
            ->assertNoRedirect();

        $this->assertNull($user->fresh()->jwt_token);
    }

    public function test_a_code_must_be_exactly_six_characters(): void
    {
        Volt::test('company-login-form')->set('verification_code', '12ab')->call('verify')->assertHasErrors(['verification_code']);
    }

    public function test_one_users_code_does_not_work_for_another_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->codeFor($owner);

        Volt::test('company-login-form')->set('userId', $other->id)->set('verification_code', '123456')->call('verify')->assertHasErrors(['verification_code']);
    }

    public function test_company_registration_creates_a_company_and_user_and_sends_a_code(): void
    {
        $this->captcha(true);
        Volt::test('company-registration-form')
            ->set('company_name', 'Acme Cricket')
            ->set('contact_person_name', 'Alex Perera')
            ->set('contact_email', 'alex@acme.test')
            ->set('contact_person_phone', '0771234567')
            ->set('turnstile_token', 'token')
            ->call('register')
            ->assertHasNoErrors()
            ->assertSet('showVerification', true);

        $user = User::where('email', 'alex@acme.test')->firstOrFail();
        $this->assertFalse($user->is_admin);
        $this->assertSame('Acme Cricket', $user->company->name);
        Notification::assertSentOnDemand(VerificationCodeNotification::class);
    }

    public function test_company_registration_needs_a_captcha_that_passes(): void
    {
        $this->captcha(false);

        Volt::test('company-registration-form')
            ->set('company_name', 'Acme')
            ->set('contact_person_name', 'Alex')
            ->set('contact_email', 'alex@acme.test')
            ->set('contact_person_phone', '0771234567')
            ->set('turnstile_token', 'bad')
            ->call('register')
            ->assertHasErrors(['turnstile_token']);

        $this->assertDatabaseMissing('users', ['email' => 'alex@acme.test']);
    }

    public function test_company_registration_rejects_an_email_that_already_exists(): void
    {
        $this->captcha(true);
        User::factory()->create(['email' => 'taken@acme.test']);

        Volt::test('company-registration-form')
            ->set('company_name', 'Acme')
            ->set('contact_person_name', 'Alex')
            ->set('contact_email', 'taken@acme.test')
            ->set('contact_person_phone', '0771234567')
            ->set('turnstile_token', 'token')
            ->call('register')
            ->assertHasErrors(['contact_email']);
    }

    public function test_company_registration_can_be_closed(): void
    {
        $this->captcha(true);
        config(['registration.enabled' => false]);

        Volt::test('company-registration-form')
            ->set('company_name', 'Acme')
            ->set('contact_person_name', 'Alex')
            ->set('contact_email', 'alex@acme.test')
            ->set('contact_person_phone', '0771234567')
            ->set('turnstile_token', 'token')
            ->call('register')
            ->assertHasErrors(['contact_email']);

        $this->assertDatabaseMissing('users', ['email' => 'alex@acme.test']);
    }

    public function test_admin_registration_never_creates_an_admin_or_a_company(): void
    {
        $this->captcha(true);
        Volt::test('admin-registration-form')
            ->set('name', 'Sam Admin')
            ->set('email', 'sam@cse.test')
            ->set('turnstile_token', 'token')
            ->call('register')
            ->assertHasNoErrors();

        $user = User::where('email', 'sam@cse.test')->firstOrFail();
        $this->assertFalse($user->is_admin);
        $this->assertNull($user->company_id);
    }
}
