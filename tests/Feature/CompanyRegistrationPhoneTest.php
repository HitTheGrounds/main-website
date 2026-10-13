<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CompanyRegistrationPhoneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['registration.enabled' => true, 'cache.default' => 'array']);
        Notification::fake();
        Http::preventStrayRequests();
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);
    }

    #[DataProvider('invalidPhones')]
    public function test_invalid_phone_prevents_registration(string $phone): void
    {
        $this->registration($phone)
            ->call('register')
            ->assertHasErrors(['contact_person_phone'])
            ->assertSet('showVerification', false);

        $this->assertDatabaseCount('companies', 0);
        $this->assertDatabaseCount('users', 0);
        Http::assertNothingSent();
        Notification::assertNothingSent();
    }

    public static function invalidPhones(): array
    {
        return array_map(fn ($phone) => [$phone], [
            '', 'not-a-phone', '123', '077123456', '07712345678',
            '+9477123456abc', '0771234567 ext 12', '+01234567890',
            '0000000000', '+1234567890123456', '0771234567+123',
        ]);
    }

    #[DataProvider('validPhones')]
    public function test_valid_phone_is_saved_without_formatting(string $phone, string $stored): void
    {
        $this->registration($phone)
            ->call('register')
            ->assertHasNoErrors()
            ->assertSet('showVerification', true);

        $this->assertDatabaseHas('companies', ['phone' => $stored]);
    }

    public static function validPhones(): array
    {
        return [
            ['0771234567', '0771234567'],
            ['+94771234567', '+94771234567'],
            [' 077 123-4567 ', '0771234567'],
            ['+1 (555) 123-4567', '+15551234567'],
            ['+123456789012345', '+123456789012345'],
        ];
    }

    private function registration(string $phone)
    {
        return Volt::test('company-registration-form')
            ->set('company_name', 'Acme')
            ->set('contact_person_name', 'Alex')
            ->set('contact_email', 'alex@acme.test')
            ->set('contact_person_phone', $phone)
            ->set('turnstile_token', 'token');
    }
}
