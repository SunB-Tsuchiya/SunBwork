<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // billing_request_id 列は既に存在するため FK 制約とインデックスのみ追加
        Schema::table('transport_expenses', function (Blueprint $table) {
            $table->foreign('billing_request_id')
                  ->references('id')
                  ->on('transport_billing_requests')
                  ->nullOnDelete();

            $table->index('billing_request_id');
        });
    }

    public function down(): void
    {
        Schema::table('transport_expenses', function (Blueprint $table) {
            $table->dropForeign(['billing_request_id']);
            $table->dropColumn('billing_request_id');
        });
    }
};
