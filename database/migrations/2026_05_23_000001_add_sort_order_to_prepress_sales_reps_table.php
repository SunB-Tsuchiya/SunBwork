<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prepress_sales_reps', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('company');
        });

        // 既存レコードを id 順で sort_order を初期化
        DB::statement('UPDATE prepress_sales_reps SET sort_order = id');
    }

    public function down(): void
    {
        Schema::table('prepress_sales_reps', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
