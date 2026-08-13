<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::create('habit_check_ins', function (Blueprint $table) {
      $table->id();
      $table->foreignId('habit_id')->constrained()->cascadeOnDelete();
      $table->date('date');
      $table->unsignedTinyInteger('mood');
      $table->text('note')->nullable();
      $table->timestamps();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('habit_check_ins');
  }
};