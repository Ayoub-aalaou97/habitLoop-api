<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\HabitCheckInController;
use App\Http\Controllers\HabitController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


//Registration (api test)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword'])
    ->middleware('throttle:5,1');
Route::post('/verify-reset-code', [PasswordResetController::class, 'verifyCode'])
    ->middleware('throttle:10,1');
Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])
    ->middleware('throttle:10,1');

//Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);

// Google routes moved to routes/web.php (Socialite needs session via `web` middleware)


Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::apiResource('habits', HabitController::class);

    Route::post('/habits/{habit}/check-ins', [HabitCheckInController::class, 'store']);
});