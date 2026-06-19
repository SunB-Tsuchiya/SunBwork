<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('n_schools', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10);
            $table->smallInteger('year')->unsigned();
            $table->string('name', 200);
            $table->string('category', 20)->default('');
            $table->timestamps();
            $table->unique(['code', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('n_schools');
    }
};
