<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Phase 20: サン・ブレーンの受注経路分離（2026-09-06）。standard/direct を同じ会社・部署・
// 年月で同時にactiveにできるよう、一意制約へorder_channelを加える。デフォルト'standard'
// 指定により既存行は追加と同時に後方補完される。
return new class extends Migration
{
    protected $connection = 'sales';

    private const OLD_UNIQUE = 'sales_active_months_company_dept_ym_unique';

    private const NEW_UNIQUE = 'sales_active_months_company_dept_channel_ym_unique';

    public function up(): void
    {
        Schema::connection('sales')->table('sales_active_months', function (Blueprint $table) {
            $table->string('order_channel', 16)->default('standard')->after('department_key');
        });

        if ($this->indexExists(self::OLD_UNIQUE)) {
            Schema::connection('sales')->table('sales_active_months', function (Blueprint $table) {
                $table->dropUnique(self::OLD_UNIQUE);
            });
        }

        Schema::connection('sales')->table('sales_active_months', function (Blueprint $table) {
            $table->unique(['company_id', 'department_key', 'order_channel', 'sales_year', 'sales_month'], self::NEW_UNIQUE);
        });
    }

    public function down(): void
    {
        // directデータを持つ行があると、company_id+department_key+sales_year+sales_monthが
        // 重複するため旧一意制約へ戻せない。行を勝手に削除せず明示的に停止する（PLAN Phase20 20.4）。
        $hasDirectRows = DB::connection('sales')->table('sales_active_months')
            ->where('order_channel', 'direct')
            ->exists();

        if ($hasDirectRows) {
            throw new \RuntimeException(
                'sales_active_months に order_channel=direct の行が存在するため、このmigrationをロールバックできません。'
                . 'direct行を削除しないと安全に旧一意制約へ戻せません。'
            );
        }

        Schema::connection('sales')->table('sales_active_months', function (Blueprint $table) {
            $table->dropUnique(self::NEW_UNIQUE);
        });

        Schema::connection('sales')->table('sales_active_months', function (Blueprint $table) {
            $table->unique(['company_id', 'department_key', 'sales_year', 'sales_month'], self::OLD_UNIQUE);
        });

        Schema::connection('sales')->table('sales_active_months', function (Blueprint $table) {
            $table->dropColumn('order_channel');
        });
    }

    private function indexExists(string $indexName): bool
    {
        $rows = DB::connection('sales')->select(
            'SHOW INDEX FROM sales_active_months WHERE Key_name = ?',
            [$indexName]
        );

        return count($rows) > 0;
    }
};
