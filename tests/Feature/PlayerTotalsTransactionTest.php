<?php

namespace Tests\Feature;

use App\Services\Tournament\PlayerTotalsService;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class PlayerTotalsTransactionTest extends TestCase
{
    public function test_derived_writer_requires_the_callers_outer_transaction(): void
    {
        $this->assertSame(0, DB::transactionLevel());
        $this->expectException(LogicException::class);
        app(PlayerTotalsService::class)->recalculate(2026, [1]);
    }
}
