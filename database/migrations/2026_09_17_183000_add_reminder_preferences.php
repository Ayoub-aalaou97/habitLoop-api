<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('timezone', 64)->default('UTC')->after('email');
            $table->boolean('reminder_email_enabled')->default(false)->after('timezone');
            $table->boolean('reminder_push_enabled')->default(false)->after('reminder_email_enabled');
            $table->boolean('reminder_weekly_summary')->default(false)->after('reminder_push_enabled');
            $table->boolean('reminder_quiet_hours')->default(true)->after('reminder_weekly_summary');
            $table->time('quiet_hours_start')->default('22:00:00')->after('reminder_quiet_hours');
            $table->time('quiet_hours_end')->default('07:00:00')->after('quiet_hours_start');
            $table->boolean('reminder_streak_risk')->default(true)->after('quiet_hours_end');
            $table->boolean('reminder_freeze_suggestions')->default(false)->after('reminder_streak_risk');
        });

        Schema::table('habits', function (Blueprint $table) {
            $table->json('reminder_days')->nullable()->after('reminder_time');
        });
    }

    public function down(): void
    {
        Schema::table('habits', function (Blueprint $table) {
            $table->dropColumn('reminder_days');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'timezone',
                'reminder_email_enabled',
                'reminder_push_enabled',
                'reminder_weekly_summary',
                'reminder_quiet_hours',
                'quiet_hours_start',
                'quiet_hours_end',
                'reminder_streak_risk',
                'reminder_freeze_suggestions',
            ]);
        });
    }
};
