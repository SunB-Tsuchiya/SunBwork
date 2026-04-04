<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_job_assignments', function (Blueprint $table) {
            if (DB::getDriverName() === 'sqlite') {
                // SQLite: カラムのみ追加（外部キー制約は非対応）
                $table->unsignedBigInteger('source_assignment_id')->nullable();
            } else {
                $table->unsignedBigInteger('source_assignment_id')->nullable()->after('sender_id');
                $table->foreign('source_assignment_id')
                    ->references('id')
                    ->on('project_job_assignments')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('project_job_assignments', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign(['source_assignment_id']);
            }
            $table->dropColumn('source_assignment_id');
        });
    }
};
