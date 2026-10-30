<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clerk_calendar_reminder_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reminder_id')->constrained('clerk_calendar_reminders')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unique(['reminder_id', 'user_id'], 'clerk_reminder_users_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clerk_calendar_reminder_users');
    }
};
