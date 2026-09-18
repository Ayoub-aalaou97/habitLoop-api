<?php

namespace App\Notifications;

use App\Models\Habit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class HabitReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Habit $habit,
        public bool $isTest = false,
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
        $habitUrl = "{$frontend}/dashboard/habits/{$this->habit->id}";
        $question = $this->habit->question ?: "Did you do {$this->habit->name} today?";
        $subject = $this->isTest
            ? "Test reminder · {$this->habit->name}"
            : "Reminder · {$this->habit->name}";

        return (new MailMessage)
            ->subject($subject)
            ->greeting('Hey'.($notifiable->first_name ? " {$notifiable->first_name}" : '').',')
            ->line($this->isTest
                ? 'This is a test nudge from HabitLoop.'
                : 'Time to keep your loop going.')
            ->line("**{$this->habit->name}**")
            ->line($question)
            ->action('Open habit', $habitUrl)
            ->line('Small steps count. See you in the loop.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'habit_id' => $this->habit->id,
            'habit_name' => $this->habit->name,
            'is_test' => $this->isTest,
        ];
    }
}
