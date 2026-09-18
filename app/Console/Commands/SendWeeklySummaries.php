<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\WeeklySummaryNotification;
use App\Support\ReminderSchedule;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SendWeeklySummaries extends Command
{
    protected $signature = 'habits:send-weekly-summaries
                            {--dry-run : List matches without sending}
                            {--at= : Override UTC timestamp (ISO-8601) for testing}';

    protected $description = 'Email weekly summaries on Sunday 18:00 in each user timezone';

    public function handle(): int
    {
        $utcNow = $this->option('at')
            ? Carbon::parse($this->option('at'), 'UTC')
            : now('UTC');

        $users = User::query()
            ->where('reminder_weekly_summary', true)
            ->where('reminder_email_enabled', true)
            ->whereNotNull('email')
            ->with(['habits' => fn ($q) => $q->whereNull('archived_at')])
            ->get();

        $sent = 0;
        $skipped = 0;

        foreach ($users as $user) {
            $local = ReminderSchedule::localNow($user, $utcNow);

            // Sunday 18:00 local
            if ((int) $local->dayOfWeek !== Carbon::SUNDAY || $local->format('H:i') !== '18:00') {
                continue;
            }

            $weekStart = $local->copy()->startOfWeek(Carbon::MONDAY)->timezone('UTC');
            $weekEnd = $local->copy()->endOfWeek(Carbon::SUNDAY)->timezone('UTC');

            $rows = $user->habits->map(function ($habit) use ($weekStart, $weekEnd) {
                $count = $habit->checkIns()
                    ->whereBetween('date', [
                        $weekStart->toDateString(),
                        $weekEnd->toDateString(),
                    ])
                    ->count();

                return [
                    'name' => $habit->name,
                    'check_ins' => $count,
                ];
            });

            $total = (int) $rows->sum('check_ins');
            $dedupeKey = sprintf('weekly-summary-sent:%d:%s', $user->id, $local->format('Y-W'));

            if ($this->option('dry-run')) {
                $this->line("Would summary {$user->email} · {$total} check-ins");
                $sent++;
                continue;
            }

            if (! Cache::add($dedupeKey, true, now()->addDays(2))) {
                $skipped++;
                continue;
            }

            $user->notify(new WeeklySummaryNotification($rows, $total));
            $this->line("Sent summary · {$user->email} · {$total}");
            $sent++;
        }

        if ($sent === 0 && $skipped === 0) {
            $this->info('No weekly summaries due this minute.');
        } else {
            $this->info("Done. sent={$sent} skipped={$skipped}");
        }

        return self::SUCCESS;
    }
}
