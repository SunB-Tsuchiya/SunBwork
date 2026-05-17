<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_rows', function (Blueprint $table) {
            $table->foreignId('stage_id')->nullable()->after('item_entry_id')
                ->constrained('stages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('workflow_rows', function (Blueprint $table) {
            $table->dropForeign(['stage_id']);
            $table->dropColumn('stage_id');
        });
    }
};
