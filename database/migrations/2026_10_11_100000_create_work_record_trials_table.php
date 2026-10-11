<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_record_trials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->date('work_date');
            $table->time('recorded_time')->nullable();
            $table->foreignId('project_job_id')->nullable()->constrained('project_jobs')->nullOnDelete();
            $table->string('project_name_text')->nullable();
            $table->string('work_category', 30);
            $table->string('activity_type', 50);
            $table->string('title')->nullable();
            $table->string('progress_state', 30);
            $table->unsignedSmallInteger('approximate_minutes')->nullable();
            $table->text('note')->nullable();
            $table->uuid('series_id');
            $table->timestamps();

            $table->index(['user_id', 'work_date', 'recorded_time'], 'work_record_trials_user_day_idx');
            $table->index(['user_id', 'series_id'], 'work_record_trials_series_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_record_trials');
    }
};
