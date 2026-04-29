<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prepress_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('jobcode', 100)->nullable();
            $table->string('title', 255);
            $table->string('project_name', 255)->nullable();
            $table->string('client_name', 255)->nullable();
            $table->text('memo')->nullable();
            $table->string('status', 20)->default('pending'); // pending / in_progress / completed
            $table->string('image_path', 500)->nullable();
            $table->string('original_filename', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prepress_tickets');
    }
};
