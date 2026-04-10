<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite（テスト環境）は外部キー制約なしでカラムのみ追加
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('project_job_assignments', function (Blueprint $table) {
                $table->unsignedBigInteger('supersedes_assignment_id')->nullable()->after('source_assignment_id');
            });
            return;
        }

        Schema::table('project_job_assignments', function (Blueprint $table) {
            $table->unsignedBigInteger('supersedes_assignment_id')->nullable()->after('source_assignment_id');
            $table->foreign('supersedes_assignment_id')
                  ->references('id')
                  ->on('project_job_assignments')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('project_job_assignments', function (Blueprint $table) {
                $table->dropColumn('supersedes_assignment_id');
            });
            return;
        }

        Schema::table('project_job_assignments', function (Blueprint $table) {
            $table->dropForeign(['supersedes_assignment_id']);
            $table->dropColumn('supersedes_assignment_id');
        });
    }
};
