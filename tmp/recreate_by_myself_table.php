<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

if (Schema::hasTable('project_job_assignment_by_myself')) {
    echo "Table already exists.\n";
    return;
}

Schema::create('project_job_assignment_by_myself', function (Blueprint $table) {
    $table->bigIncrements('id');
    $table->unsignedBigInteger('project_job_id')->nullable()->index();
    $table->unsignedBigInteger('project_job_assignment_id')->nullable()->index()
        ->comment('Coordinator が割り振った project_job_assignments.id への参照');
    $table->unsignedBigInteger('user_id')->nullable()->index();
    $table->string('title')->nullable();
    $table->text('detail')->nullable();
    $table->string('difficulty')->default('normal');
    $table->date('desired_start_date')->nullable();
    $table->date('desired_end_date')->nullable();
    $table->time('desired_time')->nullable();
    $table->decimal('estimated_hours', 6, 2)->nullable();
    $table->boolean('assigned')->default(false);
    $table->boolean('accepted')->default(false);
    $table->boolean('completed')->default(false);
    $table->boolean('scheduled')->default(false);
    $table->unsignedBigInteger('size_id')->nullable()->index();
    $table->unsignedBigInteger('work_item_type_id')->nullable()->index();
    $table->unsignedBigInteger('stage_id')->nullable()->index();
    $table->unsignedBigInteger('status_id')->nullable()->index();
    $table->unsignedBigInteger('company_id')->nullable()->index();
    $table->unsignedBigInteger('department_id')->nullable()->index();
    $table->integer('amounts')->nullable();
    $table->string('amounts_unit')->nullable();
    $table->timestamp('read_at')->nullable();
    $table->timestamp('scheduled_at')->nullable();
    $table->timestamp('starts_at')->nullable();
    $table->timestamp('ends_at')->nullable();
    $table->time('start_time')->nullable();
    $table->unsignedBigInteger('sender_id')->nullable()->index();
    $table->unsignedBigInteger('difficulty_id')->nullable()->index();
    $table->timestamps();

    try {
        $table->foreign('project_job_id')->references('id')->on('project_jobs')->onDelete('set null');
        $table->foreign('project_job_assignment_id', 'pjabm_pja_id_foreign')
            ->references('id')->on('project_job_assignments')->onDelete('set null');
        $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        $table->foreign('sender_id')->references('id')->on('users')->onDelete('set null');
        $table->foreign('size_id')->references('id')->on('sizes')->onDelete('set null');
        $table->foreign('work_item_type_id')->references('id')->on('work_item_types')->onDelete('set null');
        $table->foreign('stage_id')->references('id')->on('stages')->onDelete('set null');
        $table->foreign('status_id')->references('id')->on('statuses')->onDelete('set null');
        $table->foreign('company_id')->references('id')->on('companies')->onDelete('set null');
        $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
        $table->foreign('difficulty_id')->references('id')->on('difficulties')->onDelete('set null');
    } catch (Throwable $e) {
        // FK 作成失敗は無視
    }
});

echo "Table 'project_job_assignment_by_myself' created successfully.\n";
