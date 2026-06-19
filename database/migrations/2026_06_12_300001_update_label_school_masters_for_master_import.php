<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 学校マスターシート.xlsx インポート対応
// - code の unique 制約を削除（合併予定校など同コードが複数存在するため）
// - print_name (教室名印刷) を追加
// - area_sort_order (エリア内並び順) を追加
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('label_school_masters', function (Blueprint $table) {
            // code の unique 制約を削除
            $table->dropUnique(['code']);
            // 新カラム追加
            $table->string('print_name', 150)->nullable()->after('display_name');
            $table->unsignedSmallInteger('area_sort_order')->default(0)->after('area');
        });
    }

    public function down(): void
    {
        Schema::table('label_school_masters', function (Blueprint $table) {
            $table->dropColumn(['print_name', 'area_sort_order']);
            $table->unique('code');
        });
    }
};
