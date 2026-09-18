<?php

namespace App\Http\Controllers;

use App\Notifications\HabitReminderNotification;
use App\Services\WebPushService;
use App\Support\ReminderSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReminderController extends Controller
{
    public function settings(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json($this->serializeSettings($user));
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'timezone' => ['sometimes', 'string', 'timezone:all'],
            'email_enabled' => ['sometimes', 'boolean'],
            'push_enabled' => ['sometimes', 'boolean'],
            'weekly_summary' => ['sometimes', 'boolean'],
            'quiet_hours' => ['sometimes', 'boolean'],
            'quiet_hours_start' => ['sometimes', 'date_format:H:i'],
            'quiet_hours_end' => ['sometimes', 'date_format:H:i'],
            'streak_risk' => ['sometimes', 'boolean'],
            'freeze_suggestions' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();

        $map = [
            'timezone' => 'timezone',
            'email_enabled' => 'reminder_email_enabled',
            'push_enabled' => 'reminder_push_enabled',
            'weekly_summary' => 'reminder_weekly_summary',
            'quiet_hours' => 'reminder_quiet_hours',
            'quiet_hours_start' => 'quiet_hours_start',
            'quiet_hours_end' => 'quiet_hours_end',
            'streak_risk' => 'reminder_streak_risk',
            'freeze_suggestions' => 'reminder_freeze_suggestions',
        ];

        $payload = [];
        foreach ($map as $input => $column) {
            if (array_key_exists($input, $validated)) {
                $payload[$column] = $validated[$input];
            }
        }

        if ($payload !== []) {
            $user->update($payload);
        }

        return response()->json($this->serializeSettings($user->fresh()));
    }

    /**
     * Send a one-off test reminder (push preferred; email if enabled).
     */
    public function test(Request $request, WebPushService $webPush): JsonResponse
    {
        $validated = $request->validate([
            'habit_id' => ['sometimes', 'nullable', 'integer'],
        ]);

        $user = $request->user()->load('pushSubscriptions');

        $habitId = $validated['habit_id'] ?? null;
        $habits = $user->habits()->whereNull('archived_at');

        if ($habitId) {
            $habit = (clone $habits)->findOrFail($habitId);
        } else {
            $habit = (clone $habits)
                ->whereNotNull('reminder_time')
                ->orderBy('reminder_time')
                ->first();

            if (! $habit) {
                $habit = $habits->latest()->first();
            }
        }

        if (! $habit) {
            return response()->json([
                'message' => 'Create a habit before sending a test reminder.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $channels = [];

        if ($user->reminder_push_enabled) {
            if (! $webPush->configured()) {
                return response()->json([
                    'message' => 'Web push is not configured on the server.',
                ], Response::HTTP_SERVICE_UNAVAILABLE);
            }

            if ($user->pushSubscriptions->isEmpty()) {
                return response()->json([
                    'message' => 'Enable browser notifications on this device first.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $sent = $webPush->sendHabitReminder($user, $habit, isTest: true);
            if ($sent < 1) {
                return response()->json([
                    'message' => 'Could not deliver a push notification to this device.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $channels[] = 'push';
        }

        if ($user->reminder_email_enabled) {
            if (! $user->email) {
                return response()->json([
                    'message' => 'Your account has no email address.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $user->notifyNow(new HabitReminderNotification($habit, isTest: true));
            $channels[] = 'email';
        }

        if ($channels === []) {
            return response()->json([
                'message' => 'Turn on browser notifications under Delivery first.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'message' => 'Test reminder sent via '.implode(' + ', $channels).'.',
            'habit_id' => $habit->id,
            'habit_name' => $habit->name,
            'channels' => $channels,
            'email' => $user->email,
            'timezone' => ReminderSchedule::userTimezone($user),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSettings($user): array
    {
        return [
            'timezone' => ReminderSchedule::userTimezone($user),
            'email_enabled' => (bool) $user->reminder_email_enabled,
            'push_enabled' => (bool) $user->reminder_push_enabled,
            'weekly_summary' => (bool) $user->reminder_weekly_summary,
            'quiet_hours' => (bool) $user->reminder_quiet_hours,
            'quiet_hours_start' => ReminderSchedule::formatTime($user->quiet_hours_start) ?? '22:00',
            'quiet_hours_end' => ReminderSchedule::formatTime($user->quiet_hours_end) ?? '07:00',
            'streak_risk' => (bool) $user->reminder_streak_risk,
            'freeze_suggestions' => (bool) $user->reminder_freeze_suggestions,
            'email' => $user->email,
        ];
    }
}
