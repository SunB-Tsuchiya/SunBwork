<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 社員情報: マイナンバー（暗号化）・最寄り駅を追加。ユーザー削除でも消えないよう user_id を nullable＋nullOnDelete に変更（保存義務 7 年）
        Schema::table('suna_ga_employee_profiles', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
        Schema::table('suna_ga_employee_profiles', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->text('my_number')->nullable()->after('employee_number');      // encrypted キャストで暗号化して保存
            $table->string('nearest_station', 50)->nullable()->after('street');
        });
    }

    public function down(): void
    {
        Schema::table('suna_ga_employee_profiles', function (Blueprint $table) {
            $table->dropColumn(['my_number', 'nearest_station']);
        });
    }
};
