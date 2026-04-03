<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('progress_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_job_id')->constrained('project_jobs')->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('progress_templates')->nullOnDelete();
            $table->string('name');
            $table->json('column_config');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progress_sheets');
    }
};
