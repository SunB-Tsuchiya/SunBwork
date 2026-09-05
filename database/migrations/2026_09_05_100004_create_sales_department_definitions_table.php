<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 会社別データ分離（2026-09-05）。従来SalesDepartments::LABELS/ENABLED_KEYSに
// ハードコードしていた部署区分（企画/制作/オンデマンド）はサン・ブレーン専用の制作ライン区分
// だったため、会社ごとに異なる区分を持てるようテーブル化する。投入はSalesDepartmentDefinitionSeeder。
return new class extends Migration
{
    protected $connection = 'sales';

    public function up(): void
    {
        Schema::connection('sales')->create('sales_department_definitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id'); // 通常DB companies.id。クロスDB FKは張らない
            $table->string('key', 32);
            $table->string('label', 64);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::connection('sales')->dropIfExists('sales_department_definitions');
    }
};
