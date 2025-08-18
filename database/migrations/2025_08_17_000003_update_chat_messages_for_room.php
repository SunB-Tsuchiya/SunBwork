<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('chat_room_id')->after('id')->nullable();
            $table->unsignedBigInteger('user_id')->after('chat_room_id')->nullable();
            // 既存のfrom_user_id, to_user_idは後で削除可能
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn(['chat_room_id', 'user_id']);
        });
    }
};
