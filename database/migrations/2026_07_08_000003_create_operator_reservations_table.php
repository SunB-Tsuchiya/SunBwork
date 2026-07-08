<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operator_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operator_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reserved_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('job_name', 255);
            $table->text('memo')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->timestamps();

            $table->index(['operator_user_id', 'starts_at', 'ends_at'], 'idx_operator_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operator_reservations');
    }
};
