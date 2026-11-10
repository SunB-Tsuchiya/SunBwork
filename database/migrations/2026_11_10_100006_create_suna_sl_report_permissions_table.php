<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// サンエー印刷 売上レポートの利用許可（通常DB。portfolio_permissions と同形）。
// enabled = 閲覧できる、can_import = 取込もできる。管理者が「利用メンバー設定」画面で個別に設定する（既定は全員OFF）。
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suna_sl_report_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->boolean('enabled')->default(false);
            $table->boolean('can_import')->default(false);
            $table->unsignedBigInteger('granted_by')->nullable();
            $table->timestamp('granted_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suna_sl_report_permissions');
    }
};
