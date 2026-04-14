<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') return;
        Schema::table('project_job_assignments', function (Blueprint $table) {
            $table->string('job_type', 20)->nullable()->after('supersedes_assignment_id');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') return;
        Schema::table('project_job_assignments', function (Blueprint $table) {
            $table->dropColumn('job_type');
        });
    }
};
