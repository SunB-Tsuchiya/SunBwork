<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Existing choices are protected without rewriting expense records.
        Schema::table('transport_expenses', function (Blueprint $table) {
            $table->boolean('billing_date_manual')->default(true)->after('billing_date');
        });
    }

    public function down(): void
    {
        Schema::table('transport_expenses', fn (Blueprint $table) => $table->dropColumn('billing_date_manual'));
    }
};
