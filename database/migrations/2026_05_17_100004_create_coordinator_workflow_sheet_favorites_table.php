<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coordinator_workflow_sheet_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('workflow_sheet_id')->constrained('workflow_sheets')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'workflow_sheet_id'], 'cwsf_user_sheet_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coordinator_workflow_sheet_favorites');
    }
};
