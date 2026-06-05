<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('department_field_configs', function (Blueprint $table) {
            $table->string('source', 50)->nullable()->default(null)->after('allowed_item_ids');
            $table->string('source_group', 100)->nullable()->default(null)->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('department_field_configs', function (Blueprint $table) {
            $table->dropColumn(['source', 'source_group']);
        });
    }
};
