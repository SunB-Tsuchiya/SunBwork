<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 取込ごとの変更ログ（変更のあった行だけ）。取込履歴の「何が変わったか」の表示に使う。
return new class extends Migration
{
    protected $connection = 'sales';

    public function up(): void
    {
        Schema::connection('sales')->create('suna_sl_import_changes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('import_id');
            $table->string('client_key', 255);
            $table->string('client_name', 255);
            $table->smallInteger('sales_year');
            $table->tinyInteger('sales_month')->unsigned()->nullable(); // null=担当部門の変更（年ごとの項目）
            $table->string('kind', 12);  // added / changed / removed
            $table->string('field', 20); // amount / order_count / department
            $table->string('old_value', 120)->nullable();
            $table->string('new_value', 120)->nullable();

            $table->index(['company_id', 'import_id'], 'suna_sl_changes_import_idx');
        });
    }

    public function down(): void
    {
        Schema::connection('sales')->dropIfExists('suna_sl_import_changes');
    }
};
