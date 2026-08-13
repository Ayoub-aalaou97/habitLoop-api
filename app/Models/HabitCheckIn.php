<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HabitCheckIn extends Model
{
    protected $fillable = [
        'date',
        'mood',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'mood' => 'integer',
        ];
    }

    public function habit(): BelongsTo
    {
        return $this->belongsTo(Habit::class);
    }
}
