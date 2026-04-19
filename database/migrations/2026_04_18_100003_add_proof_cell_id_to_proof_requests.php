<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proof_requests', function (Blueprint $table) {
            // 校正依頼がどの進行表セルから送られたかを記録
            $table->unsignedBigInteger('proof_cell_id')->nullable()->after('project_job_assignment_id');
            $table->foreign('proof_cell_id')
                  ->references('id')
                  ->on('progress_cells')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('proof_requests', function (Blueprint $table) {
            $table->dropForeign(['proof_cell_id']);
            $table->dropColumn('proof_cell_id');
        });
    }
};
