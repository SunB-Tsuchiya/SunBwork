<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prepress_tickets', function (Blueprint $table) {
            $table->string('sales_rep', 100)->nullable()->after('client_name');
        });
    }

    public function down(): void
    {
        Schema::table('prepress_tickets', function (Blueprint $table) {
            $table->dropColumn('sales_rep');
        });
    }
};
