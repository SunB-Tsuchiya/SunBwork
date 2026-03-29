<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leader_permissions', function (Blueprint $table) {
            $table->boolean('dispatch_management')->default(false)->after('work_record_management');
        });
    }

    public function down(): void
    {
        Schema::table('leader_permissions', function (Blueprint $table) {
            $table->dropColumn('dispatch_management');
        });
    }
};
