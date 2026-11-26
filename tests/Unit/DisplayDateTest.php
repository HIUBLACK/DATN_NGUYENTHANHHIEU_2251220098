<?php

namespace Tests\Unit;

use App\Support\DisplayDate;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class DisplayDateTest extends TestCase
{
    public function test_displays_vietnam_time_without_mutating_the_database_timestamp(): void
    {
        $timestamp = Carbon::parse('2026-11-21 14:47:00', 'UTC');

        $this->assertSame('21/11/2026 21:47', DisplayDate::format($timestamp));
        $this->assertSame('2026-11-21 14:47:00', $timestamp->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $timestamp->timezoneName);
        $this->assertSame('21/11/2026 21:47', DisplayDate::format($timestamp->copy()->setTimezone('Asia/Ho_Chi_Minh')));
    }

    public function test_handles_midnight_and_missing_timestamps(): void
    {
        $timestamp = CarbonImmutable::parse('2026-11-21 18:30:00', 'UTC');

        $this->assertSame('22/11/2026 01:30', DisplayDate::format($timestamp));
        $this->assertSame('22/11/2026', DisplayDate::format($timestamp, 'd/m/Y'));
        $this->assertSame('', DisplayDate::format(null));
    }
}
