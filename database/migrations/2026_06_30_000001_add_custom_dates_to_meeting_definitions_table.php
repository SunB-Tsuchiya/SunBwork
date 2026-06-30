<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meeting_definitions', function (Blueprint $table) {
            $table->json('custom_dates')
                ->nullable()
                ->after('week_of_month')
                ->comment('recurrence=custom_dates のときに使う開催日配列 (YYYY-MM-DD)');
        });

        DB::statement("ALTER TABLE meeting_definitions MODIFY COLUMN recurrence ENUM('weekly','biweekly','monthly','custom_dates') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE meeting_definitions MODIFY COLUMN recurrence ENUM('weekly','biweekly','monthly') NOT NULL");

        Schema::table('meeting_definitions', function (Blueprint $table) {
            $table->dropColumn('custom_dates');
        });
    }
};
