<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function vapidPublicKey(): JsonResponse
    {
        $key = config('webpush.vapid.public_key');

        if (! filled($key)) {
            return response()->json([
                'message' => 'Web push is not configured on the server.',
            ], 503);
        }

        return response()->json([
            'publicKey' => $key,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:2048'],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'string', 'max:32'],
        ]);

        $endpointHash = hash('sha256', $data['endpoint']);

        $subscription = PushSubscription::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'endpoint_hash' => $endpointHash,
            ],
            [
                'endpoint' => $data['endpoint'],
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
                'content_encoding' => $data['contentEncoding'] ?? 'aes128gcm',
                'user_agent' => substr((string) $request->userAgent(), 0, 512) ?: null,
            ],
        );

        return response()->json([
            'id' => $subscription->id,
            'message' => 'Push subscription saved.',
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:2048'],
        ]);

        PushSubscription::query()
            ->where('user_id', $request->user()->id)
            ->where('endpoint_hash', hash('sha256', $data['endpoint']))
            ->delete();

        return response()->json([
            'message' => 'Push subscription removed.',
        ]);
    }
}
