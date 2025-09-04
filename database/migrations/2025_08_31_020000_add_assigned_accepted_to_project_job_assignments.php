<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_job_assignments', function (Blueprint $table) {
            $table->boolean('assigned')->default(false)->change();
            $table->boolean('accepted')->default(false)->change();
        });
    }

    public function down(): void
    {
        // revert not implemented
    }
};
