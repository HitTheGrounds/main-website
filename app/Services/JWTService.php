<?php

namespace App\Services;

use App\Models\User;
use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JWTService
{
    private string $secret;

    private string $algo;

    private int $ttl;

    public function __construct()
    {
        $secret = config('app.jwt_secret');

        if (empty($secret)) {
            throw new \RuntimeException('JWT_SECRET is not configured. Please set JWT_SECRET in your .env file.');
        }

        $this->secret = base64_decode($secret);

        // Validate that the decoded secret is sufficiently long (at least 32 bytes for HS256)
        if (strlen($this->secret) < 32) {
            throw new \RuntimeException('JWT_SECRET must be at least 32 bytes when base64 decoded. Please generate a stronger secret.');
        }

        $this->algo = config('app.jwt_algo', 'HS256');
        $this->ttl = config('app.jwt_ttl', 43200); // 1 month in minutes (30 days * 24 hours * 60 minutes = 43200)
    }

    /**
     * Generate a JWT token for a user
     */
    public function generateToken(User $user): string
    {
        $issuedAt = time();
        $expiresAt = $issuedAt + ($this->ttl * 60); // Convert minutes to seconds

        $payload = [
            'iat' => $issuedAt,
            'exp' => $expiresAt,
            'iss' => config('app.url'),
            'sub' => $user->id,
            'user' => [
                'id'           => $user->id,
                'email'        => $user->email,
                'name'         => $user->name,
                'company_name' => $user->company_name,
            ],
        ];

        $token = JWT::encode($payload, $this->secret, $this->algo);

        // Store a SHA-256 hash of the token — never store the raw JWT
        $user->update([
            'jwt_token'      => hash('sha256', $token),
            'jwt_expires_at' => now()->addMinutes($this->ttl),
        ]);

        return $token;
    }

    /**
     * Validate and decode a JWT token
     */
    public function validateToken(string $token): ?object
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, $this->algo));

            // Compare the SHA-256 hash of the incoming token against the stored hash
            $user = User::where('id', $decoded->sub)
                ->where('jwt_token', hash('sha256', $token))
                ->first();

            if (! $user || ! $user->jwt_expires_at || $user->jwt_expires_at->isPast()) {
                return null;
            }

            return $decoded;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Get user from JWT token
     */
    public function getUserFromToken(string $token): ?User
    {
        $decoded = $this->validateToken($token);

        if (! $decoded) {
            return null;
        }

        return User::find($decoded->sub);
    }

    /**
     * Revoke a token (logout)
     */
    public function revokeToken(User $user): void
    {
        $user->update([
            'jwt_token' => null,
            'jwt_expires_at' => null,
        ]);
    }

    /**
     * Refresh an existing token
     */
    public function refreshToken(string $token): ?string
    {
        $user = $this->getUserFromToken($token);

        if (! $user) {
            return null;
        }

        return $this->generateToken($user);
    }
}
