<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            // explicit diary date (one diary per user per day)
            $table->date('date')->index();
            $table->text('content');
            // track readers by user id array stored as JSON
            $table->json('read_by')->nullable()->comment('JSON array of user ids who have read this diary');
            $table->timestamps();

            $table->unique(['user_id', 'date']);
        });

        // create diary_comments table alongside diaries for migrate:fresh convenience
        Schema::create('diary_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diary_id')->constrained('diaries')->onDelete('cascade');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_name')->nullable();
            $table->text('comment');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // drop diary comments first if present
        if (Schema::hasTable('diary_comments')) {
            Schema::dropIfExists('diary_comments');
        }

        Schema::dropIfExists('diaries');
    }
};
