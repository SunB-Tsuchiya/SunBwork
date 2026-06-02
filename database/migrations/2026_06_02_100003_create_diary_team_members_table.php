<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diary_team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diary_team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['diary_team_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diary_team_members');
    }
};
