<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') return;

        Schema::create('proof_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proof_request_id')->constrained('proof_requests')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->timestamps();

            $table->index(['proof_request_id']);
            $table->index(['user_id']);
            $table->index(['starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') return;
        Schema::dropIfExists('proof_schedules');
    }
};
