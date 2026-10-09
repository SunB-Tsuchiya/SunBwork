<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Phase 21: 部署別経費データ（外注加工費等）の取込。売上Excelとは別軸のデータで、
// 1ファイル＝1会計年度（4月始まり）＝全社分のため、月単位のactive pointerテーブルは
// 持たない（(company_id, fiscal_year)内の最大versionが現在有効な版になる）。
return new class extends Migration
{
    protected $connection = 'sales';

    public function up(): void
    {
        Schema::connection('sales')->create('sales_expense_imports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->smallInteger('fiscal_year'); // 会計年度の開始暦年（例: 2025年4月〜2026年3月なら2025）
            $table->unsignedInteger('version');
            $table->string('original_filename', 255);
            $table->char('file_sha256', 64);
            $table->string('status', 20); // completed / failed
            $table->unsignedBigInteger('imported_by'); // 通常DB user_id。クロスDB FKは張らない
            $table->timestamp('imported_at');
            $table->unsignedInteger('category_count')->default(0);
            $table->unsignedInteger('line_item_count')->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->json('warnings')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'fiscal_year', 'version']);
            $table->unique(['company_id', 'file_sha256']);
            $table->index(['company_id', 'fiscal_year']);
        });
    }

    public function down(): void
    {
        Schema::connection('sales')->dropIfExists('sales_expense_imports');
    }
};
