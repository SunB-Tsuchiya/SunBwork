<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_definitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by')->comment('作成者 user_id');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('recurrence', ['weekly', 'biweekly', 'monthly'])->comment('毎週/隔週/毎月');
            $table->unsignedTinyInteger('day_of_week')->comment('0=日〜6=土');
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_definitions');
    }
};
