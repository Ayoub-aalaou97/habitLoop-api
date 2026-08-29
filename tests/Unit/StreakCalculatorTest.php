<?php

namespace Tests\Unit;

use App\Services\StreakCalculator;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class StreakCalculatorTest extends TestCase
{
    public function test_daily_streak_counts_consecutive_satisfied_days(): void
    {
        $today = Carbon::parse('2026-08-27')->startOfDay();
        $calc = new StreakCalculator(
            'day',
            1,
            ['2026-08-25', '2026-08-26', '2026-08-27'],
            Carbon::parse('2026-08-20'),
            $today,
        );

        $summary = $calc->summarize();

        $this->assertSame(3, $summary['current_streak']);
        $this->assertSame(3, $summary['longest_streak']);
        $this->assertSame(1, $summary['period_done']);
        $this->assertFalse($summary['at_risk']);
    }

    public function test_weekly_rest_days_do_not_break_the_streak(): void
    {
        $today = Carbon::parse('2026-08-27')->startOfDay(); // Thursday
        // Previous week Sun 16 - Sat 22: four days logged.
        $calc = new StreakCalculator(
            'week',
            4,
            ['2026-08-16', '2026-08-17', '2026-08-18', '2026-08-19', '2026-08-27'],
            Carbon::parse('2026-08-16'),
            $today,
        );

        $summary = $calc->summarize();

        $this->assertSame(1, $summary['current_streak']);
        $this->assertSame(1, $summary['period_done']);
        $this->assertSame(4, $summary['period_target']);
        $this->assertTrue($summary['at_risk']);
    }

    public function test_open_period_does_not_break_streak(): void
    {
        $today = Carbon::parse('2026-08-27')->startOfDay();
        $calc = new StreakCalculator(
            'week',
            4,
            ['2026-08-16', '2026-08-17', '2026-08-18', '2026-08-19'],
            Carbon::parse('2026-08-16'),
            $today,
        );

        $this->assertSame(1, $calc->summarize()['current_streak']);
    }

    public function test_first_week_target_is_capped_by_days_since_create(): void
    {
        $today = Carbon::parse('2026-08-27')->startOfDay(); // Thursday
        $calc = new StreakCalculator(
            'week',
            4,
            [],
            Carbon::parse('2026-08-27'),
            $today,
        );

        $summary = $calc->summarize();

        $this->assertSame(3, $summary['period_target']);
        $this->assertSame(0, $summary['period_done']);
        $this->assertTrue($summary['at_risk']);
        $this->assertSame(0, $summary['current_streak']);
    }

    public function test_freeze_keeps_missed_daily_period_on_streak(): void
    {
        $today = Carbon::parse('2026-08-27')->startOfDay();
        // Logged 25 and 27; missed 26 — freeze covers 26.
        $calc = new StreakCalculator(
            'day',
            1,
            ['2026-08-25', '2026-08-27'],
            Carbon::parse('2026-08-20'),
            $today,
            ['2026-08-26'],
        );

        $summary = $calc->summarize();

        $this->assertSame(3, $summary['current_streak']);
        $this->assertSame(3, $summary['longest_streak']);
    }

    public function test_freeze_keeps_missed_week_on_streak(): void
    {
        $today = Carbon::parse('2026-08-27')->startOfDay(); // Thu this week (Sun 23–Sat 29)
        // Week Sun 9–Sat 15 satisfied; week Sun 16–Sat 22 missed but frozen.
        $calc = new StreakCalculator(
            'week',
            4,
            ['2026-08-10', '2026-08-11', '2026-08-12', '2026-08-13'],
            Carbon::parse('2026-08-10'),
            $today,
            ['2026-08-16'],
        );

        $this->assertSame(2, $calc->summarize()['current_streak']);
    }
}
