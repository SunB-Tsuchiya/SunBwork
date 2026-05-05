<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meeting_definitions', function (Blueprint $table) {
            $table->unsignedTinyInteger('week_of_month')
                ->nullable()
                ->after('day_of_week')
                ->comment('毎月の第N週 (1〜5)。recurrence=monthly のみ使用');
        });
    }

    public function down(): void
    {
        Schema::table('meeting_definitions', function (Blueprint $table) {
            $table->dropColumn('week_of_month');
        });
    }
};
