<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 年末調整の案内（お知らせ）の送信履歴。実体は既存の announcements（お知らせ）で、ここには受付との紐づけと送信件数を残す
        Schema::create('suna_ga_nencho_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('suna_ga_nencho_campaigns')->cascadeOnDelete();
            $table->string('kind', 20);                                   // start（受付開始）/ reminder（未提出者への催促）
            $table->foreignId('announcement_id')->nullable()->constrained('announcements')->nullOnDelete();
            $table->string('title', 255);
            $table->unsignedInteger('recipient_count')->default(0);
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sent_by_name', 100);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['campaign_id', 'created_at'], 'suna_ga_nencho_notices_campaign_index');
        });

        // カレンダーのリマインダー（既存の clerk_calendar_reminders）との紐づけ
        Schema::table('suna_ga_nencho_campaigns', function (Blueprint $table) {
            $table->foreignId('calendar_reminder_id')->nullable()->after('created_by')->constrained('clerk_calendar_reminders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('suna_ga_nencho_campaigns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('calendar_reminder_id');
        });
        Schema::dropIfExists('suna_ga_nencho_notices');
    }
};
