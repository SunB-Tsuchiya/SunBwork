<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overtime_item_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('coefficient', 6, 3)->default(1.000);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('type')->default('over'); // 'over' = 残業, 'short' = 早退
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_item_types');
    }
};
