<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sizes', function (Blueprint $table) {
            // add float/decimal columns for display and a unit
            if (!Schema::hasColumn('sizes', 'width')) {
                $table->decimal('width', 10, 2)->nullable()->after('label');
            }
            if (!Schema::hasColumn('sizes', 'height')) {
                $table->decimal('height', 10, 2)->nullable()->after('width');
            }
            if (!Schema::hasColumn('sizes', 'unit')) {
                $table->string('unit', 20)->nullable()->after('height');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sizes', function (Blueprint $table) {
            if (Schema::hasColumn('sizes', 'unit')) {
                $table->dropColumn('unit');
            }
            if (Schema::hasColumn('sizes', 'height')) {
                $table->dropColumn('height');
            }
            if (Schema::hasColumn('sizes', 'width')) {
                $table->dropColumn('width');
            }
        });
    }
};
