<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mginbon')->create('mginbon_search_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mginbon_project_id')->constrained('mginbon_projects')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->comment('SBWork本体DBのusers.id');
            $table->unsignedTinyInteger('criteria_version')->default(2);
            $table->json('criteria');
            $table->char('criteria_hash', 64);
            $table->string('summary', 1000);
            $table->unsignedInteger('result_count')->default(0);
            $table->string('display_mode', 20)->default('list');
            $table->timestamp('executed_at');
            $table->timestamps();
            $table->index(['user_id', 'mginbon_project_id', 'executed_at'], 'mginbon_search_history_recent_idx');
            $table->index('criteria_hash');
        });
    }

    public function down(): void
    {
        Schema::connection('mginbon')->dropIfExists('mginbon_search_histories');
    }
};
