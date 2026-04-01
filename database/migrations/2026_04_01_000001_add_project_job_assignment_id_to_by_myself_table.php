<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('project_job_assignment_by_myself')) {
            return;
        }
        if (Schema::hasColumn('project_job_assignment_by_myself', 'project_job_assignment_id')) {
            return;
        }

        Schema::table('project_job_assignment_by_myself', function (Blueprint $table) {
            $table->unsignedBigInteger('project_job_assignment_id')
                ->nullable()
                ->after('project_job_id')
                ->comment('Coordinator が割り振った project_job_assignments.id への参照');

            try {
                // 制約名を短くして MySQL の 64 文字制限を回避
                $table->foreign('project_job_assignment_id', 'pjabm_pja_id_foreign')
                    ->references('id')
                    ->on('project_job_assignments')
                    ->onDelete('set null');
            } catch (\Throwable $e) {
                // 外部キー制約が設定できない環境では無視
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('project_job_assignment_by_myself')) {
            return;
        }
        if (! Schema::hasColumn('project_job_assignment_by_myself', 'project_job_assignment_id')) {
            return;
        }

        Schema::table('project_job_assignment_by_myself', function (Blueprint $table) {
            try {
                $table->dropForeign('pjabm_pja_id_foreign');
            } catch (\Throwable $e) {
                // ignore
            }
            $table->dropColumn('project_job_assignment_id');
        });
    }
};
