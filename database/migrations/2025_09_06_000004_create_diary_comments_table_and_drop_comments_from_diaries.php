<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // create diary_comments table
        if (!Schema::hasTable('diary_comments')) {
            Schema::create('diary_comments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('diary_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('user_name')->nullable();
                $table->text('comment');
                $table->timestamps();

                $table->foreign('diary_id')->references('id')->on('diaries')->onDelete('cascade');
            });
        }

        // drop comments column from diaries if present
        if (Schema::hasColumn('diaries', 'comments')) {
            Schema::table('diaries', function (Blueprint $table) {
                $table->dropColumn('comments');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // add comments column back to diaries as JSON nullable
        if (!Schema::hasColumn('diaries', 'comments')) {
            Schema::table('diaries', function (Blueprint $table) {
                $table->json('comments')->nullable()->after('read_by');
            });
        }

        Schema::dropIfExists('diary_comments');
    }
};
