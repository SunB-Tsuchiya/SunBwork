<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chat_rooms', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
            $table->string('type', 50)->default('private'); // private or group
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_rooms');
    }
};
