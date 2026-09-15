<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Phase 20: サン・ブレーンの受注経路分離（2026-09-06）。サンエー印刷経由(standard)と
// 独自受注(direct)を区別するための列を追加する。デフォルト'standard'を指定することで
// 既存行は追加と同時に後方補完される（MySQLのALTER TABLE ADD COLUMN ... DEFAULTの挙動）。
// sales_orders/sales_order_detailsへは重複保存しない（1ファイル1経路のため、
// sales_imports/sales_active_monthsから辿れば十分）。
return new class extends Migration
{
    protected $connection = 'sales';

    private const OLD_INDEX = 'sales_imports_dept_period_version_idx';

    private const NEW_INDEX = 'sales_imports_company_dept_channel_period_version_idx';

    public function up(): void
    {
        Schema::connection('sales')->table('sales_imports', function (Blueprint $table) {
            $table->string('order_channel', 16)->default('standard')->after('department_key');
        });

        Schema::connection('sales')->table('sales_imports', function (Blueprint $table) {
            $table->dropIndex(self::OLD_INDEX);
        });

        Schema::connection('sales')->table('sales_imports', function (Blueprint $table) {
            $table->index(
                ['company_id', 'department_key', 'order_channel', 'source_year', 'source_month', 'version'],
                self::NEW_INDEX
            );
        });
    }

    public function down(): void
    {
        // directデータを持つ行があるmigrationのロールバックはデータ消失につながるため、
        // 行を勝手に削除せず明示的に停止する（PLAN Phase20 20.4）。
        $hasDirectRows = DB::connection('sales')->table('sales_imports')
            ->where('order_channel', 'direct')
            ->exists();

        if ($hasDirectRows) {
            throw new \RuntimeException(
                'sales_imports に order_channel=direct の行が存在するため、このmigrationをロールバックできません。'
                . 'direct行を削除しないと安全に旧スキーマへ戻せません。'
            );
        }

        Schema::connection('sales')->table('sales_imports', function (Blueprint $table) {
            $table->dropIndex(self::NEW_INDEX);
        });

        Schema::connection('sales')->table('sales_imports', function (Blueprint $table) {
            $table->index(
                ['department_key', 'source_year', 'source_month', 'version'],
                self::OLD_INDEX
            );
        });

        Schema::connection('sales')->table('sales_imports', function (Blueprint $table) {
            $table->dropColumn('order_channel');
        });
    }
};
