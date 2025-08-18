<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chat_room_user', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('chat_room_id');
            $table->unsignedBigInteger('user_id');
            $table->string('role', 50)->nullable();
            $table->timestamps();

            $table->foreign('chat_room_id')->references('id')->on('chat_rooms')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['chat_room_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_room_user');
    }
};
