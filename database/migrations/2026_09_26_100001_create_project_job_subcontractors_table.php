<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_job_subcontractors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_job_id')->constrained('project_jobs')->cascadeOnDelete();
            $table->foreignId('subcontractor_id')->constrained('subcontractors')->cascadeOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique(['project_job_id', 'subcontractor_id'], 'project_job_subcontractor_unique');
        });

        $now = now();
        DB::table('project_job_assignments')->whereNotNull('subcontractor_id')
            ->select('project_job_id', 'subcontractor_id')->distinct()->orderBy('project_job_id')
            ->chunk(500, function ($rows) use ($now) {
                DB::table('project_job_subcontractors')->insertOrIgnore($rows->map(fn ($row) => [
                    'project_job_id' => $row->project_job_id,
                    'subcontractor_id' => $row->subcontractor_id,
                    'created_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_job_subcontractors');
    }
};
