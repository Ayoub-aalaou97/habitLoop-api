<?php

namespace App\Services;

use App\Models\Habit;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class StreakCalculator
{
    /**
     * @param  list<string>  $dateKeys  Unique check-in dates as Y-m-d
     * @param  list<string>  $frozenPeriodKeys  Period keys covered by freezes
     */
    public function __construct(
        private readonly string $period,
        private readonly int $target,
        private readonly array $dateKeys,
        private readonly ?CarbonInterface $startedAt = null,
        private readonly ?CarbonInterface $today = null,
        private readonly array $frozenPeriodKeys = [],
    ) {}

    /**
     * @param  list<string>  $dateKeys
     * @param  list<string>  $frozenPeriodKeys
     */
    public static function forHabit(
        Habit $habit,
        array $dateKeys,
        ?CarbonInterface $today = null,
        array $frozenPeriodKeys = [],
    ): self {
        [$period, $target] = self::goalFromHabit($habit);

        return new self(
            $period,
            $target,
            $dateKeys,
            $habit->created_at ? Carbon::parse($habit->created_at)->startOfDay() : null,
            $today,
            $frozenPeriodKeys,
        );
    }

    /**
     * @return array{period: string, target: int}
     */
    public static function goalFromHabit(Habit $habit): array
    {
        return match ($habit->frequency_type) {
            'x_times_per_week' => ['week', max(1, min(7, (int) ($habit->frequency_count ?? 4)))],
            'x_times_in_y_days' => ['month', max(1, min(31, (int) ($habit->frequency_count ?? 1)))],
            default => ['day', 1],
        };
    }

    /**
     * @return array{
     *     current_streak: int,
     *     longest_streak: int,
     *     consistency: int,
     *     period_done: int,
     *     period_target: int,
     *     period: string,
     *     at_risk: bool
     * }
     */
    public function summarize(): array
    {
        $today = ($this->today ?? now())->copy()->startOfDay();
        $origin = ($this->startedAt ?? $today)->copy()->startOfDay();
        $dates = array_fill_keys($this->dateKeys, true);

        $periods = $this->listPeriods($origin, $today, $dates);
        $current = $periods === [] ? null : $periods[array_key_last($periods)];
        $closed = array_values(array_filter(
            $periods,
            fn (array $item) => in_array($item['status'], ['satisfied', 'missed'], true),
        ));
        $rolling = array_slice($closed, -12);
        $satisfied = count(array_filter($rolling, fn (array $item) => $item['satisfied']));
        $consistency = $rolling === [] ? 0 : (int) round(($satisfied / count($rolling)) * 100);

        $periodDone = $current['done'] ?? 0;
        $periodTarget = $current['target'] ?? $this->target;
        $needed = max(0, $periodTarget - $periodDone);
        $daysLeft = $this->daysLeft($today, $origin);
        $inProgress = in_array($current['status'] ?? null, ['progress', 'satisfied'], true);

        return [
            'current_streak' => $this->currentRun($periods),
            'longest_streak' => $this->longestRun($periods),
            'consistency' => $consistency,
            'period_done' => $periodDone,
            'period_target' => $periodTarget,
            'period' => $this->period,
            'at_risk' => $inProgress && $needed > 0 && $daysLeft <= $needed,
        ];
    }

    /**
     * @param  array<string, bool>  $dates
     * @return list<array{status: string, satisfied: bool, done: int, target: int}>
     */
    private function listPeriods(CarbonInterface $origin, CarbonInterface $today, array $dates): array
    {
        $cursor = $this->startOfPeriod($origin);
        $last = $this->startOfPeriod($today);
        $periods = [];

        while ($cursor->lte($last)) {
            $snap = $this->snapshot($cursor, $dates, $today, $origin);
            if ($snap !== null) {
                $periods[] = $snap;
            }
            $cursor = $this->shiftPeriod($cursor, 1);
        }

        return $periods;
    }

    /**
     * @param  array<string, bool>  $dates
     * @return array{status: string, satisfied: bool, done: int, target: int, key: string}|null
     */
    private function snapshot(
        CarbonInterface $date,
        array $dates,
        CarbonInterface $today,
        CarbonInterface $startedAt,
    ): ?array {
        $periodStart = $this->startOfPeriod($date);
        $periodEnd = $this->endOfPeriod($date);
        $activeStart = $startedAt->copy()->startOfDay()->gt($periodStart)
            ? $startedAt->copy()->startOfDay()
            : $periodStart;

        if ($activeStart->gt($periodEnd)) {
            return null;
        }

        $availableDays = (int) $activeStart->diffInDays($periodEnd) + 1;
        $target = max(1, min($this->target, $availableDays));
        $done = $this->countInRange($dates, $activeStart, $periodEnd);
        $key = $this->periodKey($date);
        $frozen = in_array($key, $this->frozenPeriodKeys, true);
        $satisfied = $done >= $target || $frozen;

        if ($activeStart->gt($today)) {
            $status = 'future';
        } elseif ($periodEnd->gte($today)) {
            $status = $satisfied ? 'satisfied' : 'progress';
        } else {
            $status = $satisfied ? 'satisfied' : 'missed';
        }

        return [
            'status' => $status,
            'satisfied' => $satisfied,
            'done' => $done,
            'target' => $target,
            'key' => $key,
            'frozen' => $frozen,
        ];
    }

    /**
     * Look up one period snapshot by key (without applying freezes).
     *
     * @return array{status: string, satisfied: bool, done: int, target: int, key: string}|null
     */
    public function snapshotForKey(string $periodKey): ?array
    {
        $today = ($this->today ?? now())->copy()->startOfDay();
        $origin = ($this->startedAt ?? $today)->copy()->startOfDay();
        $dates = array_fill_keys($this->dateKeys, true);

        // Evaluate without freezes so protect() can detect a true miss.
        $plain = new self(
            $this->period,
            $this->target,
            $this->dateKeys,
            $this->startedAt,
            $this->today,
            [],
        );

        $cursor = $this->startOfPeriod($origin);
        $last = $this->startOfPeriod($today);

        while ($cursor->lte($last)) {
            $snap = $plain->snapshot($cursor, $dates, $today, $origin);
            if ($snap !== null && $snap['key'] === $periodKey) {
                return $snap;
            }
            $cursor = $this->shiftPeriod($cursor, 1);
        }

        return null;
    }

    public function periodKey(CarbonInterface $date): string
    {
        $start = $this->startOfPeriod($date);

        return match ($this->period) {
            'month' => $start->format('Y-m'),
            default => $start->toDateString(),
        };
    }

    /**
     * @param  array<string, bool>  $dates
     */
    private function countInRange(array $dates, CarbonInterface $start, CarbonInterface $end): int
    {
        $count = 0;
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            if (isset($dates[$cursor->toDateString()])) {
                $count++;
            }
            $cursor->addDay();
        }

        return $count;
    }

    private function startOfPeriod(CarbonInterface $date): Carbon
    {
        $day = Carbon::parse($date)->startOfDay();

        return match ($this->period) {
            'week' => $day->copy()->startOfWeek(Carbon::SUNDAY),
            'month' => $day->copy()->startOfMonth(),
            default => $day,
        };
    }

    private function endOfPeriod(CarbonInterface $date): Carbon
    {
        $start = $this->startOfPeriod($date);

        return match ($this->period) {
            'week' => $start->copy()->addDays(6),
            'month' => $start->copy()->endOfMonth()->startOfDay(),
            default => $start,
        };
    }

    private function shiftPeriod(CarbonInterface $date, int $delta): Carbon
    {
        $start = $this->startOfPeriod($date);

        return match ($this->period) {
            'week' => $start->copy()->addWeeks($delta),
            'month' => $start->copy()->addMonthsNoOverflow($delta),
            default => $start->copy()->addDays($delta),
        };
    }

    private function daysLeft(CarbonInterface $today, CarbonInterface $startedAt): int
    {
        $start = $startedAt->gt($today) ? $startedAt->copy()->startOfDay() : $today->copy();

        return (int) $start->diffInDays($this->endOfPeriod($today)) + 1;
    }

    /**
     * @param  list<array{status: string, satisfied: bool, done: int, target: int}>  $periods
     */
    private function currentRun(array $periods): int
    {
        $streak = 0;
        for ($i = count($periods) - 1; $i >= 0; $i--) {
            $item = $periods[$i];
            if ($item['status'] === 'future' || $item['status'] === 'progress') {
                continue;
            }
            if ($item['satisfied']) {
                $streak++;
            } else {
                break;
            }
        }

        return $streak;
    }

    /**
     * @param  list<array{status: string, satisfied: bool, done: int, target: int}>  $periods
     */
    private function longestRun(array $periods): int
    {
        $longest = 0;
        $run = 0;
        foreach ($periods as $item) {
            if ($item['status'] === 'future' || $item['status'] === 'progress') {
                continue;
            }
            if ($item['satisfied']) {
                $run++;
                $longest = max($longest, $run);
            } else {
                $run = 0;
            }
        }

        return $longest;
    }
}
