<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 運賃マスタ（総務・経理が管理する、経路と運賃の台帳。会社ごと）と、明細の行ごとの確認チェック。
     * 将来の駅すぱあと API は source で区別し、検査済みの自動登録に使う。
     */
    public function up(): void
    {
        Schema::create('transport_fares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('route_key', 220);                 // 駅名を正規化し、方向を問わず並べた比較用キー
            $table->string('station_a', 100);
            $table->string('station_b', 100);
            $table->unsignedInteger('fare_ic')->nullable();       // 片道・IC
            $table->unsignedInteger('fare_ticket')->nullable();   // 片道・切符
            $table->string('status', 12)->default('unverified');  // verified 検査済み / unverified 未検査 / wanted 登録したい
            $table->unsignedInteger('reported_fare')->nullable(); // 申請で使われた片道の金額（参考値。未検査の確認用）
            $table->string('reported_fare_type', 10)->nullable(); // ic / ticket
            $table->string('note', 255)->nullable();              // 乗換・路線などのメモ
            $table->unsignedInteger('use_count')->default(0);     // 申請で使われた回数（目安）
            $table->timestamp('last_used_at')->nullable();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable();
            $table->string('source', 12)->default('manual');      // manual / stock（申請から自動）/ ekispert（将来）
            $table->timestamps();

            $table->unique(['company_id', 'route_key']);
            $table->index(['company_id', 'status']);
        });

        Schema::table('transport_expense_items', function (Blueprint $table) {
            $table->timestamp('checked_at')->nullable();                                   // 総務・経理が確認済みにした日時
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transport_expense_items', function (Blueprint $table) {
            $table->dropForeign(['checked_by']);
            $table->dropColumn(['checked_at', 'checked_by']);
        });
        Schema::dropIfExists('transport_fares');
    }
};
