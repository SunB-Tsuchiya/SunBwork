<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        // MySQL: alter column type
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `attachments` MODIFY `status` VARCHAR(32) NULL DEFAULT NULL;");
            return;
        }

        // SQLite or others: attempt a safe schema change (drop & recreate column is non-trivial)
        // For test environments using sqlite we leave as-is; caller can run migrations under MySQL.
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `attachments` MODIFY `status` INT DEFAULT 0;");
        }
    }
};
