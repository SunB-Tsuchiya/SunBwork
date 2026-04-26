<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('progress_cells', function (Blueprint $table) {
            $table->foreignId('schedule_id')->nullable()->constrained('project_schedules')->nullOnDelete()->after('subcontractor_id');
            $table->date('cell_deadline')->nullable()->after('schedule_id');
            $table->text('cell_note')->nullable()->after('cell_deadline');
            $table->timestamp('completed_at')->nullable()->after('cell_note');
        });
    }

    public function down(): void
    {
        Schema::table('progress_cells', function (Blueprint $table) {
            $table->dropForeign(['schedule_id']);
            $table->dropColumn(['schedule_id', 'cell_deadline', 'cell_note', 'completed_at']);
        });
    }
};
