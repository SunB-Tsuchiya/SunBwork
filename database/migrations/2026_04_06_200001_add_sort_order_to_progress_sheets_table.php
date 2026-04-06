<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('progress_sheets', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('name');
        });

        // 既存レコードに連番を設定（project_job_id グループ内で id 順）
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('
                UPDATE progress_sheets ps1
                JOIN (
                    SELECT id,
                           ROW_NUMBER() OVER (PARTITION BY project_job_id ORDER BY id) - 1 AS rn
                    FROM progress_sheets
                ) ps2 ON ps1.id = ps2.id
                SET ps1.sort_order = ps2.rn
            ');
        }
    }

    public function down(): void
    {
        Schema::table('progress_sheets', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
