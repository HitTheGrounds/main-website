<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Support\ScorerWriteThrottle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ScorerWriteThrottleTest extends TestCase
{
    use RefreshDatabase;

    private function scorer(): User
    {
        $user = User::factory()->create(['company_id' => null, 'is_admin' => false]);
        $user->is_scorer = true;
        $user->save();

        return $user;
    }

    public function test_account_limit_is_shared_across_ips_and_expires_after_one_minute(): void
    {
        config(['tournament.enabled' => true, 'tournament.writes_per_minute' => 2]);
        $user = $this->scorer();
        request()->attributes->set('user', $user);
        ScorerWriteThrottle::hit();
        request()->server->set('REMOTE_ADDR', '192.0.2.9');
        ScorerWriteThrottle::hit();
        try {
            ScorerWriteThrottle::hit();
            $this->fail('Expected account write limit.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Please try again in', $exception->errors()['tournament'][0]);
        }
        $this->travel(61)->seconds();
        ScorerWriteThrottle::hit();
        $this->assertSame(1, RateLimiter::attempts('tournament:scorer-write:'.$user->id));
    }

    public function test_five_scorers_on_one_network_have_separate_allowances(): void
    {
        config(['tournament.enabled' => true, 'tournament.writes_per_minute' => 1]);
        request()->server->set('REMOTE_ADDR', '192.0.2.10');
        for ($i = 0; $i < 5; $i++) {
            $user = $this->scorer();
            request()->attributes->set('user', $user);
            ScorerWriteThrottle::hit();
            $this->assertSame(1, RateLimiter::attempts('tournament:scorer-write:'.$user->id));
        }
    }

    public static function unauthorizedAccounts(): array
    {
        return [['guest'], ['representative'], ['admin'], ['pending']];
    }

    #[DataProvider('unauthorizedAccounts')]
    public function test_non_scorers_cannot_consume_a_write_allowance(string $role): void
    {
        config(['tournament.enabled' => true]);
        $user = $role === 'guest' ? null : User::factory()->create([
            'is_admin' => $role === 'admin',
            'company_id' => $role === 'representative' ? Company::factory()->create()->id : null,
        ]);
        request()->attributes->set('user', $user);
        try {
            ScorerWriteThrottle::hit();
            $this->fail('Expected scorer authorization rejection.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        if ($user) {
            $this->assertSame(0, RateLimiter::attempts('tournament:scorer-write:'.$user->id));
        }
    }

    public function test_disabled_feature_rejects_writes_without_consuming_allowance(): void
    {
        config(['tournament.enabled' => false]);
        $user = $this->scorer();
        request()->attributes->set('user', $user);
        try {
            ScorerWriteThrottle::hit();
            $this->fail('Expected disabled-feature rejection.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
        $this->assertSame(0, RateLimiter::attempts('tournament:scorer-write:'.$user->id));
    }
}
