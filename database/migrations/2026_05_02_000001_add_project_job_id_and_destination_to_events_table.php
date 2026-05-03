<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedBigInteger('project_job_id')->nullable()->after('user_id');
            $table->foreign('project_job_id')->references('id')->on('project_jobs')->nullOnDelete();
            $table->string('destination')->nullable()->after('project_job_id');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['project_job_id']);
            $table->dropColumn(['project_job_id', 'destination']);
        });
    }
};
