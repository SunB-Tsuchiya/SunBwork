<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_job_id');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();
            $table->integer('progress')->default(0);
            $table->string('color')->nullable();
            $table->string('status')->nullable();
            $table->integer('order')->default(0);
            $table->json('metadata')->nullable();
            $table->date('scheduled_date')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('project_schedule_comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_schedule_id');
            $table->unsignedBigInteger('user_id');
            $table->text('comment');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_schedule_comments');
        Schema::dropIfExists('project_schedules');
    }
};
