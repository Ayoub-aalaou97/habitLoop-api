<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class WeeklySummaryNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection<int, array{name: string, check_ins: int}>  $habits
     */
    public function __construct(
        public Collection $habits,
        public int $totalCheckIns,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontend = rtrim((string) env('FRONTEND_URL', env('APP_URL', 'http://localhost:3000')), '/');
        $mail = (new MailMessage)
            ->subject('Your HabitLoop week in review')
            ->greeting('Hey'.($notifiable->first_name ? " {$notifiable->first_name}" : '').',')
            ->line("You logged **{$this->totalCheckIns}** check-in".($this->totalCheckIns === 1 ? '' : 's').' this week.');

        foreach ($this->habits->take(8) as $row) {
            $mail->line("· {$row['name']}: {$row['check_ins']}");
        }

        return $mail
            ->action('Open dashboard', "{$frontend}/dashboard")
            ->line('Keep the loop going next week.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'total_check_ins' => $this->totalCheckIns,
            'habits' => $this->habits->values()->all(),
        ];
    }
}
