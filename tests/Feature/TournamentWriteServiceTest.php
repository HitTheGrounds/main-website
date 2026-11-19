<?php

namespace Tests\Feature;

use App\Services\Tournament\TournamentWriteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class TournamentWriteServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sqlite_development_writes_return_the_callback_result(): void
    {
        $id = app(TournamentWriteService::class)->run(2026, fn () => DB::table('tournament_groups')->insertGetId([
            'tournament_year' => 2026, 'name' => 'A',
        ]));
        $this->assertDatabaseHas('tournament_groups', ['id' => $id, 'name' => 'A']);
    }

    public function test_failed_callback_rolls_back_without_replaying_it(): void
    {
        $calls = 0;
        try {
            app(TournamentWriteService::class)->run(2026, function () use (&$calls) {
                $calls++;
                DB::table('tournament_groups')->insert(['tournament_year' => 2026, 'name' => 'Rollback']);
                throw new RuntimeException('Synthetic failure');
            });
            $this->fail('Expected callback failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic failure', $exception->getMessage());
        }
        $this->assertSame(1, $calls);
        $this->assertDatabaseCount('tournament_groups', 0);
    }

    public function test_invalid_year_is_rejected_before_callback(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(TournamentWriteService::class)->run(0, fn () => $this->fail('Invalid year reached callback.'));
    }
}
