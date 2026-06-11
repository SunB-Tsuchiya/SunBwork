<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('label_route_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained('label_routes')->cascadeOnDelete();
            $table->unsignedTinyInteger('stop_order');      // Excelのラベル番号 (2〜10)
            $table->string('school_code', 10)->nullable();  // label_school_masters.code (疎結合)
            $table->string('school_name', 150)->default('');
            $table->string('arrival_time', 10)->nullable(); // HH:MM
            $table->string('notes', 200)->nullable();       // 鍵情報など
            $table->timestamps();

            $table->unique(['route_id', 'stop_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('label_route_stops');
    }
};
