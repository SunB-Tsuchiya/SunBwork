<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * company_name カラムは name に統合済みのため削除（さくら本番に残存していたカラムを除去）
     */
    public function up(): void
    {
        Schema::table('subcontractors', function (Blueprint $table) {
            if (Schema::hasColumn('subcontractors', 'company_name')) {
                $table->dropColumn('company_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subcontractors', function (Blueprint $table) {
            $table->string('company_name')->nullable()->after('name');
        });
    }
};
