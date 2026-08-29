<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHabitCheckInRequest;
use App\Services\FreezeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class HabitCheckInController extends Controller
{
    public function __construct(private readonly FreezeService $freezes) {}

    public function index(Request $request, int $habit): JsonResponse
    {
        $habit = $request->user()->habits()->findOrFail($habit);

        $checkIns = $habit->checkIns()->latest('date')->get();

        return response()->json($checkIns);
    }

    public function store(StoreHabitCheckInRequest $request, int $habit): JsonResponse
    {
        $user = $request->user();
        $habit = $user->habits()->findOrFail($habit);
        $data = $request->validated();
        $dateKey = $data['date'];
        $today = now()->toDateString();
        $isBackfill = $dateKey < $today;

        $existing = $habit->checkIns()->whereDate('date', $dateKey)->first();
        $isNewDay = $existing === null;

        // Past check-ins are immutable — only today can be updated.
        if ($existing && $isBackfill) {
            throw ValidationException::withMessages([
                'date' => 'Past check-ins cannot be changed.',
            ]);
        }

        // Past-day logs spend a freeze so the streak can stay intact.
        $freezeMeta = null;
        if ($isBackfill && $isNewDay) {
            $freezeMeta = $this->freezes->spendForBackfill($user, $habit, $dateKey);
        }

        $checkIn = $habit->checkIns()->updateOrCreate(
            ['date' => $dateKey],
            [
                'mood' => $data['mood'],
                'note' => $data['note'] ?? null,
            ],
        );

        $status = $checkIn->wasRecentlyCreated
            ? Response::HTTP_CREATED
            : Response::HTTP_OK;

        $payload = $checkIn->toArray();
        if ($freezeMeta !== null) {
            $payload['freeze'] = $freezeMeta;
        } else {
            $payload['freeze'] = [
                ...$this->freezes->balance($user->fresh()),
                'spent' => false,
                'period_key' => null,
            ];
        }

        return response()->json($payload, $status);
    }

    public function destroy(Request $request, int $habit, int $checkIn): Response
    {
        $habit = $request->user()->habits()->findOrFail($habit);

        $model = $habit->checkIns()->whereKey($checkIn)->firstOrFail();
        $dateKey = $model->date instanceof \Carbon\CarbonInterface
            ? $model->date->toDateString()
            : (string) $model->date;

        if ($dateKey !== now()->toDateString()) {
            throw ValidationException::withMessages([
                'date' => "Only today's check-in can be removed.",
            ]);
        }

        $model->delete();

        return response()->noContent();
    }
}
