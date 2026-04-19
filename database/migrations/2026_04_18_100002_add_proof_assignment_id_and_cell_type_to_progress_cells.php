<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('progress_cells', function (Blueprint $table) {
            // 校正ジョブへの FK（proof_tanto / proof_register セルが使用）
            $table->unsignedBigInteger('proof_assignment_id')->nullable()->after('assignment_id');
            $table->foreign('proof_assignment_id')
                  ->references('id')
                  ->on('project_job_assignments')
                  ->onDelete('set null');
            // セル種別（v2 進行表で使用。旧設計は NULL）
            $table->string('cell_type', 32)->nullable()->after('proof_assignment_id');
        });
    }

    public function down(): void
    {
        Schema::table('progress_cells', function (Blueprint $table) {
            $table->dropForeign(['proof_assignment_id']);
            $table->dropColumn(['proof_assignment_id', 'cell_type']);
        });
    }
};
