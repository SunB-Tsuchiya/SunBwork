<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('project_job_assignments', function (Blueprint $table) {
            // 数量: 整数で格納（例: 40）
            $table->integer('amounts')->nullable()->after('size_id');
            // 数量の単位: ページ or ファイル
            $table->string('amounts_unit')->nullable()->after('amounts');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_job_assignments', function (Blueprint $table) {
            $table->dropColumn(['amounts', 'amounts_unit']);
        });
    }
};
