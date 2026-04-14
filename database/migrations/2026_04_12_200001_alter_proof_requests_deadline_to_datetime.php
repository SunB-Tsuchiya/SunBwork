<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') return;
        DB::statement('ALTER TABLE proof_requests MODIFY COLUMN deadline DATETIME NULL');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') return;
        DB::statement('ALTER TABLE proof_requests MODIFY COLUMN deadline DATE NULL');
    }
};
