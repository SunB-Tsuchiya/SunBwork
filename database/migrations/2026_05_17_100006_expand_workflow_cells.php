<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_cells', function (Blueprint $table) {
            $table->string('cell_type', 32)->default('worker')->after('stage_key');
            $table->text('value_text')->nullable()->after('cell_type');
            $table->date('value_date')->nullable()->after('value_text');
            $table->boolean('value_bool')->nullable()->after('value_date');
            $table->foreignId('value_user_id')->nullable()->after('value_bool')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('value_subcontractor_id')->nullable()->after('value_user_id')
                ->constrained('subcontractors')->nullOnDelete();
            $table->foreignId('proof_assignment_id')->nullable()->after('value_subcontractor_id')
                ->constrained('project_job_assignments')->nullOnDelete();
            $table->foreignId('schedule_id')->nullable()->after('proof_assignment_id')
                ->constrained('project_schedules')->nullOnDelete();
            $table->date('cell_deadline')->nullable()->after('schedule_id');
        });

        // assigned_user_id → value_user_id にコピー（後方互換のため両方保持）
        DB::statement('UPDATE workflow_cells SET value_user_id = assigned_user_id WHERE assigned_user_id IS NOT NULL');
        // assignment_id がある既存セルは worker 型として確定済み（default が 'worker' なので変更不要）
    }

    public function down(): void
    {
        Schema::table('workflow_cells', function (Blueprint $table) {
            $table->dropForeign(['value_user_id']);
            $table->dropForeign(['value_subcontractor_id']);
            $table->dropForeign(['proof_assignment_id']);
            $table->dropForeign(['schedule_id']);
            $table->dropColumn([
                'cell_type', 'value_text', 'value_date', 'value_bool',
                'value_user_id', 'value_subcontractor_id',
                'proof_assignment_id', 'schedule_id', 'cell_deadline',
            ]);
        });
    }
};
