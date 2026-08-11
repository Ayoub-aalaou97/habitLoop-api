<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
  public function redirect(): RedirectResponse {
    return Socialite::driver('google')->redirect();
  }

  public function callback(): RedirectResponse {
    // Get the frontend URL from the environment variables
    $frontendUrl = rtrim((string) env('FRONTEND_URL', 'http://localhost:3000'), '/');

    try {
      // Get the Google user from the Socialite driver
      $googleUser = Socialite::driver('google')->user();
    } catch (Throwable $e) {
      Log::warning('Google OAuth callback failed', ['message' => $e->getMessage()]);

      // Redirect to the frontend URL with the error
      return redirect()->away($frontendUrl.'/auth/callback?error=google_auth_failed');
    }

    // Get the Google ID from the Google user
    $googleId = $googleUser->getId();
    // Get the email from the Google user
    $email = $googleUser->getEmail();

    // If the Google ID or email is not set, redirect to the frontend URL with the error
    if (! $googleId || ! $email) {
      // Redirect to the frontend URL with the error
      return redirect()->away($frontendUrl.'/auth/callback?error=google_auth_failed');
    }

    // Use only Contract methods (getName) — getRaw() is not on the interface.
    $fullName = trim((string) ($googleUser->getName() ?? ''));
    $nameParts = $fullName !== '' ? preg_split('/\s+/', $fullName) : [];

    $firstName = $nameParts[0] ?? 'User';
    $lastName = isset($nameParts[1]) ? implode(' ', array_slice($nameParts, 1)) : '';

    $user = User::where('google_id', $googleId)->first();

    // If the user does not exist, check if the email is already in the database
    if (!$user) {
      // Check if the email is already in the database
      $user = User::where('email', $email)->first();

      // If the user exists, update the user with the Google ID and email verified at
      if ($user) {
        $user->google_id = $googleId;
        // If the email is not verified, set the email verified at to now
        if (! $user->email_verified_at) {
          $user->email_verified_at = now();
        }
        $user->save();
      } else {
        // If the user does not exist, create a new user with the Google ID and email verified at
        $user = User::create([
          'first_name' => $firstName,
          'last_name' => $lastName,
          'email' => $email,
          'google_id' => $googleId,
          'email_verified_at' => now(),
          'password' => null,
        ]);
      }
    }

    // Create a new token for the user
    $token = $user->createToken('google-token')->plainTextToken;

    // Redirect to the frontend URL with the token
    return redirect()->away($frontendUrl.'/auth/callback?token='.urlencode($token));
  }
}
