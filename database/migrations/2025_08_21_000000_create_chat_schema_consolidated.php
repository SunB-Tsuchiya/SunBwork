<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // No-op consolidated migration.
        // This repository now creates chat tables via dedicated migrations:
        // - 2025_10_05_000001_create_chat_rooms_table.php
        // - 2025_10_05_000003_create_chat_messages_table.php
        // - 2025_10_05_000004_create_chat_message_reads_table.php
        // The consolidated file is kept for history but should not create tables to avoid duplication.
    }

    public function down(): void
    {
        // no-op
    }
};
