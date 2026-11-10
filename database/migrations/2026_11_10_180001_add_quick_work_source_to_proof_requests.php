<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proof_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('source_personal_work_entry_id')->nullable()->index('proof_requests_quick_source_index');
        });
    }

    public function down(): void
    {
        Schema::table('proof_requests', function (Blueprint $table) {
            $table->dropIndex('proof_requests_quick_source_index');
            $table->dropColumn('source_personal_work_entry_id');
        });
    }
};
