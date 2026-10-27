<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 交通費: 明細の「同行者」と「経由地」。
 *  - 同行者: 行ごとに、同じ会社の社員を複数選べる（申請の突き合わせ用）。明細・社員の削除で連動して消える
 *  - 経由地: 路線が変わる駅を、出発と到着の間に順番どおり入れる（運賃台帳の区間ごとの照合用）
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('transport_expense_item_companions')) {
        Schema::create('transport_expense_item_companions', function (Blueprint $table) {
            $table->id();
            // 外部キー名は、MySQL の上限（64 文字）に収まるよう、短く指定する
            $table->unsignedBigInteger('transport_expense_item_id');
            $table->unsignedBigInteger('user_id');
            $table->foreign('transport_expense_item_id', 'te_item_comp_item_fk')->references('id')->on('transport_expense_items')->cascadeOnDelete();
            $table->foreign('user_id', 'te_item_comp_user_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['transport_expense_item_id', 'user_id'], 'te_item_companions_unique');
            $table->index('user_id');   // 「自分を同行者に選んだ行」の逆引き
        });
        }

        foreach (['transport_expense_items', 'transport_presets'] as $name) {
            if (!Schema::hasColumn($name, 'via_stations')) {
                Schema::table($name, fn (Blueprint $table) => $table->json('via_stations')->nullable()->after('station_to'));
            }
        }
    }

    public function down(): void
    {
        Schema::table('transport_presets', fn (Blueprint $t) => $t->dropColumn('via_stations'));
        Schema::table('transport_expense_items', fn (Blueprint $t) => $t->dropColumn('via_stations'));
        Schema::dropIfExists('transport_expense_item_companions');
    }
};
