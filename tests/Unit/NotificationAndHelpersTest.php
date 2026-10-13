<?php

namespace Tests\Unit;

use App\Helpers\CompanyAuth;
use App\Models\Company;
use App\Models\Team;
use App\Models\User;
use App\Notifications\VerificationCodeNotification;
use App\Services\JWTService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationAndHelpersTest extends TestCase
{
    use RefreshDatabase;

    public function test_verification_email_contains_the_code_and_expiry(): void
    {
        $mail = (new VerificationCodeNotification('482913'))->toMail(new \stdClass);
        $text = implode("\n", array_merge($mail->introLines, $mail->outroLines));

        $this->assertSame('Your Verification Code', $mail->subject);
        $this->assertStringContainsString('482913', $text);
        $this->assertStringContainsString('15 minutes', $text);
    }

    public function test_verification_email_is_sent_by_mail_only(): void
    {
        $this->assertSame(['mail'], (new VerificationCodeNotification('123456'))->via(new \stdClass));
    }

    public function test_company_auth_returns_the_request_user_when_the_middleware_set_one(): void
    {
        $user = User::factory()->create();
        request()->attributes->set('user', $user);

        $this->assertTrue(CompanyAuth::user()->is($user));
    }

    public function test_company_auth_falls_back_to_the_cookie(): void
    {
        $user = User::factory()->create();
        request()->cookies->set('company_token', (new JWTService)->generateToken($user));

        $this->assertTrue(CompanyAuth::user()->is($user));
    }

    public function test_company_auth_is_null_without_a_valid_token(): void
    {
        $this->assertNull(CompanyAuth::user());

        request()->cookies->set('company_token', 'garbage');
        $this->assertNull(CompanyAuth::user());
    }

    public function test_user_admin_flag_defaults_to_false_and_is_boolean(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->fresh()->is_admin);
        $this->assertIsBool($user->fresh()->is_admin);
    }

    public function test_user_belongs_to_a_company_and_owns_teams(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        Team::factory()->count(2)->create(['user_id' => $user->id, 'company_id' => $company->id]);

        $this->assertTrue($user->company->is($company));
        $this->assertCount(2, $user->teams);
    }

    public function test_company_team_limit_defaults_to_two(): void
    {
        $this->assertSame(2, Company::create(['name' => 'Fresh Co'])->fresh()->max_team_count);
    }
}
