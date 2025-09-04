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
            $table->unsignedBigInteger('project_job_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('desired_start')->nullable();
            $table->timestamp('desired_end')->nullable();
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
