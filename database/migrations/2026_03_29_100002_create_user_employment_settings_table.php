<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_employment_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            // 日報義務: デフォルト true（正社員・契約社員）。
            // 派遣・業務委託はコードで false 扱いにするが、per-user 上書き可能。
            $table->boolean('diary_required')->default(true);
            // 将来拡張用（今はすべて true）
            $table->boolean('job_registration')->default(true);   // ジョブ登録権限
            $table->boolean('ranking_included')->default(true);   // ランキング対象
            $table->boolean('workload_tracked')->default(true);   // 作業量計測対象
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_employment_settings');
    }
};
