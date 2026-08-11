<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordResetCode extends Model
{
  //
  protected $fillable = [
    'email',
    'code_hash',
    'reset_token_hash',
    'expires_at',
    'attempts',
  ];

  protected function casts(): array
  {
    return [
      'expires_at' => 'datetime',
    ];
  }
}
