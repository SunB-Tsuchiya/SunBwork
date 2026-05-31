<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('active');
        });

        // サンエー印刷（親会社）を1番目、サン・ブレーンを2番目に設定
        DB::table('companies')->where('id', 3)->update(['sort_order' => 1]); // サンエー印刷
        DB::table('companies')->where('id', 2)->update(['sort_order' => 2]); // サン・ブレーン
        DB::table('companies')->where('id', 7)->update(['sort_order' => 3]); // フォトパブリッシング
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
