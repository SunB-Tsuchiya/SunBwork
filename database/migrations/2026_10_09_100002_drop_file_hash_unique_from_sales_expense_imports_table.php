<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Phase 21追加: 取込ロジック更新後に同じExcelファイルを再取込みして（既存データの列を
// 補完する等）新しい版として登録し直したい、という正当な要望が発生した。DB側のunique制約
// (company_id, file_sha256)が残っているとアプリ側のforceフラグで同一内容チェックを
// 回避してもINSERT自体が失敗するため、unique制約を外し検索用の通常indexに置き換える
// （誤って二重アップロードする事故への防止は、アプリ側の`SalesExpenseImportService::confirm()`
// の同一内容チェック＋確認付きの再取込みボタンで引き続き担保する）。
return new class extends Migration
{
    protected $connection = 'sales';

    public function up(): void
    {
        Schema::connection('sales')->table('sales_expense_imports', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'file_sha256']);
            $table->index(['company_id', 'file_sha256']);
        });
    }

    public function down(): void
    {
        Schema::connection('sales')->table('sales_expense_imports', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'file_sha256']);
            $table->unique(['company_id', 'file_sha256']);
        });
    }
};
