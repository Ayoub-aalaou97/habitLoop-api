<?php

namespace App\Http\Controllers;

use App\Services\FreezeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FreezeController extends Controller
{
    public function __construct(private readonly FreezeService $freezes) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            ...$this->freezes->balance($user),
            'by_habit' => $user->periodFreezes()
                ->get(['habit_id', 'period', 'period_key', 'reason'])
                ->groupBy('habit_id')
                ->mapWithKeys(fn ($rows, $habitId) => [
                    (string) $habitId => $rows->pluck('period_key')->values()->all(),
                ])
                ->all(),
        ]);
    }

    public function store(Request $request, int $habit): JsonResponse
    {
        $data = $request->validate([
            'period_key' => ['required', 'string', 'max:16'],
        ]);

        $habitModel = $request->user()->habits()->findOrFail($habit);
        $result = $this->freezes->protectPeriod(
            $request->user(),
            $habitModel,
            $data['period_key'],
        );

        return response()->json([
            'remaining' => $result['remaining'],
            'total' => $result['total'],
            'period_key' => $result['period_key'],
            'freeze' => $result['freeze'],
        ], Response::HTTP_CREATED);
    }
}
