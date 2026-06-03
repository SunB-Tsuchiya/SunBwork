<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_meeting_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_meeting_minute_id')->constrained('team_meeting_minutes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['team_meeting_minute_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_meeting_attendees');
    }
};
