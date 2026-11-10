<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 売上レポートの取込1回（ファイル1つ）の記録。取込ファイル自体は保存しない（確定/破棄で削除）。
return new class extends Migration
{
    protected $connection = 'sales';

    public function up(): void
    {
        Schema::connection('sales')->create('suna_sl_imports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('format', 40); // 取込形式（例: client_sales_v1）
            $table->string('original_filename', 255);
            $table->char('file_sha256', 64);
            $table->string('status', 20); // completed
            $table->unsignedBigInteger('imported_by'); // 通常DB user_id。クロスDB FKは張らない
            $table->timestamp('imported_at');
            $table->smallInteger('year_min')->nullable();
            $table->smallInteger('year_max')->nullable();
            $table->unsignedInteger('row_count')->default(0);       // ファイルの得意先×年の行数
            $table->unsignedInteger('added_count')->default(0);     // 追加した月の行数
            $table->unsignedInteger('changed_count')->default(0);   // 変更した月の行数
            $table->unsignedInteger('removed_count')->default(0);   // 削除した月の行数
            $table->unsignedInteger('unchanged_count')->default(0); // 変更なしの月の行数
            $table->json('replace_years')->nullable();              // 「この年を入れ替える」を指定した年
            $table->json('warnings')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'imported_at']);
            $table->index(['company_id', 'file_sha256']);
        });
    }

    public function down(): void
    {
        Schema::connection('sales')->dropIfExists('suna_sl_imports');
    }
};
