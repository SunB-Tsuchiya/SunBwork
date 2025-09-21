<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('project_job_assignment_by_myself', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('project_job_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('title')->nullable();
            $table->text('detail')->nullable();
            $table->string('difficulty')->nullable();
            $table->float('estimated_hours')->nullable();
            $table->date('desired_start_date')->nullable();
            $table->date('desired_end_date')->nullable();
            $table->string('desired_time')->nullable();
            $table->timestamp('desired_at')->nullable();

            // lookup fields
            $table->unsignedBigInteger('work_item_type_id')->nullable()->index();
            $table->unsignedBigInteger('size_id')->nullable()->index();
            $table->unsignedBigInteger('stage_id')->nullable()->index();
            $table->unsignedBigInteger('status_id')->nullable()->index();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('department_id')->nullable()->index();

            // quantity
            $table->integer('amounts')->nullable();
            $table->string('amounts_unit')->nullable();

            // flags
            $table->boolean('assigned')->default(false);
            $table->boolean('accepted')->default(false);
            $table->boolean('completed')->default(false);

            // scheduling
            $table->boolean('scheduled')->default(false);
            $table->timestamp('scheduled_at')->nullable();

            // timestamps
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // foreign keys - optional and safe (do not cascade delete)
            try {
                $table->foreign('project_job_id')->references('id')->on('project_jobs')->onDelete('set null');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
                $table->foreign('work_item_type_id')->references('id')->on('work_item_types')->onDelete('set null');
                $table->foreign('size_id')->references('id')->on('sizes')->onDelete('set null');
                $table->foreign('stage_id')->references('id')->on('stages')->onDelete('set null');
                $table->foreign('status_id')->references('id')->on('statuses')->onDelete('set null');
                $table->foreign('company_id')->references('id')->on('companies')->onDelete('set null');
                $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
            } catch (\Throwable $e) {
                // ignore if foreign keys cannot be created in some environments
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('project_job_assignment_by_myself');
    }
};
