<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 申請の種類を、システムの機能画面（例: 年末調整）に連携させるキー。NULL = 連携なし（枠の表示のみ）
        Schema::table('suna_ga_application_types', function (Blueprint $table) {
            $table->string('feature_key', 50)->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('suna_ga_application_types', function (Blueprint $table) {
            $table->dropColumn('feature_key');
        });
    }
};
