<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHabitRequest;
use App\Http\Requests\UpdateHabitRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class HabitController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $habits = $request->user()->habits()
            ->when(
                $request->boolean('include_archived') === false,
                fn ($query) => $query->whereNull('archived_at')
            )
            ->latest()
            ->get();

        return response()->json($habits);
    }

    public function store(StoreHabitRequest $request): JsonResponse
    {
        $habit = $request->user()
            ->habits()
            ->create($request->validated());

        return response()->json($habit, Response::HTTP_CREATED);
    }

    public function show(Request $request, int $habit): JsonResponse
    {
        $habit = $request->user()
            ->habits()
            ->findOrFail($habit);

        return response()->json($habit);
    }

    public function update(UpdateHabitRequest $request, int $habit): JsonResponse
    {
        $habit = $request->user()
            ->habits()
            ->findOrFail($habit);

        $habit->update($request->validated());

        return response()->json($habit->fresh());
    }

    public function destroy(Request $request, int $habit): Response
    {
        $habit = $request->user()
            ->habits()
            ->findOrFail($habit);

        $habit->delete();

        return response()->noContent();
    }
}
