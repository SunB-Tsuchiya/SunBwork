<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // new_job | new_job_info | completed | completed_info | progress_registered | progress_completed
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('project_job_id')->constrained('project_jobs')->cascadeOnDelete();
            $table->foreignId('assignment_id')->nullable()->constrained('project_job_assignments')->nullOnDelete();
            $table->string('message');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['recipient_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_notifications');
    }
};
