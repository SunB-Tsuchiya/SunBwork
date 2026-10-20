<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 往復の「片道の金額 ×2」を、入力した状態のまま復元できるように保存する。
     * amount（合計）は従来どおり用紙・集計に使う。既存行は NULL / false のまま（見え方は変わらない）。
     */
    public function up(): void
    {
        Schema::table('transport_expense_items', function (Blueprint $table) {
            $table->unsignedInteger('base_amount')->nullable()->after('amount');
            $table->boolean('auto_double')->default(false)->after('base_amount');
        });
    }

    public function down(): void
    {
        Schema::table('transport_expense_items', function (Blueprint $table) {
            $table->dropColumn(['base_amount', 'auto_double']);
        });
    }
};
