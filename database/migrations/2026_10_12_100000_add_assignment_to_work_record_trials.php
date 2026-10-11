<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_record_trials', function (Blueprint $table) {
            $table->foreignId('project_job_assignment_id')->nullable()
                ->after('project_job_id')->constrained('project_job_assignments')->nullOnDelete();
            $table->index(['user_id', 'project_job_assignment_id'], 'work_record_trials_user_assignment_idx');
        });
    }

    public function down(): void
    {
        Schema::table('work_record_trials', function (Blueprint $table) {
            $table->dropIndex('work_record_trials_user_assignment_idx');
            $table->dropConstrainedForeignId('project_job_assignment_id');
        });
    }
};
