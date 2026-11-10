<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// サンエー印刷 売上レポート（SunA/Sales。設計: z_instructions/SUNASALES_PLAN1.md）。
// 営業部門マスタ。部（第一部/第二部/第三部）と課（第三部のみ: 第一課/第二課/直轄）の2段。
// source_label は取込Excelの「担当部門」の表記（例: 営業第三部第一課）。未登録の表記は取込を止める。
return new class extends Migration
{
    protected $connection = 'sales';

    public function up(): void
    {
        Schema::connection('sales')->create('suna_sl_departments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('key', 40);
            $table->string('parent_key', 40)->nullable(); // null=部、値=課（親の部のキー）
            $table->string('label', 64);
            $table->string('source_label', 64)->nullable(); // Excelの「担当部門」の表記
            $table->boolean('is_direct')->default(false); // 部直轄（課に属さない）
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'key']);
            $table->index(['company_id', 'source_label']);
        });
    }

    public function down(): void
    {
        Schema::connection('sales')->dropIfExists('suna_sl_departments');
    }
};
