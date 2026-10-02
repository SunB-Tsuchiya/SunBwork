<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mginbon')->create('mginbon_saved_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mginbon_project_id')->nullable()->constrained('mginbon_projects')->cascadeOnDelete();
            $table->unsignedBigInteger('owner_user_id')->comment('SBWork本体DBのusers.id');
            $table->string('name', 100);
            $table->string('description', 500)->nullable();
            $table->string('scope', 20)->default('personal');
            $table->unsignedTinyInteger('criteria_version')->default(2);
            $table->json('criteria');
            $table->string('display_mode', 20)->default('list');
            $table->string('sort_key', 100)->nullable();
            $table->timestamps();
            $table->index(['mginbon_project_id', 'scope'], 'mginbon_saved_search_project_scope_idx');
            $table->index('owner_user_id');
            $table->unique(['mginbon_project_id', 'owner_user_id', 'scope', 'name'], 'mginbon_saved_search_scope_name_unique');
        });
    }

    public function down(): void
    {
        Schema::connection('mginbon')->dropIfExists('mginbon_saved_searches');
    }
};
