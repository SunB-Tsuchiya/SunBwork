<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broad_project_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('broad_project_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'broad_project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broad_project_favorites');
    }
};
