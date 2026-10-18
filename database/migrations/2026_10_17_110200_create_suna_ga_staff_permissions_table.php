<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 総務・経理（と管理者）の担当者ごとの権限。レコードなし＝すべてオフ（社員情報は許可しない限り見られない）
        Schema::create('suna_ga_staff_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('can_view_employees')->default(false);   // 社員情報（一般項目）を見る
            $table->boolean('can_edit_employees')->default(false);   // 社員情報を編集する
            $table->boolean('can_view_my_number')->default(false);   // マイナンバーを見る（入力・変更も含む）
            $table->boolean('can_manage_nencho')->default(false);    // 年末調整を管理する（受付・回収・確認・代理入力）
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suna_ga_staff_permissions');
    }
};
