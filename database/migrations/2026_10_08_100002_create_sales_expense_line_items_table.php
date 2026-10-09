<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Phase 21: 経費明細（科目×会社名(支払先)×月の金額）。sales_orders/sales_order_detailsへは
// 保存しない（売上とは別の集計軸のため、別テーブルとして独立させる）。
return new class extends Migration
{
    protected $connection = 'sales';

    public function up(): void
    {
        Schema::connection('sales')->create('sales_expense_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_expense_import_id')->constrained('sales_expense_imports')->onDelete('restrict');
            $table->string('department_key', 32); // planning / production / ondemand / shared
            $table->string('category', 100); // 科目（改行・空白除去済み）
            $table->string('vendor_name', 255)->nullable(); // 会社名（支払先）。空欄はnull
            $table->smallInteger('calendar_year');
            $table->tinyInteger('calendar_month');
            $table->decimal('amount', 15, 2);
            $table->boolean('is_shared_split')->default(false); // サンエー印刷との折半科目か
            $table->timestamps();

            $table->index(['sales_expense_import_id', 'department_key', 'category'], 'sales_expense_items_dept_category_idx');
            $table->index(['sales_expense_import_id', 'calendar_year', 'calendar_month'], 'sales_expense_items_period_idx');
        });
    }

    public function down(): void
    {
        Schema::connection('sales')->dropIfExists('sales_expense_line_items');
    }
};
