<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        if (Schema::hasTable('project_job_assignments')) {
            Schema::table('project_job_assignments', function (Blueprint $table) {
                if (Schema::hasColumn('project_job_assignments', 'difficulty')) {
                    $table->dropColumn('difficulty');
                }
            });
        }

        if (Schema::hasTable('project_job_assignment_by_myself')) {
            Schema::table('project_job_assignment_by_myself', function (Blueprint $table) {
                if (Schema::hasColumn('project_job_assignment_by_myself', 'difficulty')) {
                    $table->dropColumn('difficulty');
                }
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('project_job_assignments')) {
            Schema::table('project_job_assignments', function (Blueprint $table) {
                if (!Schema::hasColumn('project_job_assignments', 'difficulty')) {
                    $table->string('difficulty')->nullable();
                }
            });
        }

        if (Schema::hasTable('project_job_assignment_by_myself')) {
            Schema::table('project_job_assignment_by_myself', function (Blueprint $table) {
                if (!Schema::hasColumn('project_job_assignment_by_myself', 'difficulty')) {
                    $table->string('difficulty')->nullable();
                }
            });
        }
    }
};
