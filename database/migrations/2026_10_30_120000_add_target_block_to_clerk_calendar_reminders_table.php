<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clerk_calendar_reminders', function (Blueprint $table) {
            // all=全社 / honsha=本社 / odai=小台（サン・ブレーンのみ。既存は all）
            $table->string('target_block', 20)->default('all')->after('target_type');
        });
    }

    public function down(): void
    {
        Schema::table('clerk_calendar_reminders', function (Blueprint $table) {
            $table->dropColumn('target_block');
        });
    }
};
