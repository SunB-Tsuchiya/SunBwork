<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONNECTION = 'mginbon';

    public function up(): void
    {
        $schema = Schema::connection(self::CONNECTION);

        $schema->create('mginbon_media_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $schema->create('mginbon_subjects', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 50);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $schema->create('mginbon_production_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mginbon_project_id')->constrained('mginbon_projects')->cascadeOnDelete();
            $table->string('unit_type', 30);
            $table->unsignedBigInteger('n_exam_id')->nullable()->comment('SBWork本体DBのn_exams.id');
            $table->unsignedBigInteger('n_school_id')->nullable()->comment('SBWork本体DBのn_schools.id');
            $table->string('mikuni_code', 50)->nullable();
            $table->string('n_code', 100)->nullable();
            $table->string('display_name', 300);
            $table->string('school_category', 50)->nullable();
            $table->string('n_category', 100)->nullable();
            $table->string('review_status', 30)->default('draft');
            $table->timestamps();
            $table->index(['mginbon_project_id', 'unit_type'], 'mginbon_units_project_type_idx');
            $table->index(['mginbon_project_id', 'mikuni_code'], 'mginbon_units_project_mikuni_idx');
            $table->index(['mginbon_project_id', 'n_code'], 'mginbon_units_project_ncode_idx');
            $table->index('n_exam_id');
            $table->index('n_school_id');
        });

        $schema->create('mginbon_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mginbon_production_unit_id')->constrained('mginbon_production_units')->cascadeOnDelete();
            $table->foreignId('mginbon_media_type_id')->constrained('mginbon_media_types')->restrictOnDelete();
            $table->foreignId('mginbon_import_row_id')->nullable()->unique()->constrained('mginbon_import_rows')->nullOnDelete();
            $table->string('publication_status', 50)->nullable();
            $table->text('note')->nullable();
            $table->string('review_status', 30)->default('draft');
            $table->timestamps();
            $table->index(['mginbon_production_unit_id', 'mginbon_media_type_id'], 'mginbon_items_unit_media_idx');
        });

        $schema->create('mginbon_item_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mginbon_item_id')->constrained('mginbon_items')->cascadeOnDelete();
            $table->foreignId('mginbon_subject_id')->constrained('mginbon_subjects')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['mginbon_item_id', 'mginbon_subject_id'], 'mginbon_item_subject_unique');
        });

        $schema->create('mginbon_stage_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mginbon_project_id')->constrained('mginbon_projects')->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('name', 100);
            $table->string('activity_type', 30);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['mginbon_project_id', 'code'], 'mginbon_stage_project_code_unique');
        });

        $schema->create('mginbon_stage_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mginbon_item_id')->constrained('mginbon_items')->cascadeOnDelete();
            $table->foreignId('mginbon_item_subject_id')->nullable()->constrained('mginbon_item_subjects')->cascadeOnDelete();
            $table->foreignId('mginbon_stage_definition_id')->constrained('mginbon_stage_definitions')->restrictOnDelete();
            $table->string('status', 30)->default('not_started');
            $table->unsignedBigInteger('project_job_assignment_id')->nullable()->comment('SBWork本体DBのproject_job_assignments.id');
            $table->timestamps();
            $table->unique(
                ['mginbon_item_id', 'mginbon_item_subject_id', 'mginbon_stage_definition_id'],
                'mginbon_stage_task_scope_unique'
            );
            $table->index('project_job_assignment_id');
        });

        $schema->create('mginbon_stage_task_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mginbon_stage_task_id')->constrained('mginbon_stage_tasks')->cascadeOnDelete();
            $table->string('role_type', 50);
            $table->string('execution_type', 30)->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->comment('SBWork本体DBのusers.id');
            $table->unsignedBigInteger('department_id')->nullable()->comment('SBWork本体DBのdepartments.id');
            $table->unsignedBigInteger('subcontractor_id')->nullable()->comment('SBWork本体DBのsubcontractors.id');
            $table->string('legacy_value', 255)->nullable();
            $table->string('resolution_status', 30)->default('unresolved');
            $table->timestamps();
            $table->index(['mginbon_stage_task_id', 'role_type'], 'mginbon_participant_task_role_idx');
            $table->index('user_id');
            $table->index('subcontractor_id');
        });

        $schema->create('mginbon_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mginbon_item_id')->constrained('mginbon_items')->cascadeOnDelete();
            $table->foreignId('mginbon_item_subject_id')->nullable()->constrained('mginbon_item_subjects')->cascadeOnDelete();
            $table->string('code', 80);
            $table->date('occurred_on');
            $table->string('source', 30)->default('legacy_import');
            $table->timestamps();
            $table->unique(['mginbon_item_id', 'mginbon_item_subject_id', 'code'], 'mginbon_milestone_scope_unique');
        });

        $schema->create('mginbon_work_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mginbon_item_subject_id')->constrained('mginbon_item_subjects')->cascadeOnDelete();
            $table->string('work_type', 30);
            $table->string('execution_type', 30);
            $table->unsignedInteger('quantity')->default(0);
            $table->string('unit', 20)->default('point');
            $table->unsignedBigInteger('subcontractor_id')->nullable()->comment('SBWork本体DBのsubcontractors.id');
            $table->string('legacy_value', 50)->nullable();
            $table->timestamps();
            $table->unique(
                ['mginbon_item_subject_id', 'work_type', 'execution_type'],
                'mginbon_measurement_subject_work_execution_unique'
            );
            $table->index('subcontractor_id');
        });
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONNECTION);
        $schema->dropIfExists('mginbon_work_measurements');
        $schema->dropIfExists('mginbon_milestones');
        $schema->dropIfExists('mginbon_stage_task_participants');
        $schema->dropIfExists('mginbon_stage_tasks');
        $schema->dropIfExists('mginbon_stage_definitions');
        $schema->dropIfExists('mginbon_item_subjects');
        $schema->dropIfExists('mginbon_items');
        $schema->dropIfExists('mginbon_production_units');
        $schema->dropIfExists('mginbon_subjects');
        $schema->dropIfExists('mginbon_media_types');
    }
};
