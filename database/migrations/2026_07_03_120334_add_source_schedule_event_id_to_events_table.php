<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('source_schedule_event_id')->nullable()->after('room_reservation_id')
                ->constrained('events')->nullOnDelete();
            // 複製元イベントが削除され source_schedule_event_id が null化されても、
            // 「実績として複製されたイベントである」ことを判定できるよう独立したフラグを持つ
            $table->boolean('is_materialized_copy')->default(false)->after('source_schedule_event_id');
        });

        // unique制約は別の ALTER TABLE として発行する（FK追加と同一ステートメントに含めると
        // MySQL側で外部キー制約が実体化されないまま migrate が成功してしまう事象が確認されたため）
        Schema::table('events', function (Blueprint $table) {
            $table->unique(['user_id', 'source_schedule_event_id'], 'events_user_source_schedule_event_unique');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['source_schedule_event_id']);
        });
        Schema::table('events', function (Blueprint $table) {
            $table->dropUnique('events_user_source_schedule_event_unique');
        });
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['source_schedule_event_id', 'is_materialized_copy']);
        });
    }
};
