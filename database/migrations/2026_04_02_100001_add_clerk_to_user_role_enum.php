<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE users MODIFY COLUMN user_role ENUM('superadmin','admin','leader','coordinator','clerk','user') NOT NULL DEFAULT 'user'");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // clerkユーザーをuserにダウングレードしてからENUMを戻す
        DB::statement("UPDATE users SET user_role = 'user' WHERE user_role = 'clerk'");
        DB::statement("ALTER TABLE users MODIFY COLUMN user_role ENUM('superadmin','admin','leader','coordinator','user') NOT NULL DEFAULT 'user'");
    }
};
