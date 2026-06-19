<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_pages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->string('password', 255);
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_pages');
    }
};
