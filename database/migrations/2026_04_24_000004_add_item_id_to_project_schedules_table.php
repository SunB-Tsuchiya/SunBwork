<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_schedules', function (Blueprint $table) {
            $table->foreignId('project_job_item_id')->nullable()->after('project_job_id')
                ->constrained('project_job_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('project_schedules', function (Blueprint $table) {
            $table->dropForeign(['project_job_item_id']);
            $table->dropColumn('project_job_item_id');
        });
    }
};
