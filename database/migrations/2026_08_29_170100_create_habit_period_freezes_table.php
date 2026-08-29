<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('habit_period_freezes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('habit_id')->constrained()->cascadeOnDelete();
            $table->string('period', 16); // day | week | month
            $table->string('period_key', 16); // Y-m-d (day/week start) or Y-m (month)
            $table->string('reason', 32)->default('manual'); // backfill | protect
            $table->timestamps();

            $table->unique(['habit_id', 'period', 'period_key']);
            $table->index(['user_id', 'habit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('habit_period_freezes');
    }
};
