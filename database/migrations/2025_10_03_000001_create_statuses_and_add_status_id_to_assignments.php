<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('statuses')) {
            Schema::create('statuses', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->string('name');
                $table->timestamps();
            });
        }

        if (Schema::hasTable('project_job_assignments') && !Schema::hasColumn('project_job_assignments', 'status_id')) {
            Schema::table('project_job_assignments', function (Blueprint $table) {
                $table->unsignedBigInteger('status_id')->nullable()->after('completed');
                $table->foreign('status_id')->references('id')->on('statuses')->nullOnDelete();
            });
        }

        if (Schema::hasTable('project_job_assignment_by_myself') && !Schema::hasColumn('project_job_assignment_by_myself', 'status_id')) {
            Schema::table('project_job_assignment_by_myself', function (Blueprint $table) {
                $table->unsignedBigInteger('status_id')->nullable()->after('completed');
                $table->foreign('status_id')->references('id')->on('statuses')->nullOnDelete();
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('project_job_assignment_by_myself') && Schema::hasColumn('project_job_assignment_by_myself', 'status_id')) {
            Schema::table('project_job_assignment_by_myself', function (Blueprint $table) {
                $table->dropForeign(['status_id']);
                $table->dropColumn('status_id');
            });
        }

        if (Schema::hasTable('project_job_assignments') && Schema::hasColumn('project_job_assignments', 'status_id')) {
            Schema::table('project_job_assignments', function (Blueprint $table) {
                $table->dropForeign(['status_id']);
                $table->dropColumn('status_id');
            });
        }

        if (Schema::hasTable('statuses')) {
            Schema::dropIfExists('statuses');
        }
    }
};
