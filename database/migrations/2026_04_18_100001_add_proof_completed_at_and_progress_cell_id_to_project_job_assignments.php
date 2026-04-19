<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_job_assignments', function (Blueprint $table) {
            // 校正済みマーク（校正管理者が complete を押した時刻、または直接割当完了時）
            $table->timestamp('proof_completed_at')->nullable()->after('completed');
            // 進行管理表セルへの逆引き FK（セル削除時は NULL）
            $table->unsignedBigInteger('progress_cell_id')->nullable()->after('proof_completed_at');
            $table->foreign('progress_cell_id')
                  ->references('id')
                  ->on('progress_cells')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('project_job_assignments', function (Blueprint $table) {
            $table->dropForeign(['progress_cell_id']);
            $table->dropColumn(['proof_completed_at', 'progress_cell_id']);
        });
    }
};
