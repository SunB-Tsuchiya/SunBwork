<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// stop_order を tinyInt(max255) → smallInt(max65535) に修正、default_qty 追加
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('label_school_masters');
        Schema::create('label_school_masters', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('display_name', 150);
            $table->string('area', 50)->default('');
            $table->string('route', 10)->nullable();
            $table->unsignedSmallInteger('stop_order')->nullable();
            $table->unsignedSmallInteger('default_qty')->default(0);
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
