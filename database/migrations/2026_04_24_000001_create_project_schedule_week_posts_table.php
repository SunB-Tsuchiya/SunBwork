<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_schedule_week_posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_job_id');
            $table->unsignedBigInteger('user_id');
            $table->integer('year');
            $table->integer('week');
            $table->text('body');
            $table->timestamps();

            $table->foreign('project_job_id')->references('id')->on('project_jobs')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['project_job_id', 'year', 'week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_schedule_week_posts');
    }
};
