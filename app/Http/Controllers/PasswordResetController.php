<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\PasswordResetCode;
use App\Notifications\SendPasswordResetCode;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\VerifyResetCodeRequest;
use App\Http\Requests\ResetPasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
  //
  public function forgotPassword(ForgotPasswordRequest $request): JsonResponse {
    $validated = $request->validated();

    $email = $validated['email'];

    $user = User::where('email', $email)->first();

    if ($user) {
      PasswordResetCode::where('email', $email)->delete();
      $code = random_int(100000, 999999);

      PasswordResetCode::insert([
        'email' => $email,
        'code_hash' => Hash::make((string) $code),
        'reset_token_hash' => null,
        'expires_at' => now()->addMinutes(15),
        'attempts' => 0,
        'created_at' => now(),
        'updated_at' => now(),
      ]);

      $user->notify(new SendPasswordResetCode($code));
    }

    // Same status and body whether or not the account exists.
    return response()->json([
      'message' => "If an account exists for this email, we've sent a code.",
    ], 200);
  }

  public function verifyCode(VerifyResetCodeRequest $request): JsonResponse {
    $validated = $request->validated();

    $email = $validated['email'];
    $code = $validated['code'];

    $row = PasswordResetCode::where('email', $email)->first();

    if(!$row || !$row->code_hash) {
      return $this->invalidCodeResponse();
    }

    if($row->expires_at->isPast()) {
      PasswordResetCode::where('email', $email)->delete();
      return $this->invalidCodeResponse();
    }

    if($row->attempts >= 5) {
      PasswordResetCode::where('email', $email)->delete();
      return $this->invalidCodeResponse();
    }

    if (!Hash::check((string) $code, $row->code_hash)) {
      PasswordResetCode::where('email', $email)->increment('attempts');
      return $this->invalidCodeResponse();
    }

    $resetToken = Str::random(64);

    PasswordResetCode::where('email', $email)
      ->update([
        'code_hash' => null,
        'reset_token_hash' => Hash::make($resetToken),
        'expires_at' => now()->addMinutes(10),
        'updated_at' => now(),
      ]);

    return response()->json([
      'message' => 'Code verified.',
      'reset_token' => $resetToken,
    ], 200);

  }

  public function resetPassword(ResetPasswordRequest $request): JsonResponse {
    $validated = $request->validated();

    $row = PasswordResetCode::where('email', $validated['email'])->first();

    if (!$row || !$row->reset_token_hash ||
      now()->isAfter($row->expires_at) || !Hash::check($validated['reset_token'], $row->reset_token_hash)
    ) {
        return $this->invalidCodeResponse();
    }

      $user = User::where('email', $validated['email'])->first();

      if (!$user) {
        return $this->invalidCodeResponse();
      }

      DB::transaction(function () use ($user, $validated) {
        // The User model's `hashed` cast handles password hashing.
        $user->password = $validated['password'];
        $user->save();

        // Password reset tokens are single-use.
        PasswordResetCode::where('email', $user->email)->delete();

        // Revoke every existing Sanctum token.
        $user->tokens()->delete();
      });

      return response()->json([
        'message' => 'Password reset successfully. Please log in with your new password.',
      ], 200);
  }

  private function invalidCodeResponse(): JsonResponse
  {
    return response()->json([
      'message' => 'Invalid or expired code.',
    ], 422);
  }

}
