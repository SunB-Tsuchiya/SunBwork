<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diaries', function (Blueprint $table) {
            if (Schema::hasColumn('diaries', 'admin_read')) {
                $table->dropColumn('admin_read');
            }
        });
    }

    public function down(): void
    {
        Schema::table('diaries', function (Blueprint $table) {
            if (!Schema::hasColumn('diaries', 'admin_read')) {
                $table->boolean('admin_read')->default(false);
            }
        });
    }
};
