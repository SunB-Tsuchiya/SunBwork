<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_job_assignments', function (Blueprint $table) {
            $table->json('file_info')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('project_job_assignments', function (Blueprint $table) {
            $table->dropColumn('file_info');
        });
    }
};
