<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('project_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_job_id')->nullable()->index();
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('status')->default('planned');
            $table->integer('order')->default(0);
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('project_schedule_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_schedule_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('role')->nullable();
            $table->timestamps();
        });

        Schema::create('project_schedule_comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_schedule_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->text('body');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('project_schedule_comments');
        Schema::dropIfExists('project_schedule_assignments');
        Schema::dropIfExists('project_schedules');
    }
};
