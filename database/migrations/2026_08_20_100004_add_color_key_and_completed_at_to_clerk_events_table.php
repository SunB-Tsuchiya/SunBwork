<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clerk_events', function (Blueprint $table) {
            $table->string('color_key', 20)->nullable()->after('all_day');
            $table->timestamp('completed_at')->nullable()->after('color_key');
        });
    }

    public function down(): void
    {
        Schema::table('clerk_events', function (Blueprint $table) {
            $table->dropColumn(['color_key', 'completed_at']);
        });
    }
};
