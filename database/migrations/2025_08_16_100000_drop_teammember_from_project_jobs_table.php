<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_jobs', function (Blueprint $table) {
            $table->dropColumn('teammember');
        });
    }

    public function down(): void
    {
        Schema::table('project_jobs', function (Blueprint $table) {
            $table->json('teammember')->nullable();
        });
    }
};
