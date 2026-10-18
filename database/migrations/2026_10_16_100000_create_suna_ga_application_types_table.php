<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // サンエー印刷 総務: 申請の種類（年末調整など）
        Schema::create('suna_ga_application_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['company_id', 'is_active', 'sort_order'], 'suna_ga_app_types_list_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suna_ga_application_types');
    }
};
