<?php

namespace App\Console\Commands;

use App\Models\Habit;
use App\Notifications\HabitReminderNotification;
use App\Services\WebPushService;
use App\Support\ReminderSchedule;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SendHabitReminders extends Command
{
    protected $signature = 'habits:send-reminders
                            {--dry-run : List matches without sending}
                            {--at= : Override UTC timestamp (ISO-8601) for testing}';

    protected $description = 'Send due habit reminders via browser push (and email when enabled)';

    public function handle(WebPushService $webPush): int
    {
        $utcNow = $this->option('at')
            ? Carbon::parse($this->option('at'), 'UTC')
            : now('UTC');

        $habits = Habit::query()
            ->with(['user.pushSubscriptions'])
            ->whereNotNull('reminder_time')
            ->whereNull('archived_at')
            ->whereHas('user', function ($q) {
                $q->where(function ($inner) {
                    $inner->where('reminder_email_enabled', true)
                        ->orWhere('reminder_push_enabled', true);
                });
            })
            ->get();

        $sent = 0;
        $skipped = 0;

        foreach ($habits as $habit) {
            $user = $habit->user;
            if (! $user) {
                $skipped++;
                continue;
            }

            $wantEmail = (bool) $user->reminder_email_enabled && filled($user->email);
            $wantPush = (bool) $user->reminder_push_enabled
                && $user->pushSubscriptions->isNotEmpty()
                && $webPush->configured();

            if (! $wantEmail && ! $wantPush) {
                $skipped++;
                continue;
            }

            $local = ReminderSchedule::localNow($user, $utcNow);

            if (! ReminderSchedule::matchesReminderMinute($habit, $local)) {
                continue;
            }

            if (! ReminderSchedule::isDueToday($habit, $local)) {
                $skipped++;
                continue;
            }

            if (ReminderSchedule::isQuietHours($user, $local)) {
                $this->line("Quiet hours · skipped {$habit->name} for user #{$user->id}");
                $skipped++;
                continue;
            }

            $dedupeKey = sprintf(
                'habit-reminder-sent:%d:%s',
                $habit->id,
                $local->format('Y-m-d-H-i'),
            );

            $label = sprintf(
                'user#%d · %s @ %s (%s)',
                $user->id,
                $habit->name,
                $local->format('H:i'),
                ReminderSchedule::userTimezone($user),
            );

            if ($this->option('dry-run')) {
                $channels = [];
                if ($wantPush) {
                    $channels[] = 'push';
                }
                if ($wantEmail) {
                    $channels[] = 'email';
                }
                $this->line('Would notify '.implode('+', $channels)." · {$label}");
                $sent++;
                continue;
            }

            if (! Cache::add($dedupeKey, true, now()->addMinutes(5))) {
                $skipped++;
                continue;
            }

            $delivered = false;

            if ($wantPush) {
                $pushCount = $webPush->sendHabitReminder($user, $habit);
                if ($pushCount > 0) {
                    $this->line("Push · {$label} ({$pushCount})");
                    $delivered = true;
                }
            }

            if ($wantEmail) {
                $user->notifyNow(new HabitReminderNotification($habit));
                $this->line("Email · {$label}");
                $delivered = true;
            }

            if ($delivered) {
                $sent++;
            } else {
                Cache::forget($dedupeKey);
                $skipped++;
            }
        }

        if ($sent === 0 && $skipped === 0) {
            $this->info('No habits due this minute.');
        } else {
            $this->info("Done. sent={$sent} skipped={$skipped}");
        }

        return self::SUCCESS;
    }
}
