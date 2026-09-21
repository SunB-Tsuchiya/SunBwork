<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mginbon')->create('mginbon_work_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mginbon_project_id')->constrained('mginbon_projects')->cascadeOnDelete();
            $table->foreignId('mginbon_item_id')->constrained('mginbon_items')->cascadeOnDelete();
            $table->foreignId('mginbon_stage_definition_id')->constrained('mginbon_stage_definitions')->restrictOnDelete();
            $table->unsignedBigInteger('user_id')->nullable()->comment('SBWork本体DBのusers.id');
            $table->unsignedBigInteger('subcontractor_id')->nullable()->comment('SBWork本体DBのsubcontractors.id');
            $table->unsignedBigInteger('project_job_assignment_id')->nullable()->comment('SBWork本体DBのproject_job_assignments.id');
            $table->string('status', 30)->default('assigned');
            $table->unsignedBigInteger('created_by')->nullable()->comment('SBWork本体DBのusers.id');
            $table->timestamps();
            $table->index(['mginbon_item_id', 'mginbon_stage_definition_id'], 'mginbon_package_item_stage_idx');
            $table->index('project_job_assignment_id');
        });

        Schema::connection('mginbon')->create('mginbon_work_package_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mginbon_work_package_id')->constrained('mginbon_work_packages')->cascadeOnDelete();
            $table->foreignId('mginbon_stage_task_id')->constrained('mginbon_stage_tasks')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['mginbon_work_package_id', 'mginbon_stage_task_id'], 'mginbon_package_task_unique');
            $table->index('mginbon_stage_task_id');
        });
    }

    public function down(): void
    {
        Schema::connection('mginbon')->dropIfExists('mginbon_work_package_tasks');
        Schema::connection('mginbon')->dropIfExists('mginbon_work_packages');
    }
};
