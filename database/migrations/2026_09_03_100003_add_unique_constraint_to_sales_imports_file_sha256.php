<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Codexレビュー2回目（SALES_ANALYSIS_REVIEW2.md 8.3）に基づく変更。
 * 同一トークンの同時確定を排他ロックで防いでいるが、アプリ側の重複チェック
 * （SalesImport::where('file_sha256', ...)->exists()）だけでは競合を完全に防げないため、
 * DB側にも一意制約を最終防御として設ける。
 * doctrine/dbal未導入のため Blueprint::change() は使わず、MySQL専用のALTER TABLEで実施する。
 */
return new class extends Migration
{
    protected $connection = 'sales';

    public function up(): void
    {
        DB::connection('sales')->statement('
            ALTER TABLE sales_imports
                DROP INDEX sales_imports_file_sha256_index,
                ADD UNIQUE KEY sales_imports_file_sha256_unique (file_sha256)
        ');
    }

    public function down(): void
    {
        DB::connection('sales')->statement('
            ALTER TABLE sales_imports
                DROP INDEX sales_imports_file_sha256_unique,
                ADD INDEX sales_imports_file_sha256_index (file_sha256)
        ');
    }
};
