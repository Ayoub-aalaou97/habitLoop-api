<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHabitCheckInRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class HabitCheckInController extends Controller
{
    public function store(StoreHabitCheckInRequest $request, int $habit): JsonResponse
    {
        $habit = $request->user()
            ->habits()
            ->findOrFail($habit);

        $checkIn = $habit->checkIns()->create($request->validated());

        return response()->json($checkIn, Response::HTTP_CREATED);
    }
}
