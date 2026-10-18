<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 同時編集の防止（編集ロック）。画面を開いている間は定期更新し、一定時間更新がなければ自動で失効する
        Schema::table('suna_ga_nencho_submissions', function (Blueprint $table) {
            $table->foreignId('edit_lock_user_id')->nullable()->after('proxy_confirmation')->constrained('users')->nullOnDelete();
            $table->string('edit_lock_name', 100)->nullable()->after('edit_lock_user_id');
            $table->string('edit_lock_mode', 10)->nullable()->after('edit_lock_name');     // self（本人）/ proxy（総務の代理）
            $table->string('edit_lock_token', 64)->nullable()->after('edit_lock_mode');    // 画面（タブ）ごとの識別子
            $table->timestamp('edit_locked_at')->nullable()->after('edit_lock_token');     // 最終の更新（ハートビート）
        });
    }

    public function down(): void
    {
        Schema::table('suna_ga_nencho_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('edit_lock_user_id');
            $table->dropColumn(['edit_lock_name', 'edit_lock_mode', 'edit_lock_token', 'edit_locked_at']);
        });
    }
};
