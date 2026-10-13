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
