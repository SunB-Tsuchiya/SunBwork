<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department_field_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->enum('slot', ['type', 'stage', 'size', 'amounts']);
            $table->string('label', 100)->default('');
            $table->boolean('enabled')->default(true);
            $table->json('allowed_item_ids')->nullable();
            $table->tinyInteger('sort_order')->unsigned()->default(0);
            $table->timestamps();

            $table->unique(['department_id', 'slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_field_configs');
    }
};
