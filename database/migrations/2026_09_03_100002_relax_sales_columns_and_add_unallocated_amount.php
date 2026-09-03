<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Codexレビュー（SALES_ANALYSIS_EXCEL_VALIDATION_REVIEW.md 6.2 High-2）に基づく変更。
 * 実帳票では得意先名・品名・分類・項目・判型・明細金額等が空欄の行があり得るため、
 * ValidatorがNULL保存を許容する列に合わせてDB制約を緩和する。
 * また、M列合計とN列受注金額の差額（未配賦額）を隠さず保持するカラムを追加する。
 * doctrine/dbal未導入のため Blueprint::change() は使わず、MySQL専用のALTER TABLEで実施する。
 */
return new class extends Migration
{
    protected $connection = 'sales';

    public function up(): void
    {
        DB::connection('sales')->statement('
            ALTER TABLE sales_orders
                MODIFY client_name VARCHAR(255) NULL,
                MODIFY product_name VARCHAR(500) NULL,
                ADD COLUMN unallocated_amount DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER order_amount
        ');

        DB::connection('sales')->statement('
            ALTER TABLE sales_order_details
                MODIFY client_name VARCHAR(255) NULL,
                MODIFY product_name VARCHAR(500) NULL,
                MODIFY category VARCHAR(255) NULL,
                MODIFY item_name VARCHAR(255) NULL,
                MODIFY format_size VARCHAR(255) NULL,
                MODIFY color_count DECIMAL(10,2) NULL,
                MODIFY quantity DECIMAL(15,2) NULL,
                MODIFY unit_price DECIMAL(15,2) NULL,
                MODIFY line_amount DECIMAL(15,2) NULL
        ');
    }

    public function down(): void
    {
        DB::connection('sales')->statement('ALTER TABLE sales_orders DROP COLUMN unallocated_amount');

        // NOT NULLへ戻す方向はNULLデータが既に存在すると失敗するため、
        // ロールバックは列追加の取り消しのみに留める（nullable化は据え置き）。
    }
};
