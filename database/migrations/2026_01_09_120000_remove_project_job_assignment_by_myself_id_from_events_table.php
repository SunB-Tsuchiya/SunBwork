<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Safely drop the `project_job_assignment_by_myself_id` column from `events`.
     * If a foreign key exists it will be removed first.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('events')) {
            return;
        }

        if (!Schema::hasColumn('events', 'project_job_assignment_by_myself_id')) {
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // Try to detect a foreign key constraint name and drop it first (MySQL only)
        if (DB::getDriverName() !== 'sqlite') {
            $database = DB::getDatabaseName();
            $rows = DB::select(
                'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
                [$database, 'events', 'project_job_assignment_by_myself_id']
            );

            if (!empty($rows) && !empty($rows[0]->CONSTRAINT_NAME)) {
                $constraint = $rows[0]->CONSTRAINT_NAME;
                Schema::table('events', function (Blueprint $table) use ($constraint) {
                    $table->dropForeign($constraint);
                });
            }
        }

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('project_job_assignment_by_myself_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Re-create the nullable column (no FK added automatically).
     * Add a FK manually if you need it restored to the original behaviour.
     *
     * @return void
     */
    public function down()
    {
        if (!Schema::hasTable('events')) {
            return;
        }

        if (!Schema::hasColumn('events', 'project_job_assignment_by_myself_id')) {
            Schema::table('events', function (Blueprint $table) {
                $table->unsignedBigInteger('project_job_assignment_by_myself_id')->nullable()->after('id');
            });
        }
    }
};
