<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Copy numeric mm columns into width/height and set unit='mm' for those rows, then drop mm columns
        if (Schema::hasColumn('sizes', 'width_mm')) {
            DB::statement("UPDATE sizes SET width = width_mm, height = height_mm, unit = 'mm' WHERE (width IS NULL OR width = 0) AND (width_mm IS NOT NULL OR height_mm IS NOT NULL)");
        }

        Schema::table('sizes', function (Blueprint $table) {
            if (Schema::hasColumn('sizes', 'width_mm')) {
                $table->dropColumn(['width_mm', 'height_mm']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('sizes', function (Blueprint $table) {
            if (!Schema::hasColumn('sizes', 'width_mm')) {
                $table->integer('width_mm')->nullable()->after('unit');
            }
            if (!Schema::hasColumn('sizes', 'height_mm')) {
                $table->integer('height_mm')->nullable()->after('width_mm');
            }
        });

        // copy back when unit is mm
        DB::statement("UPDATE sizes SET width_mm = CAST(width AS SIGNED), height_mm = CAST(height AS SIGNED) WHERE unit = 'mm'");
    }
};
