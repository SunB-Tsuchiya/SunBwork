<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_job_assignments', function (Blueprint $table) {
            $table->timestamp('desired_start')->nullable()->change();
            $table->timestamp('desired_end')->nullable()->change();
        });
    }

    public function down(): void
    {
        // revert not implemented
    }
};
