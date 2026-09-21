<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mginbon')->create('mginbon_change_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mginbon_project_id')->constrained('mginbon_projects')->cascadeOnDelete();
            $table->foreignId('mginbon_item_id')->nullable()->constrained('mginbon_items')->nullOnDelete();
            $table->foreignId('mginbon_item_subject_id')->nullable()->constrained('mginbon_item_subjects')->nullOnDelete();
            $table->unsignedBigInteger('changed_by')->nullable()->comment('SBWork本体DBのusers.id');
            $table->string('field_path', 180);
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->string('source', 30)->default('manual');
            $table->timestamps();
            $table->index(['mginbon_item_id', 'created_at']);
            $table->index('changed_by');
        });
    }

    public function down(): void
    {
        Schema::connection('mginbon')->dropIfExists('mginbon_change_logs');
    }
};
