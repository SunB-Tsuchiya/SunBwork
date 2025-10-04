<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('statuses') && ! Schema::hasColumn('statuses', 'key')) {
            Schema::table('statuses', function (Blueprint $table) {
                $table->string('key')->nullable()->unique()->after('slug');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('statuses') && Schema::hasColumn('statuses', 'key')) {
            Schema::table('statuses', function (Blueprint $table) {
                $table->dropUnique(['key']);
                $table->dropColumn('key');
            });
        }
    }
};
