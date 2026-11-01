<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_jobs', function (Blueprint $table) {
            $table->foreignId('broad_project_id')->nullable()->constrained('broad_projects')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('project_jobs', function (Blueprint $table) {
            $table->dropForeign(['broad_project_id']);
            $table->dropColumn('broad_project_id');
        });
    }
};
