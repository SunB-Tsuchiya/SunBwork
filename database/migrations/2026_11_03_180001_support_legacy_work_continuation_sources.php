<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_job_continuations', function (Blueprint $table) {
            $table->unsignedBigInteger('source_entry_id')->nullable()->change();
            $table->unsignedBigInteger('source_assignment_id')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('work_job_continuations')->whereNull('source_entry_id')->exists()) {
            throw new RuntimeException('旧形式を元にした受付があるため、データを保持して停止します。');
        }
        Schema::table('work_job_continuations', function (Blueprint $table) {
            $table->dropColumn('source_assignment_id');
            $table->unsignedBigInteger('source_entry_id')->nullable(false)->change();
        });
    }
};
