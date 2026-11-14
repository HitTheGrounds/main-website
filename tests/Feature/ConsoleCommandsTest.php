<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsoleCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_set_admin_promotes_a_user(): void
    {
        $user = User::factory()->create(['email' => 'boss@example.com', 'is_admin' => false]);

        $this->artisan('user:set-admin', ['email' => 'boss@example.com'])->assertExitCode(0);

        $this->assertTrue($user->fresh()->is_admin);
    }

    public function test_set_admin_can_revoke(): void
    {
        $user = User::factory()->create(['email' => 'boss@example.com', 'is_admin' => true]);

        $this->artisan('user:set-admin', ['email' => 'boss@example.com', '--revoke' => true])->assertExitCode(0);

        $this->assertFalse($user->fresh()->is_admin);
    }

    public function test_set_admin_fails_for_an_unknown_email(): void
    {
        $this->artisan('user:set-admin', ['email' => 'nobody@example.com'])->assertExitCode(1);
    }

    public function test_set_admin_rejects_scorers_without_changing_any_account_fields(): void
    {
        config(['tournament.enabled' => false]);
        foreach ([false, true] as $admin) {
            $user = User::factory()->create(['company_id' => null, 'is_admin' => $admin]);
            $user->is_scorer = true;
            $user->save();
            $before = $user->fresh()->getAttributes();
            $this->artisan('user:set-admin', ['email' => $user->email])
                ->expectsOutput('Cannot promote scorer accounts to admin. Use a separate admin account.')
                ->assertExitCode(1);
            $this->assertSame($before, $user->fresh()->getAttributes());
        }
    }

    public function test_set_admin_can_revoke_an_ambiguous_scorer_admin_combination(): void
    {
        $user = User::factory()->create(['company_id' => null, 'is_admin' => true]);
        $user->is_scorer = true;
        $user->save();
        $this->artisan('user:set-admin', ['email' => $user->email, '--revoke' => true])->assertExitCode(0);
        $this->assertFalse($user->fresh()->is_admin);
        $this->assertTrue($user->fresh()->is_scorer);
        $this->assertNull($user->fresh()->company_id);
    }

    public function test_set_admin_preserves_existing_company_associations(): void
    {
        $company = \App\Models\Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id, 'is_admin' => true]);
        $this->artisan('user:set-admin', ['email' => $user->email])->assertExitCode(0);
        $this->assertTrue($user->fresh()->is_admin);
        $this->assertSame($company->id, $user->fresh()->company_id);
        $this->assertFalse($user->fresh()->is_scorer);
    }

    public function test_cleanup_deletes_only_expired_and_used_codes(): void
    {
        $user = User::factory()->create();
        $keep = VerificationCode::create(['user_id' => $user->id, 'code' => '111111', 'expires_at' => now()->addMinutes(5)]);
        VerificationCode::create(['user_id' => $user->id, 'code' => '222222', 'expires_at' => now()->subMinute()]);
        VerificationCode::create(['user_id' => $user->id, 'code' => '333333', 'expires_at' => now()->addMinutes(5), 'is_used' => true]);

        $this->artisan('verification-codes:cleanup')->assertExitCode(0);

        $this->assertSame([$keep->id], VerificationCode::pluck('id')->all());
    }

    public function test_verification_code_validity(): void
    {
        $user = User::factory()->create();

        $fresh = VerificationCode::create(['user_id' => $user->id, 'code' => '123456', 'expires_at' => now()->addMinutes(5)]);
        $expired = VerificationCode::create(['user_id' => $user->id, 'code' => '123457', 'expires_at' => now()->subMinute()]);
        $used = VerificationCode::create(['user_id' => $user->id, 'code' => '123458', 'expires_at' => now()->addMinutes(5), 'is_used' => true]);

        $this->assertTrue($fresh->isValid());
        $this->assertFalse($expired->isValid());
        $this->assertFalse($used->isValid());
    }
}
