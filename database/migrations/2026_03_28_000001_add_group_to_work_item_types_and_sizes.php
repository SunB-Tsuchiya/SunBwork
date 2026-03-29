<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('work_item_types', 'group')) {
            Schema::table('work_item_types', function (Blueprint $table) {
                $table->string('group', 50)->nullable()->after('name');
            });
        }

        if (!Schema::hasColumn('sizes', 'group')) {
            Schema::table('sizes', function (Blueprint $table) {
                $table->string('group', 50)->nullable()->after('name');
            });
        }
    }

    public function down(): void
    {
        Schema::table('work_item_types', function (Blueprint $table) {
            if (Schema::hasColumn('work_item_types', 'group')) {
                $table->dropColumn('group');
            }
        });
        Schema::table('sizes', function (Blueprint $table) {
            if (Schema::hasColumn('sizes', 'group')) {
                $table->dropColumn('group');
            }
        });
    }
};
