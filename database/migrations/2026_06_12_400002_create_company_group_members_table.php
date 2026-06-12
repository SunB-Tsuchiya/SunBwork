<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_group_id')->constrained('company_groups')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->unique(['company_group_id', 'company_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_group_members');
    }
};
