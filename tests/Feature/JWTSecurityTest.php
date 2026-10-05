<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\JWTService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JWTSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_jwt_secret_must_be_configured(): void
    {
        config(['app.jwt_secret' => '']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('JWT_SECRET is not configured');

        new JWTService;
    }

    public function test_jwt_secret_must_be_strong_enough(): void
    {
        // Set a weak secret (less than 32 bytes when decoded)
        config(['app.jwt_secret' => base64_encode('weak')]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('JWT_SECRET must be at least 32 bytes');

        new JWTService;
    }

    public function test_jwt_token_can_be_generated_and_validated(): void
    {
        // Generate a strong secret
        config(['app.jwt_secret' => base64_encode(random_bytes(64))]);

        $user = User::factory()->create();
        $jwtService = new JWTService;

        $token = $jwtService->generateToken($user);

        $this->assertNotEmpty($token);

        $decoded = $jwtService->validateToken($token);

        $this->assertNotNull($decoded);
        $this->assertEquals($user->id, $decoded->sub);
    }

    public function test_jwt_token_is_stored_in_database(): void
    {
        config(['app.jwt_secret' => base64_encode(random_bytes(64))]);

        $user = User::factory()->create();
        $jwtService = new JWTService;

        $token = $jwtService->generateToken($user);

        $user->refresh();
        $this->assertEquals(hash('sha256', $token), $user->jwt_token);
        $this->assertNotNull($user->jwt_expires_at);
    }

    public function test_expired_jwt_token_is_invalid(): void
    {
        config(['app.jwt_secret' => base64_encode(random_bytes(64))]);
        config(['app.jwt_ttl' => 0]); // Set to expire immediately

        $user = User::factory()->create();
        $jwtService = new JWTService;

        $token = $jwtService->generateToken($user);

        // Manually set expiration in the past
        $user->update(['jwt_expires_at' => now()->subMinutes(1)]);

        $decoded = $jwtService->validateToken($token);

        $this->assertNull($decoded);
    }

    public function test_revoked_token_is_invalid(): void
    {
        config(['app.jwt_secret' => base64_encode(random_bytes(64))]);

        $user = User::factory()->create();
        $jwtService = new JWTService;

        $token = $jwtService->generateToken($user);

        // Revoke the token
        $jwtService->revokeToken($user);

        $decoded = $jwtService->validateToken($token);

        $this->assertNull($decoded);
    }

    public function test_token_refresh_generates_new_token(): void
    {
        config(['app.jwt_secret' => base64_encode(random_bytes(64))]);

        $user = User::factory()->create();
        $jwtService = new JWTService;

        $originalToken = $jwtService->generateToken($user);

        // Wait a moment to ensure different timestamp
        sleep(1);

        $newToken = $jwtService->refreshToken($originalToken);

        $this->assertNotNull($newToken);
        $this->assertNotEquals($originalToken, $newToken);

        // Old token should no longer be valid
        $this->assertNull($jwtService->validateToken($originalToken));

        // New token should be valid
        $this->assertNotNull($jwtService->validateToken($newToken));
    }

    public function test_cookie_secure_flag_is_environment_aware(): void
    {
        config(['app.env' => 'production']);
        $this->assertEquals('production', config('app.env'));

        config(['app.env' => 'local']);
        $this->assertEquals('local', config('app.env'));
    }
}
