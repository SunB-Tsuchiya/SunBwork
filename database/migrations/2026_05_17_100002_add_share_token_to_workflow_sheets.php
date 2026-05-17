<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_sheets', function (Blueprint $table) {
            $table->string('share_token', 64)->nullable()->unique()->after('column_config');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_sheets', function (Blueprint $table) {
            $table->dropColumn('share_token');
        });
    }
};
