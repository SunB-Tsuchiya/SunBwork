<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_item_types', function (Blueprint $table) {
            if (!Schema::hasColumn('work_item_types', 'sort_order')) {
                $table->integer('sort_order')->default(0)->after('slug');
            }
        });

        Schema::table('sizes', function (Blueprint $table) {
            if (!Schema::hasColumn('sizes', 'sort_order')) {
                $table->integer('sort_order')->default(0)->after('label');
            }
        });

        Schema::table('stages', function (Blueprint $table) {
            if (!Schema::hasColumn('stages', 'sort_order')) {
                $table->integer('sort_order')->default(0)->after('order_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('work_item_types', function (Blueprint $table) {
            if (Schema::hasColumn('work_item_types', 'sort_order')) {
                $table->dropColumn('sort_order');
            }
        });

        Schema::table('sizes', function (Blueprint $table) {
            if (Schema::hasColumn('sizes', 'sort_order')) {
                $table->dropColumn('sort_order');
            }
        });

        Schema::table('stages', function (Blueprint $table) {
            if (Schema::hasColumn('stages', 'sort_order')) {
                $table->dropColumn('sort_order');
            }
        });
    }
};
