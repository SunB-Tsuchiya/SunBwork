<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('job_assignment_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('job_assignment_messages', 'accepted')) {
                $table->boolean('accepted')->nullable()->default(false)->after('read_at');
            }
            if (!Schema::hasColumn('job_assignment_messages', 'scheduled')) {
                $table->boolean('scheduled')->nullable()->default(false)->after('accepted');
            }
            if (!Schema::hasColumn('job_assignment_messages', 'scheduled_at')) {
                $table->timestamp('scheduled_at')->nullable()->after('scheduled');
            }
            // Add foreign key to project_job_assignments if column exists.
            // We avoid calling Doctrine schema manager to keep this migration simple
            // across environments; just attempt to add the FK and ignore failures.
            if (Schema::hasColumn('job_assignment_messages', 'project_job_assignment_id')) {
                try {
                    $table->foreign('project_job_assignment_id', 'jam_pja_id_fk')
                        ->references('id')
                        ->on('project_job_assignments')
                        ->onDelete('cascade');
                } catch (\Throwable $__e) {
                    // ignore if FK cannot be added (already exists or DB platform doesn't support)
                }
            }
        });
    }

    public function down()
    {
        Schema::table('job_assignment_messages', function (Blueprint $table) {
            if (Schema::hasColumn('job_assignment_messages', 'scheduled_at')) {
                $table->dropColumn('scheduled_at');
            }
            if (Schema::hasColumn('job_assignment_messages', 'scheduled')) {
                $table->dropColumn('scheduled');
            }
            if (Schema::hasColumn('job_assignment_messages', 'accepted')) {
                $table->dropColumn('accepted');
            }
            try {
                $table->dropForeign('jam_pja_id_fk');
            } catch (\Throwable $__e) {
                // ignore
            }
        });
    }
};
