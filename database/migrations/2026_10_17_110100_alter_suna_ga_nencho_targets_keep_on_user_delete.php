<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 対象者: ユーザー削除でも消えないよう user_id を nullable＋nullOnDelete に。氏名のスナップショットを持つ
        Schema::table('suna_ga_nencho_targets', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
        Schema::table('suna_ga_nencho_targets', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('user_name', 100)->nullable()->after('user_id');
        });
        DB::statement('UPDATE suna_ga_nencho_targets t JOIN users u ON u.id = t.user_id SET t.user_name = u.name WHERE t.user_name IS NULL');
    }

    public function down(): void
    {
        Schema::table('suna_ga_nencho_targets', function (Blueprint $table) {
            $table->dropColumn('user_name');
        });
    }
};
