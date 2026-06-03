<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_board_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_board_id')->constrained('team_boards')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('color', 50)->default('blue');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_board_columns');
    }
};
