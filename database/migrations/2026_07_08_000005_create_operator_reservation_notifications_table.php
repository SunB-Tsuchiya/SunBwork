<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operator_reservation_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operator_reservation_request_id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 30);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->foreign('operator_reservation_request_id', 'orn_request_id_foreign')
                ->references('id')->on('operator_reservation_requests')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operator_reservation_notifications');
    }
};
