<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::table('work_job_continuations', fn (Blueprint $table) => $table->foreignId('assignment_id')->nullable()->constrained('project_job_assignments')->nullOnDelete());
    }
    public function down(): void
    {
        Schema::table('work_job_continuations', fn (Blueprint $table) => $table->dropConstrainedForeignId('assignment_id'));
    }
};
