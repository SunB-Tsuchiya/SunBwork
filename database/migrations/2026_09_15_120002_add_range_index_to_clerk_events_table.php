<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clerk_events', function (Blueprint $table) {
            $table->index(['company_id', 'starts_at'], 'clerk_events_company_start_index');
        });
    }

    public function down(): void
    {
        Schema::table('clerk_events', function (Blueprint $table) {
            $table->dropIndex('clerk_events_company_start_index');
        });
    }
};
