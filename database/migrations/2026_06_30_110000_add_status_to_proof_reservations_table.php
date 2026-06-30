<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proof_reservations', function (Blueprint $table) {
            $table->string('status', 20)->default('reserved')->after('note')->index();
        });
    }

    public function down(): void
    {
        Schema::table('proof_reservations', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
