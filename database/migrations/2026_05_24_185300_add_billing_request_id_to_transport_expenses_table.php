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
        if (!Schema::hasColumn('transport_expenses', 'billing_request_id')) {
            Schema::table('transport_expenses', function (Blueprint $table) {
                $table->unsignedBigInteger('billing_request_id')->nullable()->after('status');
                $table->foreign('billing_request_id')
                      ->references('id')
                      ->on('transport_billing_requests')
                      ->nullOnDelete();
                $table->index('billing_request_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('transport_expenses', 'billing_request_id')) {
            Schema::table('transport_expenses', function (Blueprint $table) {
                $table->dropForeign(['billing_request_id']);
                $table->dropIndex(['billing_request_id']);
                $table->dropColumn('billing_request_id');
            });
        }
    }
};
