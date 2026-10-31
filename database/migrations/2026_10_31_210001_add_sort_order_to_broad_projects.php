<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('broad_projects', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('broad_projects', fn (Blueprint $table) => $table->dropColumn('sort_order'));
    }
};
