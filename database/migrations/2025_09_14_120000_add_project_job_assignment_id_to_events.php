<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds a nullable project_job_assignment_id foreign key to events.
     */
    public function up()
    {
        if (! Schema::hasTable('events')) return;

        Schema::table('events', function (Blueprint $table) {
            if (! Schema::hasColumn('events', 'project_job_assignment_id')) {
                $table->unsignedBigInteger('project_job_assignment_id')->nullable()->after('user_id');
                // Add foreign key if the assignments table exists; wrap in try/catch to avoid platform-specific errors
                try {
                    if (Schema::hasTable('project_job_assignments')) {
                        $table->foreign('project_job_assignment_id')->references('id')->on('project_job_assignments')->onDelete('set null');
                    }
                } catch (\Throwable $__e) {
                    // non-fatal: if FK cannot be created due to engine or platform differences, leave the column nullable without FK
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        if (! Schema::hasTable('events')) return;

        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'project_job_assignment_id')) {
                try {
                    $table->dropForeign(['project_job_assignment_id']);
                } catch (\Throwable $__e) {
                    // ignore
                }
                $table->dropColumn('project_job_assignment_id');
            }
        });
    }
};
