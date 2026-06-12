<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_board_cards', function (Blueprint $table) {
            $table->string('card_color', 30)->nullable()->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('team_board_cards', function (Blueprint $table) {
            $table->dropColumn('card_color');
        });
    }
};
