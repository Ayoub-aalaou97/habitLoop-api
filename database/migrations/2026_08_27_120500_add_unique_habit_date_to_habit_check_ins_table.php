<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Keep the newest row per habit+date, remove older duplicates.
        $duplicates = DB::table('habit_check_ins')
            ->select('habit_id', 'date', DB::raw('MAX(id) as keep_id'))
            ->groupBy('habit_id', 'date')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $row) {
            DB::table('habit_check_ins')
                ->where('habit_id', $row->habit_id)
                ->whereDate('date', $row->date)
                ->where('id', '!=', $row->keep_id)
                ->delete();
        }

        Schema::table('habit_check_ins', function (Blueprint $table) {
            $table->unique(['habit_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('habit_check_ins', function (Blueprint $table) {
            $table->dropUnique(['habit_id', 'date']);
        });
    }
};
