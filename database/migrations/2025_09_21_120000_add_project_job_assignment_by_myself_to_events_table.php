<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('events') && !Schema::hasColumn('events', 'project_job_assignment_by_myself_id')) {
            Schema::table('events', function (Blueprint $table) {
                $table->unsignedBigInteger('project_job_assignment_by_myself_id')->nullable()->after('id');
                $table->foreign('project_job_assignment_by_myself_id')->references('id')->on('project_job_assignment_by_myself')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('events') && Schema::hasColumn('events', 'project_job_assignment_by_myself_id')) {
            Schema::table('events', function (Blueprint $table) {
                $table->dropForeign(['project_job_assignment_by_myself_id']);
                $table->dropColumn('project_job_assignment_by_myself_id');
            });
        }
    }
};
