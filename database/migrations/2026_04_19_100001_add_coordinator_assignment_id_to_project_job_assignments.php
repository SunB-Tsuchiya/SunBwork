<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_job_assignments', function (Blueprint $table) {
            // Coordinator→User リンク（pja100→pja101）専用FK。
            // source_assignment_id は「続きチェーン（日またぎ続きジョブ）」専用とする。
            $table->unsignedBigInteger('coordinator_assignment_id')->nullable()->after('source_assignment_id');
            $table->foreign('coordinator_assignment_id')
                  ->references('id')
                  ->on('project_job_assignments')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('project_job_assignments', function (Blueprint $table) {
            $table->dropForeign(['coordinator_assignment_id']);
            $table->dropColumn('coordinator_assignment_id');
        });
    }
};
