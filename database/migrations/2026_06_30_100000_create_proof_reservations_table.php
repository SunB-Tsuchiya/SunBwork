<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proof_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_job_id')->constrained('project_jobs')->cascadeOnDelete();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('requested_at_mode', 16)->default('datetime');
            $table->dateTime('requested_at')->nullable();
            $table->string('requested_at_text')->nullable();
            $table->string('deadline_mode', 16)->default('datetime');
            $table->dateTime('deadline_at')->nullable();
            $table->string('deadline_text')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('calendar_registered_at')->nullable();
            $table->timestamps();

            $table->index(['requested_at_mode', 'requested_at']);
            $table->index(['deadline_mode', 'deadline_at']);
            $table->index('calendar_registered_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proof_reservations');
    }
};
