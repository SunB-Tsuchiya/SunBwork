<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('label_route_stops', function (Blueprint $table) {
            $table->string('color_category', 20)->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('label_route_stops', function (Blueprint $table) {
            $table->dropColumn('color_category');
        });
    }
};
