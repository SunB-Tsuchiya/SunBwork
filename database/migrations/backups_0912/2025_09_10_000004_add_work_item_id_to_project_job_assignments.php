<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('project_job_assignments') && !Schema::hasColumn('project_job_assignments', 'work_item_id')) {
            Schema::table('project_job_assignments', function (Blueprint $table) {
                $table->unsignedBigInteger('work_item_id')->nullable()->index();
                $table->foreign('work_item_id')->references('id')->on('work_items')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('project_job_assignments') && Schema::hasColumn('project_job_assignments', 'work_item_id')) {
            Schema::table('project_job_assignments', function (Blueprint $table) {
                $table->dropForeign(['work_item_id']);
                $table->dropColumn('work_item_id');
            });
        }
    }
};
