<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prepress_tickets', function (Blueprint $table) {
            $table->foreignId('sales_rep_id')
                  ->nullable()
                  ->after('sales_rep')
                  ->constrained('prepress_sales_reps')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('prepress_tickets', function (Blueprint $table) {
            $table->dropForeign(['sales_rep_id']);
            $table->dropColumn('sales_rep_id');
        });
    }
};
