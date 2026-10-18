<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 管理者メモ（総務だけが見られる。従業員には表示しない）／代理入力時の本人確認の記録（任意）
        Schema::table('suna_ga_nencho_submissions', function (Blueprint $table) {
            $table->text('admin_memo')->nullable()->after('returned_by_name');
            $table->json('proxy_confirmation')->nullable()->after('admin_memo');   // {paper: bool, person: bool, note: ?string, confirmed_by: string, confirmed_at: string}
        });
    }

    public function down(): void
    {
        Schema::table('suna_ga_nencho_submissions', function (Blueprint $table) {
            $table->dropColumn(['admin_memo', 'proxy_confirmation']);
        });
    }
};
