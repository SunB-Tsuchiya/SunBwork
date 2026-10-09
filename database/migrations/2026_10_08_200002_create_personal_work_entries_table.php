<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_work_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->date('work_date');
            $table->string('work_category', 30);
            $table->string('title')->nullable();
            $table->string('project_name_text')->nullable();
            $table->string('plan_slot', 20)->nullable();
            $table->time('planned_start_time')->nullable();
            $table->time('planned_end_time')->nullable();
            $table->decimal('actual_quantity', 10, 2)->nullable();
            $table->string('actual_unit', 30)->nullable();
            $table->text('result_note')->nullable();
            $table->string('entry_visibility', 20)->default('department');
            $table->foreignId('project_job_id')->nullable()->constrained('project_jobs')->nullOnDelete();
            $table->foreignId('project_job_assignment_id')->nullable()->constrained('project_job_assignments')->nullOnDelete();
            $table->foreignId('source_event_id')->nullable()->unique()->constrained('events')->nullOnDelete();
            $table->foreignId('diary_id')->nullable()->constrained('diaries')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'work_date']);
            $table->index(['company_id', 'department_id', 'work_date'], 'personal_work_department_date_idx');
            $table->unique(['diary_id', 'source_event_id'], 'personal_work_diary_event_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_work_entries');
    }
};
