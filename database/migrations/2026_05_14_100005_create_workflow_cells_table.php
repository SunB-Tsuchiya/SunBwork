<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_cells', function (Blueprint $table) {
            $table->id();
            $table->foreignId('row_id')->constrained('workflow_rows')->cascadeOnDelete();
            $table->string('stage_key', 64);
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assignment_id')->nullable()->constrained('project_job_assignments')->nullOnDelete();
            $table->date('work_date')->nullable();
            $table->unsignedInteger('work_minutes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('cell_note')->nullable();
            $table->foreignId('cell_note_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['row_id', 'stage_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_cells');
    }
};
