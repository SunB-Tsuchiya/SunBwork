<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 取込の取り消し（SUNASALES_PLAN1.md §16）。取り消した取込は status='undone' にして、日時と取消者を記録する。
// 追加のみ（既存の列・データは変更しない）。
return new class extends Migration
{
    protected $connection = 'sales';

    public function up(): void
    {
        Schema::connection('sales')->table('suna_sl_imports', function (Blueprint $table) {
            $table->timestamp('undone_at')->nullable();
            $table->unsignedBigInteger('undone_by')->nullable(); // 通常DB user_id。クロスDB FKは張らない
        });
    }

    public function down(): void
    {
        Schema::connection('sales')->table('suna_sl_imports', function (Blueprint $table) {
            $table->dropColumn(['undone_at', 'undone_by']);
        });
    }
};
