<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        // add columns to project_job_assignments if not exists
        if (Schema::hasTable('project_job_assignments')) {
            Schema::table('project_job_assignments', function (Blueprint $table) {
                if (!Schema::hasColumn('project_job_assignments', 'size_id')) {
                    $table->unsignedBigInteger('size_id')->nullable()->after('difficulty');
                }
                if (!Schema::hasColumn('project_job_assignments', 'work_item_type_id')) {
                    $table->unsignedBigInteger('work_item_type_id')->nullable()->after('size_id');
                }
                if (!Schema::hasColumn('project_job_assignments', 'stage_id')) {
                    $table->unsignedBigInteger('stage_id')->nullable()->after('work_item_type_id');
                }
                if (!Schema::hasColumn('project_job_assignments', 'status_id')) {
                    $table->unsignedBigInteger('status_id')->nullable()->after('stage_id');
                }
                if (!Schema::hasColumn('project_job_assignments', 'company_id')) {
                    $table->unsignedBigInteger('company_id')->nullable()->after('status_id');
                }
                if (!Schema::hasColumn('project_job_assignments', 'department_id')) {
                    $table->unsignedBigInteger('department_id')->nullable()->after('company_id');
                }
            });
        }

        // drop work_items table if exists
        if (Schema::hasTable('work_items')) {
            Schema::dropIfExists('work_items');
        }
    }

    public function down()
    {
        // recreate work_items table minimally for rollback (best-effort)
        if (!Schema::hasTable('work_items')) {
            Schema::create('work_items', function (Blueprint $table) {
                $table->id();
                $table->string('title')->nullable();
                $table->text('description')->nullable();
                $table->unsignedBigInteger('work_item_type_id')->nullable();
                $table->unsignedBigInteger('size_id')->nullable();
                $table->unsignedBigInteger('status_id')->nullable();
                $table->unsignedBigInteger('company_id')->nullable();
                $table->unsignedBigInteger('department_id')->nullable();
                $table->unsignedBigInteger('project_job_assignment_id')->nullable();
                $table->timestamps();
            });
        }

        // drop added columns from project_job_assignments
        if (Schema::hasTable('project_job_assignments')) {
            Schema::table('project_job_assignments', function (Blueprint $table) {
                if (Schema::hasColumn('project_job_assignments', 'department_id')) {
                    $table->dropColumn('department_id');
                }
                if (Schema::hasColumn('project_job_assignments', 'company_id')) {
                    $table->dropColumn('company_id');
                }
                if (Schema::hasColumn('project_job_assignments', 'status_id')) {
                    $table->dropColumn('status_id');
                }
                if (Schema::hasColumn('project_job_assignments', 'stage_id')) {
                    $table->dropColumn('stage_id');
                }
                if (Schema::hasColumn('project_job_assignments', 'work_item_type_id')) {
                    $table->dropColumn('work_item_type_id');
                }
                if (Schema::hasColumn('project_job_assignments', 'size_id')) {
                    $table->dropColumn('size_id');
                }
            });
        }
    }
};
