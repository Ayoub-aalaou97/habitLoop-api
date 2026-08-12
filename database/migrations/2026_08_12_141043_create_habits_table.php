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
    Schema::create('habits', function (Blueprint $table) {
      $table->id();
      $table->foreignId('user_id')->constrained()->cascadeOnDelete();
      $table->string('name');
      $table->string('question')->nullable();
      $table->string('color', 7);
      $table->text('note')->nullable();
      $table->string('type'); // build | quit
      $table->string('frequency_type');
      $table->unsignedSmallInteger('frequency_count')->nullable();
      $table->unsignedSmallInteger('frequency_period_days')->nullable();
      $table->time('reminder_time')->nullable();
      $table->timestamp('archived_at')->nullable();
      $table->timestamps();

      $table->index('user_id');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('habits');
  }
};
