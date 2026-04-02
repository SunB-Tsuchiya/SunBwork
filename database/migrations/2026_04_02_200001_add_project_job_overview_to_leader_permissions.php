<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') return;

        Schema::table('leader_permissions', function (Blueprint $table) {
            $table->boolean('project_job_overview')->default(true)->after('user_management');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') return;

        Schema::table('leader_permissions', function (Blueprint $table) {
            $table->dropColumn('project_job_overview');
        });
    }
};
