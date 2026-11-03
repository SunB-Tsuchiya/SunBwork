<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_work_entries', function (Blueprint $table) {
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('source_entry_id')->nullable()->constrained('personal_work_entries')->restrictOnDelete();
            $table->foreignId('source_assignment_id')->nullable()->constrained('project_job_assignments')->restrictOnDelete();
        });
        Schema::table('project_job_assignments', function (Blueprint $table) {
            $table->foreignId('source_personal_work_entry_id')->nullable()->constrained('personal_work_entries')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('project_job_assignments', fn (Blueprint $table) => $table->dropConstrainedForeignId('source_personal_work_entry_id'));
        Schema::table('personal_work_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_assignment_id');
            $table->dropConstrainedForeignId('source_entry_id');
            $table->dropColumn('completed_at');
        });
    }
};
