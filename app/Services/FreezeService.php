<?php

namespace App\Services;

use App\Models\Habit;
use App\Models\HabitPeriodFreeze;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FreezeService
{
    /**
     * @return array{remaining: int, total: int}
     */
    public function balance(User $user): array
    {
        return [
            'remaining' => (int) $user->streak_freezes_remaining,
            'total' => (int) $user->streak_freezes_total,
        ];
    }

    /**
     * @return list<string>
     */
    public function frozenPeriodKeys(Habit $habit): array
    {
        return $habit->periodFreezes()
            ->pluck('period_key')
            ->map(fn ($key) => (string) $key)
            ->all();
    }

    public function periodKeyForDate(Habit $habit, string $dateKey): string
    {
        [$period] = StreakCalculator::goalFromHabit($habit);
        $day = Carbon::parse($dateKey)->startOfDay();

        return match ($period) {
            'week' => $day->copy()->startOfWeek(Carbon::SUNDAY)->toDateString(),
            'month' => $day->format('Y-m'),
            default => $day->toDateString(),
        };
    }

    /**
     * Spend one freeze to cover a past check-in date's period.
     * Idempotent if that period is already frozen.
     *
     * @return array{remaining: int, total: int, spent: bool, period_key: string}
     */
    public function spendForBackfill(User $user, Habit $habit, string $dateKey): array
    {
        [$period] = StreakCalculator::goalFromHabit($habit);
        $periodKey = $this->periodKeyForDate($habit, $dateKey);

        return DB::transaction(function () use ($user, $habit, $period, $periodKey) {
            $user = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $existing = HabitPeriodFreeze::query()
                ->where('habit_id', $habit->id)
                ->where('period', $period)
                ->where('period_key', $periodKey)
                ->first();

            if ((int) $user->streak_freezes_remaining < 1) {
                throw ValidationException::withMessages([
                    'date' => 'No streak freezes left. Log today without a freeze, or wait to refill.',
                ]);
            }

            if ($existing) {
                return [
                    ...$this->balance($user),
                    'spent' => false,
                    'period_key' => $periodKey,
                ];
            }

            $user->streak_freezes_remaining = max(0, (int) $user->streak_freezes_remaining - 1);
            $user->save();

            HabitPeriodFreeze::query()->create([
                'user_id' => $user->id,
                'habit_id' => $habit->id,
                'period' => $period,
                'period_key' => $periodKey,
                'reason' => 'backfill',
            ]);

            return [
                ...$this->balance($user->fresh()),
                'spent' => true,
                'period_key' => $periodKey,
            ];
        });
    }

    /**
     * Apply a freeze to protect a closed missed period (no check-in required).
     *
     * @return array{remaining: int, total: int, period_key: string, freeze: HabitPeriodFreeze}
     */
    public function protectPeriod(User $user, Habit $habit, string $periodKey): array
    {
        [$period] = StreakCalculator::goalFromHabit($habit);

        return DB::transaction(function () use ($user, $habit, $period, $periodKey) {
            $user = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $existing = HabitPeriodFreeze::query()
                ->where('habit_id', $habit->id)
                ->where('period', $period)
                ->where('period_key', $periodKey)
                ->first();

            if ($existing) {
                return [
                    ...$this->balance($user),
                    'period_key' => $periodKey,
                    'freeze' => $existing,
                ];
            }

            if ((int) $user->streak_freezes_remaining < 1) {
                throw ValidationException::withMessages([
                    'period_key' => 'No streak freezes left.',
                ]);
            }

            // Period must be closed and currently a miss based on check-ins alone.
            $dates = $habit->checkIns()
                ->pluck('date')
                ->map(fn ($d) => Carbon::parse($d)->toDateString())
                ->unique()
                ->values()
                ->all();

            $calc = StreakCalculator::forHabit($habit, $dates);
            $snap = $calc->snapshotForKey($periodKey);

            if ($snap === null || $snap['status'] !== 'missed') {
                throw ValidationException::withMessages([
                    'period_key' => 'Freezes can only protect a closed missed loop.',
                ]);
            }

            $user->streak_freezes_remaining = max(0, (int) $user->streak_freezes_remaining - 1);
            $user->save();

            $freeze = HabitPeriodFreeze::query()->create([
                'user_id' => $user->id,
                'habit_id' => $habit->id,
                'period' => $period,
                'period_key' => $periodKey,
                'reason' => 'protect',
            ]);

            return [
                ...$this->balance($user->fresh()),
                'period_key' => $periodKey,
                'freeze' => $freeze,
            ];
        });
    }
}
