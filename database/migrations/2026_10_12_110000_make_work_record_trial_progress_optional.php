<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_record_trials', function (Blueprint $table) {
            $table->string('progress_state', 30)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('work_record_trials')->whereNull('progress_state')->exists()) {
            throw new RuntimeException('進みが未入力の試用記録があるため、列を必須に戻せません。');
        }

        Schema::table('work_record_trials', function (Blueprint $table) {
            $table->string('progress_state', 30)->nullable(false)->change();
        });
    }
};
