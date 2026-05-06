<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_job_items', function (Blueprint $table) {
            $table->unsignedBigInteger('linked_schedule_id')->nullable()->after('calendar_linked');
            $table->foreign('linked_schedule_id')
                  ->references('id')->on('project_schedules')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('project_job_items', function (Blueprint $table) {
            $table->dropForeign(['linked_schedule_id']);
            $table->dropColumn('linked_schedule_id');
        });
    }
};
