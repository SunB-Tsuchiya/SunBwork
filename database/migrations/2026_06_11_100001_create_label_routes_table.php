<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('label_routes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();         // A1, B1, G水便 など
            $table->unsignedTinyInteger('course');        // 1 or 2
            $table->string('area', 50)->default('');      // 首都圏, NG便
            $table->string('day1', 20)->default('');      // 月曜日
            $table->string('day1_start', 50)->default(''); // コバ発
            $table->string('day2', 20)->nullable();       // 木曜日
            $table->string('day2_start', 50)->nullable(); // 町田発
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('label_routes');
    }
};
