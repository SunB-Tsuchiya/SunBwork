<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('company_type', 32)->default('general')->after('code');
        });

        // 既存の SUNBRAIN 会社を sunbrain タイプに更新
        DB::table('companies')->where('code', 'SUNBRAIN')->update(['company_type' => 'sunbrain']);
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('company_type');
        });
    }
};
