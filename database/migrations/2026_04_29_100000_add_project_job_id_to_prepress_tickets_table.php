<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prepress_tickets', function (Blueprint $table) {
            $table->foreignId('project_job_id')
                  ->nullable()
                  ->after('user_id')
                  ->constrained('project_jobs')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('prepress_tickets', function (Blueprint $table) {
            $table->dropForeign(['project_job_id']);
            $table->dropColumn('project_job_id');
        });
    }
};
