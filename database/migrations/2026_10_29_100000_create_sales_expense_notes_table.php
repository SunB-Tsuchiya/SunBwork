<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Phase24-5: 経費メモ。科目・支払先・月にコメント（例:「◯月は臨時案件のため増」）を残し、翌年度も見られる。
// 取込の版（sales_expense_imports）とは切り離し、年度・部署・科目・支払先の「名前」で紐づける（再取込でも消えない）。
// category/vendor_name/calendar_month が空なら、それぞれ部署全体／科目全体／年間へのメモ。名前は名寄せ後の正式名で保存し、読み出し時にも名寄せを通す。
return new class extends Migration
{
    protected $connection = 'sales';

    public function up(): void
    {
        Schema::connection('sales')->create('sales_expense_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->smallInteger('fiscal_year'); // 会計年度の開始暦年（例: 2025年4月〜2026年3月なら2025）
            $table->string('department_key', 30);
            $table->string('category', 255)->nullable();
            $table->string('vendor_name', 255)->nullable();
            $table->unsignedTinyInteger('calendar_month')->nullable(); // 1〜12（暦月。年度から暦年は決まる）
            $table->text('body');
            $table->unsignedBigInteger('created_by'); // 通常DB user_id。クロスDB FKは張らない
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'fiscal_year', 'department_key'], 'sales_expense_notes_scope_idx');
            $table->index(['company_id', 'vendor_name'], 'sales_expense_notes_vendor_idx');
        });
    }

    public function down(): void
    {
        Schema::connection('sales')->dropIfExists('sales_expense_notes');
    }
};
