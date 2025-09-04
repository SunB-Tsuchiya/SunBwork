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
            $table->date('scheduled_date');
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
