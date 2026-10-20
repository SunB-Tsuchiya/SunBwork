<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 交通費の「よく使う行き先」（ユーザーごとの登録）。行先・用件・区間・IC/切符・金額（片道と×2）をひとまとめに登録する */
    public function up(): void
    {
        Schema::create('transport_presets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('destination', 100);
            $table->string('purpose', 20);                  // TransportExpenseItem::PURPOSE_LABELS のキー
            $table->string('purpose_text', 100)->nullable();
            $table->string('station_from', 100)->nullable();
            $table->string('station_to', 100)->nullable();
            $table->string('fare_type', 10)->default('ic');
            $table->unsignedInteger('base_amount')->default(0);   // 片道（×2 のとき）または合計
            $table->boolean('auto_double')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'destination']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_presets');
    }
};
