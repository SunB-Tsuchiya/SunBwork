<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proof_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('workflow_cell_id')->nullable()->after('proof_cell_id');
            $table->foreign('workflow_cell_id')->references('id')->on('workflow_cells')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('proof_requests', function (Blueprint $table) {
            $table->dropForeign(['workflow_cell_id']);
            $table->dropColumn('workflow_cell_id');
        });
    }
};
