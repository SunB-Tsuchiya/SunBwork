<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mginbon')->table('mginbon_work_packages', function (Blueprint $table) {
            $table->timestamp('assigned_at')->nullable()->after('status');
            $table->timestamp('completed_at')->nullable()->after('assigned_at');
            $table->timestamp('cancelled_at')->nullable()->after('completed_at');
        });

        DB::connection('mginbon')->table('mginbon_work_packages')
            ->whereNull('assigned_at')
            ->update(['assigned_at' => DB::raw('created_at')]);
        DB::connection('mginbon')->table('mginbon_work_packages')
            ->where('status', 'completed')
            ->whereNull('completed_at')
            ->update(['completed_at' => DB::raw('updated_at')]);
        DB::connection('mginbon')->table('mginbon_work_packages')
            ->where('status', 'cancelled')
            ->whereNull('cancelled_at')
            ->update(['cancelled_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::connection('mginbon')->table('mginbon_work_packages', function (Blueprint $table) {
            $table->dropColumn(['assigned_at', 'completed_at', 'cancelled_at']);
        });
    }
};
