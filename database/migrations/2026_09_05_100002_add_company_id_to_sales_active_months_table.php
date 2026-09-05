<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// 会社別データ分離（2026-09-05）。department_key単独の一意制約のままだと、将来別会社が
// 同じdepartment_key（例: 'general'）を使った場合に衝突するため、company_idを一意制約に含める。
// 元の複合一意（department_key, sales_year, sales_month）はテーブル名込みでMySQLの識別子上限
// 64文字を超えていたため、環境によっては実際には作成されていない可能性がある。存在チェックを
// してから安全にdrop/createする。
return new class extends Migration
{
    protected $connection = 'sales';

    private const NEW_UNIQUE = 'sales_active_months_company_dept_ym_unique';

    private const OLD_UNIQUE = 'sales_active_months_department_key_sales_year_sales_month_unique';

    public function up(): void
    {
        Schema::connection('sales')->table('sales_active_months', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->after('department_key');
        });

        if ($this->indexExists(self::OLD_UNIQUE)) {
            Schema::connection('sales')->table('sales_active_months', function (Blueprint $table) {
                $table->dropUnique(self::OLD_UNIQUE);
            });
        }

        Schema::connection('sales')->table('sales_active_months', function (Blueprint $table) {
            $table->unique(['company_id', 'department_key', 'sales_year', 'sales_month'], self::NEW_UNIQUE);
        });
    }

    public function down(): void
    {
        Schema::connection('sales')->table('sales_active_months', function (Blueprint $table) {
            $table->dropUnique(self::NEW_UNIQUE);
            $table->dropColumn('company_id');
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
