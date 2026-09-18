<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'endpoint',
        'endpoint_hash',
        'public_key',
        'auth_token',
        'content_encoding',
        'user_agent',
    ];

    protected static function booted(): void
    {
        static::saving(function (PushSubscription $subscription) {
            if ($subscription->isDirty('endpoint') || empty($subscription->endpoint_hash)) {
                $subscription->endpoint_hash = hash('sha256', $subscription->endpoint);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
