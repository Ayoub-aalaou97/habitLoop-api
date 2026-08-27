<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHabitCheckInRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class HabitCheckInController extends Controller
{
    public function index(Request $request, int $habit): JsonResponse
    {
        $habit = $request->user()->habits()->findOrFail($habit);

        $checkIns = $habit->checkIns()->latest('date')->get();

        return response()->json($checkIns);
    }

    public function store(StoreHabitCheckInRequest $request, int $habit): JsonResponse
    {
        $habit = $request->user()
            ->habits()
            ->findOrFail($habit);

        $data = $request->validated();

        // One check-in per habit per day: update mood/note if already logged.
        $checkIn = $habit->checkIns()->updateOrCreate(
            ['date' => $data['date']],
            [
                'mood' => $data['mood'],
                'note' => $data['note'] ?? null,
            ],
        );

        $status = $checkIn->wasRecentlyCreated
            ? Response::HTTP_CREATED
            : Response::HTTP_OK;

        return response()->json($checkIn, $status);
    }
}
