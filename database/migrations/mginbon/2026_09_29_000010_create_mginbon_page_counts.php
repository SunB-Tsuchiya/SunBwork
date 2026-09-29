<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mginbon')->create('mginbon_page_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mginbon_production_unit_id')->constrained('mginbon_production_units')->cascadeOnDelete();
            $table->foreignId('mginbon_subject_id')->constrained('mginbon_subjects')->restrictOnDelete();
            $table->string('page_type', 30);
            $table->unsignedSmallInteger('page_count');
            $table->timestamps();
            $table->unique(['mginbon_production_unit_id', 'mginbon_subject_id', 'page_type'], 'mginbon_page_count_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::connection('mginbon')->dropIfExists('mginbon_page_counts');
    }
};
