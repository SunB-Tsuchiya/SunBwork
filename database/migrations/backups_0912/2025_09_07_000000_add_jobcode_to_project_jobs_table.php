<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('project_jobs')) {
            return;
        }

        Schema::table('project_jobs', function (Blueprint $table) {
            if (! Schema::hasColumn('project_jobs', 'jobcode')) {
                // add as nullable to avoid breaking existing records; can be tightened later
                $table->string('jobcode')->nullable()->after('id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('project_jobs')) {
            return;
        }

        Schema::table('project_jobs', function (Blueprint $table) {
            if (Schema::hasColumn('project_jobs', 'jobcode')) {
                $table->dropColumn('jobcode');
            }
        });
    }
};
