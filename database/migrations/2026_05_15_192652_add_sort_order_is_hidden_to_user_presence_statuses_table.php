<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_presence_statuses', function (Blueprint $table) {
            if (!Schema::hasColumn('user_presence_statuses', 'sort_order')) {
                $table->integer('sort_order')->default(0)->after('status_source');
            }
            if (!Schema::hasColumn('user_presence_statuses', 'is_hidden')) {
                $table->boolean('is_hidden')->default(false)->after('sort_order');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_presence_statuses', function (Blueprint $table) {
            $table->dropColumn(['sort_order', 'is_hidden']);
        });
    }
};
