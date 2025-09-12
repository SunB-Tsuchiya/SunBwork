<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            // role column expected by application pivot usage
            $table->string('role')->nullable();
            $table->timestamps();
            $table->unique(['team_id', 'user_id'], 'team_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_user');
    }
};
