<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 会社別データ分離（2026-09-05）。複数会社（サン・ブレーン・サンエー印刷）が同一システムを
// 使うにあたり、売上分析データを会社単位で分離する。クロスDB接続のためFKは張らない
// （既存のimported_by等と同じくunsignedBigIntegerのみ）。既存データの後方補完は
// 2026_09_05_100005_backfill_sales_company_id_for_sunbrain.phpで行う。
return new class extends Migration
{
    protected $connection = 'sales';

    public function up(): void
    {
        Schema::connection('sales')->table('sales_imports', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->after('department_key');
            $table->index(['company_id', 'department_key']);
        });
    }

    public function down(): void
    {
        Schema::connection('sales')->table('sales_imports', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'department_key']);
            $table->dropColumn('company_id');
        });
    }
};
