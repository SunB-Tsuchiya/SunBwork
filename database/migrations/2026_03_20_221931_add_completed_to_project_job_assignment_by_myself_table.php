<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('project_job_assignment_by_myself', function (Blueprint $table) {
            if (!Schema::hasColumn('project_job_assignment_by_myself', 'completed')) {
                $table->boolean('completed')->default(false)->after('accepted');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_job_assignment_by_myself', function (Blueprint $table) {
            if (Schema::hasColumn('project_job_assignment_by_myself', 'completed')) {
                $table->dropColumn('completed');
            }
        });
    }
};
