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

        $schema->create('mginbon_projects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_job_id')->nullable()->comment('SBWork本体DBのproject_jobs.id');
            $table->unsignedSmallInteger('year')->unique();
            $table->string('name', 200);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status', 20)->default('draft');
            $table->unsignedBigInteger('created_by')->nullable()->comment('SBWork本体DBのusers.id');
            $table->timestamps();
            $table->index('project_job_id');
        });

        $schema->create('mginbon_import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mginbon_project_id')->nullable()->constrained('mginbon_projects')->nullOnDelete();
            $table->string('source_filename');
            $table->string('source_sha256', 64);
            $table->string('source_encoding', 30)->default('CP932');
            $table->unsignedSmallInteger('source_year');
            $table->string('status', 30)->default('previewed');
            $table->unsignedInteger('record_count')->default(0);
            $table->unsignedSmallInteger('column_count')->default(0);
            $table->json('summary_json')->nullable();
            $table->timestamp('previewed_at')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->comment('SBWork本体DBのusers.id');
            $table->timestamps();
            $table->index(['source_year', 'status']);
            $table->index('source_sha256');
        });

        $schema->create('mginbon_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mginbon_import_batch_id')->constrained('mginbon_import_batches')->cascadeOnDelete();
            $table->unsignedInteger('source_row_number');
            $table->string('raw_mikuni_code', 50)->nullable();
            $table->string('raw_n_code', 100)->nullable();
            $table->string('raw_school_name', 300)->nullable();
            $table->string('raw_media_type', 100)->nullable();
            $table->json('raw_json');
            $table->json('normalized_json')->nullable();
            $table->string('resolution_status', 30)->default('pending');
            $table->json('warning_codes')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable()->comment('SBWork本体DBのusers.id');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['mginbon_import_batch_id', 'source_row_number'], 'mginbon_import_rows_batch_row_unique');
            $table->index(['resolution_status', 'raw_media_type'], 'mginbon_import_rows_resolution_media_idx');
        });
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONNECTION);
        $schema->dropIfExists('mginbon_import_rows');
        $schema->dropIfExists('mginbon_import_batches');
        $schema->dropIfExists('mginbon_projects');
    }
};
