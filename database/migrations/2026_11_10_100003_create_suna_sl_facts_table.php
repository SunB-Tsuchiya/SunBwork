<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 売上の事実。1行 = 得意先 × 年（暦年。サンエー印刷は1月〜12月が1期）× 月。登録済みの月だけ行がある
// （行が無い月は「未登録」。0円は amount=0 の行）。担当部門は年（行）ごと。
// 将来の次元（商品分類・担当者 等）は nullable 列の追加、または同形の別テーブルで足す。
return new class extends Migration
{
    protected $connection = 'sales';

    public function up(): void
    {
        Schema::connection('sales')->create('suna_sl_facts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('client_key', 255);  // 正規化した得意先名（同一性の判定に使う）
            $table->string('client_name', 255); // 表示用の得意先名
            $table->smallInteger('sales_year');
            $table->tinyInteger('sales_month')->unsigned();
            $table->string('department_key', 40);     // 部
            $table->string('section_key', 40)->nullable(); // 課（第三部のみ。直轄を含む）
            $table->decimal('amount', 15, 2)->default(0);
            $table->unsignedInteger('order_count')->default(0);
            $table->unsignedBigInteger('import_id'); // 最後にこの行を書いた取込
            $table->timestamps();

            $table->unique(['company_id', 'client_key', 'sales_year', 'sales_month'], 'suna_sl_facts_unique');
            $table->index(['company_id', 'sales_year', 'sales_month'], 'suna_sl_facts_period_idx');
            $table->index(['company_id', 'department_key', 'section_key'], 'suna_sl_facts_dept_idx');
        });
    }

    public function down(): void
    {
        Schema::connection('sales')->dropIfExists('suna_sl_facts');
    }
};
