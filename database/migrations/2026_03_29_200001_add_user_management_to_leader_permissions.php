<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leader_permissions', function (Blueprint $table) {
            // dispatch_management が未追加の場合に備えて安全に追加
            if (! Schema::hasColumn('leader_permissions', 'dispatch_management')) {
                $table->boolean('dispatch_management')->default(false)->after('work_record_management');
            }
            $table->boolean('user_management')->default(false)->after('dispatch_management');
        });
    }

    public function down(): void
    {
        Schema::table('leader_permissions', function (Blueprint $table) {
            $table->dropColumn('user_management');
        });
    }
};
