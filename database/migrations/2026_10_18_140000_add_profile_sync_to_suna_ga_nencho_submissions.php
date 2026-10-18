<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 社員情報との照合の結果（総務が、どちらが正しいか選んで対応した記録）。内容が変わると再び「差異あり」に戻る（signature で判定）
        Schema::table('suna_ga_nencho_submissions', function (Blueprint $table) {
            $table->json('profile_sync')->nullable()->after('proxy_confirmation');   // {resolved_at, resolved_by, decisions: {group: update|keep}, signature}
        });
    }

    public function down(): void
    {
        Schema::table('suna_ga_nencho_submissions', function (Blueprint $table) {
            $table->dropColumn('profile_sync');
        });
    }
};
