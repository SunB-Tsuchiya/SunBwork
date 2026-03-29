<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // dispatch_profiles に is_active カラムを追加
        // true（デフォルト）= 在籍中、false = 非在籍（手動オフ）
        // 表示ロジック: is_active=false → 非在籍 / contract_end 超過 → 契約終了 / それ以外 → 在籍中
        if (! Schema::hasColumn('dispatch_profiles', 'is_active')) {
            Schema::table('dispatch_profiles', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->after('is_hidden')->comment('在籍中フラグ（manualオフ可能）');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('dispatch_profiles', 'is_active')) {
            Schema::table('dispatch_profiles', function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }
    }
};
