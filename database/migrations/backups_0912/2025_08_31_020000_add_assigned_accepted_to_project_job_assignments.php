<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // noop if columns already exist with desired types; safe guard for migrate:fresh
        if (!Schema::hasTable('project_job_assignments')) return;

        try {
            Schema::table('project_job_assignments', function (Blueprint $table) {
                if (Schema::hasColumn('project_job_assignments', 'assigned')) {
                    $table->boolean('assigned')->default(false)->change();
                }
                if (Schema::hasColumn('project_job_assignments', 'accepted')) {
                    $table->boolean('accepted')->default(false)->change();
                }
            });
        } catch (\Exception $e) {
            // ignore change failures during fresh setups
        }
    }

    public function down(): void
    {
        // revert not implemented
    }
};
