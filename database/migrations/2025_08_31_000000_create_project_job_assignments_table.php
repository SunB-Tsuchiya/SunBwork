<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_job_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_job_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();

            // canonical fields
            $table->string('title')->nullable();
            $table->text('detail')->nullable();
            $table->string('difficulty')->default('normal');

            // desired dates/times: store date and time separately to avoid TZ issues
            $table->date('desired_start_date')->nullable();
            $table->date('desired_end_date')->nullable();
            $table->time('desired_time')->nullable();

            // fractional estimated hours (e.g. 1.50 == 1 hour 30 minutes)
            $table->decimal('estimated_hours', 6, 2)->nullable();

            $table->boolean('assigned')->default(false);
            $table->boolean('accepted')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_job_assignments');
    }
};
