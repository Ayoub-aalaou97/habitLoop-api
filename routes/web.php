<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GoogleAuthController;

Route::get('/', function () {
    return view('welcome');
});

// Google OAuth needs session state. Socialite will fail on the `api` middleware group.
Route::middleware('web')->group(function () {
    Route::get('/api/auth/google', [GoogleAuthController::class, 'redirect']);
    Route::get('/api/auth/google/callback', [GoogleAuthController::class, 'callback']);
});
