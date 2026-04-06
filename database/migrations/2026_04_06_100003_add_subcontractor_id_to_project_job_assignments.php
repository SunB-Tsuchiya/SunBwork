<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_job_assignments', function (Blueprint $table) {
            $table->unsignedBigInteger('subcontractor_id')->nullable()->after('user_id');

            if (DB::getDriverName() !== 'sqlite') {
                $table->foreign('subcontractor_id')->references('id')->on('subcontractors')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('project_job_assignments', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign(['subcontractor_id']);
            }
            $table->dropColumn('subcontractor_id');
        });
    }
};
