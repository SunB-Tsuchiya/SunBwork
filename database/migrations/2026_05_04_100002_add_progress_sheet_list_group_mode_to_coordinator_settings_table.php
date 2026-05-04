<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coordinator_settings', function (Blueprint $table) {
            $table->string('progress_sheet_list_group_mode', 20)->default('date')->after('jobbox_group_mode');
        });
    }

    public function down(): void
    {
        Schema::table('coordinator_settings', function (Blueprint $table) {
            $table->dropColumn('progress_sheet_list_group_mode');
        });
    }
};
