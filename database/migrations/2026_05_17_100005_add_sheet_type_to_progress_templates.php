<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('progress_templates', function (Blueprint $table) {
            // 'progress' / 'management' / NULL（両方対応）
            $table->string('sheet_type', 32)->nullable()->default(null)->after('is_shared');
        });
    }

    public function down(): void
    {
        Schema::table('progress_templates', function (Blueprint $table) {
            $table->dropColumn('sheet_type');
        });
    }
};
