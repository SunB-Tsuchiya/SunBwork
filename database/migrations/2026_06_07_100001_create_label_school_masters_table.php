<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('label_school_masters', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('display_name', 150);
            $table->string('area', 50)->default('');
            $table->string('route', 10)->nullable();
            $table->unsignedTinyInteger('stop_order')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('label_school_masters');
    }
};
