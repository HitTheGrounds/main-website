<?php

namespace Tests\PostgreSQL;

use App\Models\User;
use App\Services\Tournament\ScorerAccountService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Support\PostgreSqlTestDatabaseSafety;

class ScorerAccountConcurrencyTest extends PostgreSqlTestCase
{
    use DatabaseMigrations;

    private function admin(): User
    {
        config(['tournament.enabled' => true]);
        $admin = User::factory()->create(['is_admin' => true]);
        request()->attributes->set('user', $admin);

        return $admin;
    }

    private function compete(User $actor, string $action, int $target, callable $first): string
    {
        $tag = 'htg-account-'.bin2hex(random_bytes(8));
        $code = <<<'PHP'
require 'vendor/autoload.php';
try {
    $test = new class('probe') extends \Tests\PostgreSQL\PostgreSqlTestCase {};
    $test->createApplication();
    config(['tournament.enabled' => true]);
    \Illuminate\Support\Facades\DB::selectOne("SELECT set_config('application_name', ?, false)", [$argv[4]]);
    \Illuminate\Support\Facades\DB::statement("SET lock_timeout = '8s'");
    request()->attributes->set('user', \App\Models\User::findOrFail((int) $argv[1]));
    $service = app(\App\Services\Tournament\ScorerAccountService::class);
    if ($argv[2] === 'create') {
        $service->create('Second request', ' RACE@example.com ');
    } else {
        $service->revoke((int) $argv[3]);
    }
    echo 'WROTE';
} catch (\Illuminate\Validation\ValidationException $exception) {
    echo 'REJECTED:'.array_key_first($exception->errors());
} catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
    echo 'DENIED:'.$exception->getStatusCode();
} catch (\Throwable) {
    echo 'UNEXPECTED WORKER FAILURE';
    exit(2);
}
PHP;
        $worker = new Process([PHP_BINARY, '-r', $code, (string) $actor->id, $action, (string) $target, $tag], base_path());
        $worker->setTimeout(15);
        $observer = PostgreSqlTestDatabaseSafety::connect(config('database.connections.pgsql'));
        DB::beginTransaction();
        try {
            $first();
            $worker->start();
            $statement = $observer->prepare("SELECT count(*) FROM pg_stat_activity WHERE application_name = ? AND wait_event_type = 'Lock'");
            $deadline = microtime(true) + 5;
            $waiting = false;
            do {
                $statement->execute([$tag]);
                $waiting = (int) $statement->fetchColumn() === 1;
                if ($waiting || ! $worker->isRunning()) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            $this->assertTrue($waiting, 'Competing account write must actually wait for the first transaction.');
            DB::commit();
            $worker->wait();
            $this->assertTrue($worker->isSuccessful(), 'Guarded worker failed.');

            return trim($worker->getOutput());
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            if ($worker->isRunning()) {
                $worker->stop(0);
            }
        }
    }

    public function test_simultaneous_normalized_duplicate_email_creates_only_one_account(): void
    {
        $this->admin();
        $second = $this->admin();
        $created = null;
        $result = $this->compete($second, 'create', 0, function () use (&$created) {
            $created = app(ScorerAccountService::class)->create('First request', 'race@example.com');
        });
        $this->assertSame('REJECTED:email', $result);
        $this->assertSame(1, User::where('email', 'race@example.com')->count());
        $this->assertSame('First request', $created->fresh()->name);
        $this->assertTrue($created->fresh()->is_scorer);
        $this->assertFalse($created->fresh()->is_admin);
    }

    public function test_admin_revocation_is_rechecked_by_waiting_creation(): void
    {
        $admin = $this->admin();
        $result = $this->compete($admin, 'create', 0,
            fn () => DB::table('users')->where('id', $admin->id)->update(['is_admin' => false]));
        $this->assertSame('DENIED:403', $result);
        $this->assertDatabaseMissing('users', ['email' => 'race@example.com']);
    }

    public function test_admin_revocation_is_rechecked_by_waiting_scorer_revocation(): void
    {
        $admin = $this->admin();
        $target = app(ScorerAccountService::class)->create('Scorer', 'target@example.com');
        $before = $target->getAttributes();
        $result = $this->compete($admin, 'revoke', $target->id,
            fn () => DB::table('users')->where('id', $admin->id)->update(['is_admin' => false]));
        $this->assertSame('DENIED:403', $result);
        $this->assertSame($before, $target->fresh()->getAttributes());
    }

    public function test_duplicate_revocation_returns_validation_and_preserves_account_fields(): void
    {
        $this->admin();
        $target = app(ScorerAccountService::class)->create('Scorer', 'target@example.com');
        $before = $target->getAttributes();
        $second = $this->admin();
        $result = $this->compete($second, 'revoke', $target->id,
            fn () => app(ScorerAccountService::class)->revoke($target->id));
        $this->assertSame('REJECTED:tournament', $result);
        $this->assertFalse($target->fresh()->is_scorer);
        $after = $target->fresh()->getAttributes();
        unset($before['is_scorer'], $before['updated_at'], $after['is_scorer'], $after['updated_at']);
        $this->assertSame($before, $after);
    }
}
