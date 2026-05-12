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
        Schema::table('coordinator_project_job_favorites', function (Blueprint $table) {
            if (!Schema::hasColumn('coordinator_project_job_favorites', 'user_id')) {
                $table->unsignedBigInteger('user_id')->after('id');
            }
            if (!Schema::hasColumn('coordinator_project_job_favorites', 'project_job_id')) {
                $table->unsignedBigInteger('project_job_id')->after('user_id');
            }
            // インデックスが存在しない場合のみ追加
            try {
                $table->unique(['user_id', 'project_job_id']);
            } catch (\Exception $e) {}
            try {
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            } catch (\Exception $e) {}
            try {
                $table->foreign('project_job_id')->references('id')->on('project_jobs')->onDelete('cascade');
            } catch (\Exception $e) {}
        });
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
