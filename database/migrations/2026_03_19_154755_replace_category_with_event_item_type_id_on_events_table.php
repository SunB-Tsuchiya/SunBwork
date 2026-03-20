<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('category');
            $table->unsignedBigInteger('event_item_type_id')->nullable()->after('title');
            $table->foreign('event_item_type_id')->references('id')->on('event_item_types')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['event_item_type_id']);
            $table->dropColumn('event_item_type_id');
            $table->string('category')->nullable()->after('title');
        });
    }
};
