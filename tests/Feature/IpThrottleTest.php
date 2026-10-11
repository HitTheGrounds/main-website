<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use Tests\TestCase;

class IpThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_code_is_limited_per_ip_even_when_rotating_emails(): void
    {
        $component = Volt::test('company-login-form');

        for ($i = 1; $i <= 10; $i++) {
            $component->set('contact_email', "nobody{$i}@example.com")->call('sendCode');
        }

        $component->set('contact_email', 'nobody11@example.com')
            ->call('sendCode')
            ->assertHasErrors(['contact_email']);

        $this->assertStringContainsString('Too many requests from your network', $component->errors()->first('contact_email'));
    }

    public function test_livewire_update_and_auth_pages_are_throttled(): void
    {
        $this->assertContains('throttle:livewire', Route::getRoutes()->getByName('livewire.update')->gatherMiddleware());
        $this->assertContains('throttle:auth-pages', Route::getRoutes()->getByName('login')->gatherMiddleware());
        $this->assertContains('throttle:auth-pages', Route::getRoutes()->getByName('admin.register')->gatherMiddleware());
    }
}
