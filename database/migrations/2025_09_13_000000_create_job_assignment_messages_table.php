<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_assignment_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_job_assignment_id')->nullable()->index();
            $table->unsignedBigInteger('sender_id')->nullable()->index();
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->foreign('project_job_assignment_id')->references('id')->on('project_job_assignments')->onDelete('set null');
            $table->foreign('sender_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_assignment_messages');
    }
};
