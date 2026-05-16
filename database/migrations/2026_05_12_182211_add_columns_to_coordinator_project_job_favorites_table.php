<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * try/catch はコールバック外で実行しないと SQLite では効かないため、
     * Schema::getIndexes / getForeignKeys で事前チェックする。
     */
    public function up(): void
    {
        if (!Schema::hasColumn('coordinator_project_job_favorites', 'user_id')) {
            Schema::table('coordinator_project_job_favorites', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->after('id');
            });
        }
        if (!Schema::hasColumn('coordinator_project_job_favorites', 'project_job_id')) {
            Schema::table('coordinator_project_job_favorites', function (Blueprint $table) {
                $table->unsignedBigInteger('project_job_id')->after('user_id');
            });
        }

        $indexName     = 'coordinator_project_job_favorites_user_id_project_job_id_unique';
        $existingNames = collect(Schema::getIndexes('coordinator_project_job_favorites'))->pluck('name');
        if (!$existingNames->contains($indexName)) {
            Schema::table('coordinator_project_job_favorites', function (Blueprint $table) {
                $table->unique(['user_id', 'project_job_id']);
            });
        }

        $foreignKeys = collect(Schema::getForeignKeys('coordinator_project_job_favorites'));
        if (!$foreignKeys->contains(fn($fk) => in_array('user_id', $fk['columns']))) {
            Schema::table('coordinator_project_job_favorites', function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            });
        }
        if (!$foreignKeys->contains(fn($fk) => in_array('project_job_id', $fk['columns']))) {
            Schema::table('coordinator_project_job_favorites', function (Blueprint $table) {
                $table->foreign('project_job_id')->references('id')->on('project_jobs')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::table('coordinator_project_job_favorites', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['project_job_id']);
            $table->dropUnique(['user_id', 'project_job_id']);
            $table->dropColumn(['user_id', 'project_job_id']);
        });
    }
};
