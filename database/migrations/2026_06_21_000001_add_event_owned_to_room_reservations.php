<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_reservations', function (Blueprint $table) {
            // 予約作成時に同時生成したイベントか (true) / 既存イベントをリンクしたか (false)
            $table->boolean('event_owned')->default(false)->after('event_id');
        });
    }

    public function down(): void
    {
        Schema::table('room_reservations', function (Blueprint $table) {
            $table->dropColumn('event_owned');
        });
    }
};
