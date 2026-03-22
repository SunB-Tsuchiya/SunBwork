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
        Schema::table('worktypes', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->after('id');
            $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
        });

        // company_id のバックフィルは WorktypeSeeder で行う（migrate 実行時は companies テーブルが空のため）
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('worktypes', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });
    }
};
