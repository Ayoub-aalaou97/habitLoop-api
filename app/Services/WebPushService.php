<?php

namespace App\Services;

use App\Models\Habit;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushService
{
    public function configured(): bool
    {
        return filled(config('webpush.vapid.public_key'))
            && filled(config('webpush.vapid.private_key'));
    }

    public function sendHabitReminder(User $user, Habit $habit, bool $isTest = false): int
    {
        if (! $this->configured()) {
            return 0;
        }

        $subscriptions = $user->pushSubscriptions;
        if ($subscriptions->isEmpty()) {
            return 0;
        }

        $frontend = rtrim((string) env('FRONTEND_URL', env('APP_URL', 'http://localhost:3000')), '/');
        $title = $isTest
            ? "Test reminder · {$habit->name}"
            : "Reminder · {$habit->name}";
        $body = $habit->question ?: ($isTest
            ? 'This is a test nudge from HabitLoop.'
            : "Did you do {$habit->name} today?");

        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'habitId' => $habit->id,
            'url' => "{$frontend}/dashboard/habits/{$habit->id}",
            'tag' => 'habitloop-habit-'.$habit->id.($isTest ? '-test' : '-due'),
        ], JSON_THROW_ON_ERROR);

        $webPush = $this->client();
        $sent = 0;

        foreach ($subscriptions as $subscription) {
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'publicKey' => $subscription->public_key,
                    'authToken' => $subscription->auth_token,
                    'contentEncoding' => $subscription->content_encoding ?: 'aes128gcm',
                ]),
                $payload,
            );
        }

        foreach ($webPush->flush() as $report) {
            $endpoint = $report->getRequest()?->getUri()?->__toString();

            if ($report->isSuccess()) {
                $sent++;
                continue;
            }

            $code = $report->getResponse()?->getStatusCode();
            if (in_array($code, [404, 410], true) && $endpoint) {
                PushSubscription::query()->where('endpoint', $endpoint)->delete();
            } else {
                Log::warning('Web push failed', [
                    'endpoint' => $endpoint,
                    'reason' => $report->getReason(),
                    'status' => $code,
                ]);
            }
        }

        return $sent;
    }

    protected function client(): WebPush
    {
        $subject = (string) config('webpush.vapid.subject');
        if (! str_starts_with($subject, 'mailto:') && ! str_starts_with($subject, 'http')) {
            $subject = 'mailto:'.$subject;
        }

        return new WebPush([
            'VAPID' => [
                'subject' => $subject,
                'publicKey' => (string) config('webpush.vapid.public_key'),
                'privateKey' => (string) config('webpush.vapid.private_key'),
            ],
        ]);
    }
}
