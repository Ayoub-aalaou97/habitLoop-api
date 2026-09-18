<?php

namespace App\Support;

use App\Models\Habit;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class ReminderSchedule
{
    /**
     * @return array<int, bool> Sun–Sat
     */
    public static function defaultDaysForHabit(Habit $habit): array
    {
        if (in_array($habit->frequency_type, ['daily', 'every_x_days'], true)) {
            return [true, true, true, true, true, true, true];
        }

        if ($habit->frequency_type === 'x_times_per_week') {
            $count = max(1, min(7, (int) ($habit->frequency_count ?? 3)));
            $preferred = [1, 3, 5, 2, 4, 6, 0];
            $days = [false, false, false, false, false, false, false];
            for ($i = 0; $i < $count; $i++) {
                $days[$preferred[$i]] = true;
            }

            return $days;
        }

        return [false, true, true, true, true, true, false];
    }

    /**
     * @return array<int, bool>
     */
    public static function daysForHabit(Habit $habit): array
    {
        $stored = $habit->reminder_days;
        if (is_array($stored) && count($stored) === 7) {
            return array_map(static fn ($v) => (bool) $v, array_values($stored));
        }

        return self::defaultDaysForHabit($habit);
    }

    public static function userTimezone(User $user): string
    {
        $tz = $user->timezone ?: config('app.timezone', 'UTC');

        try {
            new \DateTimeZone($tz);

            return $tz;
        } catch (\Exception) {
            return (string) config('app.timezone', 'UTC');
        }
    }

    public static function localNow(User $user, ?CarbonInterface $utcNow = null): Carbon
    {
        $utcNow ??= now('UTC');

        return Carbon::parse($utcNow)->timezone(self::userTimezone($user));
    }

    public static function formatTime(?CarbonInterface $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value->format('H:i');
    }

    public static function isQuietHours(User $user, CarbonInterface $localNow): bool
    {
        if (! $user->reminder_quiet_hours) {
            return false;
        }

        $start = self::formatTime($user->quiet_hours_start) ?? '22:00';
        $end = self::formatTime($user->quiet_hours_end) ?? '07:00';
        $current = $localNow->format('H:i');

        if ($start === $end) {
            return false;
        }

        // Overnight window (e.g. 22:00 → 07:00)
        if ($start > $end) {
            return $current >= $start || $current < $end;
        }

        return $current >= $start && $current < $end;
    }

    public static function isDueToday(Habit $habit, CarbonInterface $localNow): bool
    {
        $days = self::daysForHabit($habit);

        return (bool) ($days[$localNow->dayOfWeek] ?? false);
    }

    public static function matchesReminderMinute(Habit $habit, CarbonInterface $localNow): bool
    {
        $time = self::formatTime($habit->reminder_time);
        if ($time === null) {
            return false;
        }

        return $time === $localNow->format('H:i');
    }
}
