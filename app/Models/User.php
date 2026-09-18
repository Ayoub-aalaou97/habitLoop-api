<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'google_id',
        'email_verified_at',
        'streak_freezes_remaining',
        'streak_freezes_total',
        'timezone',
        'reminder_email_enabled',
        'reminder_push_enabled',
        'reminder_weekly_summary',
        'reminder_quiet_hours',
        'quiet_hours_start',
        'quiet_hours_end',
        'reminder_streak_risk',
        'reminder_freeze_suggestions',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'streak_freezes_remaining' => 'integer',
            'streak_freezes_total' => 'integer',
            'reminder_email_enabled' => 'boolean',
            'reminder_push_enabled' => 'boolean',
            'reminder_weekly_summary' => 'boolean',
            'reminder_quiet_hours' => 'boolean',
            'quiet_hours_start' => 'datetime:H:i',
            'quiet_hours_end' => 'datetime:H:i',
            'reminder_streak_risk' => 'boolean',
            'reminder_freeze_suggestions' => 'boolean',
        ];
    }

    public function habits(): HasMany
    {
        return $this->hasMany(Habit::class);
    }

    public function periodFreezes(): HasMany
    {
        return $this->hasMany(HabitPeriodFreeze::class);
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }
}
