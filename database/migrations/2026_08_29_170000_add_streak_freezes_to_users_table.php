<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('streak_freezes_remaining')->default(3)->after('remember_token');
            $table->unsignedTinyInteger('streak_freezes_total')->default(3)->after('streak_freezes_remaining');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['streak_freezes_remaining', 'streak_freezes_total']);
        });
    }
};
