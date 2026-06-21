<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE schedule_notifications MODIFY COLUMN type ENUM('morning_summary','pre_event_reminder','invitation_declined') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE schedule_notifications MODIFY COLUMN type ENUM('morning_summary','pre_event_reminder') NOT NULL");
    }
};
