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
            $table->unsignedBigInteger('proof_dispatcher_id')->nullable()->after('subcontractor_id');

            if (DB::getDriverName() !== 'sqlite') {
                $table->foreign('proof_dispatcher_id')->references('id')->on('proof_dispatchers')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('project_job_assignments', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign(['proof_dispatcher_id']);
            }
            $table->dropColumn('proof_dispatcher_id');
        });
    }
};
