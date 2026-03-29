<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // dispatch_profiles に is_hidden カラムを追加
        // 派遣社員がスポット勤務などで一時的に一覧から非表示にするためのフラグ
        if (!Schema::hasColumn('dispatch_profiles', 'is_hidden')) {
            Schema::table('dispatch_profiles', function (Blueprint $table) {
                $table->boolean('is_hidden')->default(false)->after('notes')->comment('一覧から非表示にするフラグ');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('dispatch_profiles', 'is_hidden')) {
            Schema::table('dispatch_profiles', function (Blueprint $table) {
                $table->dropColumn('is_hidden');
            });
        }
    }
};
