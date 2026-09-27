<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mginbon')->create('mginbon_value_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mginbon_project_id')->constrained('mginbon_projects')->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('name', 100);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['mginbon_project_id', 'code']);
        });

        Schema::connection('mginbon')->create('mginbon_value_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mginbon_value_list_id')->constrained('mginbon_value_lists')->cascadeOnDelete();
            $table->string('value', 255);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('linked_user_id')->nullable();
            $table->unsignedBigInteger('linked_subcontractor_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['mginbon_value_list_id', 'value']);
            $table->index(['mginbon_value_list_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::connection('mginbon')->dropIfExists('mginbon_value_list_items');
        Schema::connection('mginbon')->dropIfExists('mginbon_value_lists');
    }
};
