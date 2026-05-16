<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iruka_status_orders', function (Blueprint $table) {
            $table->string('custom_label', 50)->nullable()->after('is_active');
            $table->string('custom_color', 30)->nullable()->after('custom_label');
        });
    }

    public function down(): void
    {
        Schema::table('iruka_status_orders', function (Blueprint $table) {
            $table->dropColumn(['custom_label', 'custom_color']);
        });
    }
};
