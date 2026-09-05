<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 会社別データ分離（2026-09-05）。file_sha256の一意制約が全社共通だと、別会社がたまたま
 * バイト単位で同一のExcelファイルを取り込んだ場合に誤って弾かれてしまう
 * （現実には起こりにくいが、会社ごとにデータを分離する設計方針と矛盾するため直す）。
 * (company_id, file_sha256) の複合一意へ変更する。
 */
return new class extends Migration
{
    protected $connection = 'sales';

    public function up(): void
    {
        DB::connection('sales')->statement('
            ALTER TABLE sales_imports
                DROP INDEX sales_imports_file_sha256_unique,
                ADD UNIQUE KEY sales_imports_company_file_sha256_unique (company_id, file_sha256)
        ');
    }

    public function down(): void
    {
        DB::connection('sales')->statement('
            ALTER TABLE sales_imports
                DROP INDEX sales_imports_company_file_sha256_unique,
                ADD UNIQUE KEY sales_imports_file_sha256_unique (file_sha256)
        ');
    }
};
