<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Habit extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'question',
        'color',
        'note',
        'type',
        'frequency_type',
        'frequency_count',
        'frequency_period_days',
        'reminder_time',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'frequency_count' => 'integer',
            'frequency_period_days' => 'integer',
            'reminder_time' => 'datetime:H:i',
            'archived_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function checkIns(): HasMany {
        return $this->hasMany(HabitCheckIn::class);
    }

}
