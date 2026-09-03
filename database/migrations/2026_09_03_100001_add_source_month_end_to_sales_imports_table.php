<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 半期等の複数月まとめ取込（source_type='range'）に対応するため終了月を追加する
return new class extends Migration
{
    protected $connection = 'sales';

    public function up(): void
    {
        Schema::connection('sales')->table('sales_imports', function (Blueprint $table) {
            $table->tinyInteger('source_month_end')->nullable()->after('source_month');
        });
    }

    public function down(): void
    {
        Schema::connection('sales')->table('sales_imports', function (Blueprint $table) {
            $table->dropColumn('source_month_end');
        });
    }
};
