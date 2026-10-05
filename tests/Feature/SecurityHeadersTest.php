<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_security_headers_are_present(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);

        // Assert CSP header is present
        $response->assertHeader('Content-Security-Policy');

        // Assert X-Powered-By is absent
        $response->assertHeaderMissing('X-Powered-By');
        $response->assertHeaderMissing('Server');

        // Assert all other security headers are set
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
    }
}
