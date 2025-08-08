<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 先にenum値を拡張（leader追加）
        DB::statement("ALTER TABLE users MODIFY COLUMN user_role ENUM('admin', 'owner', 'leader', 'user') NOT NULL DEFAULT 'user'");

        // 既存の'owner'データを'leader'に更新
        DB::table('users')->where('user_role', 'owner')->update(['user_role' => 'leader']);

        // ownerを除去したenum値に更新
        DB::statement("ALTER TABLE users MODIFY COLUMN user_role ENUM('admin', 'leader', 'user') NOT NULL DEFAULT 'user'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 'leader'データを'owner'に戻す
        DB::table('users')->where('user_role', 'leader')->update(['user_role' => 'owner']);

        // enum値を元に戻す
        DB::statement("ALTER TABLE users MODIFY COLUMN user_role ENUM('admin', 'owner', 'user') NOT NULL DEFAULT 'user'");
    }
};
