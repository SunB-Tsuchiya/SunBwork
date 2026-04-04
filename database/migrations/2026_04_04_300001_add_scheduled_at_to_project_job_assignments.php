<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * project_job_assignments に scheduled_at カラムを追加する。
 * 300001 (migrate_by_myself) は SQLite をスキップするため、
 * このマイグレーションで SQLite テスト環境にも追加する。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('project_job_assignments', 'scheduled_at')) {
            return;
        }

        Schema::table('project_job_assignments', function (Blueprint $table) {
            if (DB::getDriverName() === 'sqlite') {
                $table->timestamp('scheduled_at')->nullable();
            } else {
                $table->timestamp('scheduled_at')->nullable()->after('scheduled');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('project_job_assignments', 'scheduled_at')) {
            Schema::table('project_job_assignments', function (Blueprint $table) {
                $table->dropColumn('scheduled_at');
            });
        }
    }
};
