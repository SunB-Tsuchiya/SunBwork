<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') return;

        Schema::table('user_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('user_settings', 'lunch_start')) {
                $table->string('lunch_start', 5)->default('12:00')->after('calendar_view');
            }
            if (!Schema::hasColumn('user_settings', 'lunch_end')) {
                $table->string('lunch_end', 5)->default('13:00')->after('lunch_start');
            }
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') return;

        Schema::table('user_settings', function (Blueprint $table) {
            $table->dropColumn(['lunch_start', 'lunch_end']);
        });
    }
};
