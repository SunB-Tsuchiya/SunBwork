<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meeting_rooms', function (Blueprint $table) {
            $table->time('available_from')->nullable()->after('sort_order'); // null=制限なし
            $table->time('available_to')->nullable()->after('available_from');
        });
    }

    public function down(): void
    {
        Schema::table('meeting_rooms', function (Blueprint $table) {
            $table->dropColumn(['available_from', 'available_to']);
        });
    }
};
