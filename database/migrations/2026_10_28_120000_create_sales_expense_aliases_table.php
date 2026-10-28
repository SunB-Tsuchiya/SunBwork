<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Phase24: 経費の名寄せ。経費Excelは年度ごとに科目名の表記が変わる（例: 外注加工→外注加工費、
// 消耗品→消耗品費）ため、完全一致の比較では前年が「新規」扱いになる。別名（alias_name）を
// 正式名（canonical_name）へ読み替える対応表を持ち、取込済みの元データは書き換えず表示時にだけ通す。
// kind='category'は科目名（部署ごと）、kind='vendor'は支払先名（部署をまたぐのでdepartment_key='all'。24-3で利用）。
return new class extends Migration
{
    protected $connection = 'sales';

    public function up(): void
    {
        Schema::connection('sales')->create('sales_expense_aliases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('kind', 20); // category / vendor
            $table->string('department_key', 30)->default('all');
            $table->string('alias_name', 255);
            $table->string('canonical_name', 255);
            $table->unsignedBigInteger('created_by')->nullable(); // 通常DB user_id。クロスDB FKは張らない
            $table->timestamps();

            $table->unique(['company_id', 'kind', 'department_key', 'alias_name'], 'sales_expense_aliases_unique');
            $table->index(['company_id', 'kind', 'department_key', 'canonical_name'], 'sales_expense_aliases_canonical_idx');
        });
    }

    public function down(): void
    {
        Schema::connection('sales')->dropIfExists('sales_expense_aliases');
    }
};
