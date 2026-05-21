<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prepress_tickets', function (Blueprint $table) {
            $table->date('submission_date')->nullable()->after('memo');
            $table->date('sb_delivery_date')->nullable()->after('submission_date');
        });
    }

    public function down(): void
    {
        Schema::table('prepress_tickets', function (Blueprint $table) {
            $table->dropColumn(['submission_date', 'sb_delivery_date']);
        });
    }
};
