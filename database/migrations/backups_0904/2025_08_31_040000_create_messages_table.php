<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('from_user_id')->nullable();
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->string('status')->default('draft'); // draft, sent
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index('from_user_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('messages');
    }
};
