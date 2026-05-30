<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->string('module', 32)->nullable()->after('name');
        });

        // 既存の部署にモジュールを設定
        DB::table('departments')->where('name', '情報出版')->update(['module' => 'publishing']);
        DB::table('departments')->where('name', '製版')->update(['module' => 'prepress']);
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn('module');
        });
    }
};
