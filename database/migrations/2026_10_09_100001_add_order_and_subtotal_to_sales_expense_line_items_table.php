<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Phase 21追加: 科目の表示順（Excel登場順）と、Excel上の小計（小計①/小計②等）の区切りを
// 保持するための列を追加する。既存取込データはこれらの情報を持たないため、
// 変更反映には経費表の再取込みが必要（旧版は履歴として残るため上書きではない）。
return new class extends Migration
{
    protected $connection = 'sales';

    public function up(): void
    {
        Schema::connection('sales')->table('sales_expense_line_items', function (Blueprint $table) {
            $table->unsignedSmallInteger('category_order')->default(0)->after('category');
            $table->unsignedSmallInteger('subtotal_group')->default(1)->after('is_shared_split');
            $table->string('subtotal_label', 100)->nullable()->after('subtotal_group');
        });
    }

    public function down(): void
    {
        Schema::connection('sales')->table('sales_expense_line_items', function (Blueprint $table) {
            $table->dropColumn(['category_order', 'subtotal_group', 'subtotal_label']);
        });
    }
};
