<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leader_permissions', function (Blueprint $table) {
            $table->boolean('script_access')->default(false)->after('project_job_overview');
        });
    }

    public function down(): void
    {
        Schema::table('leader_permissions', function (Blueprint $table) {
            $table->dropColumn('script_access');
        });
    }
};
