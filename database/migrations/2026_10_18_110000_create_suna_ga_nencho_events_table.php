<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 年末調整の操作履歴（提出・取り下げ・差戻しなど）。操作した人が削除されても氏名を残す
        Schema::create('suna_ga_nencho_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('suna_ga_nencho_submissions')->cascadeOnDelete();
            $table->string('action', 30);                              // submitted / withdrawn / returned
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name', 100);
            $table->text('comment')->nullable();
            $table->json('meta')->nullable();                          // 例: 差戻しで直してほしい工程 {steps: [1, 3]}
            $table->timestamp('created_at')->useCurrent();
            $table->index(['submission_id', 'created_at'], 'suna_ga_nencho_events_sub_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suna_ga_nencho_events');
    }
};
