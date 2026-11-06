<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broad_project_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('broad_project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['broad_project_id', 'client_id'], 'broad_project_clients_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broad_project_clients');
    }
};
