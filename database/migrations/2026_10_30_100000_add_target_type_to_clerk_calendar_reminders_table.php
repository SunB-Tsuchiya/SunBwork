<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clerk_calendar_reminders', function (Blueprint $table) {
            // 既存行は all（全員表示）のまま
            $table->string('target_type', 20)->default('all')->after('color_key');
        });
    }

    public function down(): void
    {
        Schema::table('clerk_calendar_reminders', function (Blueprint $table) {
            $table->dropColumn('target_type');
        });
    }
};
