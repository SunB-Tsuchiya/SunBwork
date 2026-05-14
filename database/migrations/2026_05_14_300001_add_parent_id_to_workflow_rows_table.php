<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_rows', function (Blueprint $table) {
            if (!Schema::hasColumn('workflow_rows', 'parent_id')) {
                $table->unsignedBigInteger('parent_id')->nullable()->after('sheet_id');
                $table->foreign('parent_id')
                    ->references('id')->on('workflow_rows')
                    ->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('workflow_rows', function (Blueprint $table) {
            if (Schema::hasColumn('workflow_rows', 'parent_id')) {
                $table->dropForeign(['parent_id']);
                $table->dropColumn('parent_id');
            }
        });
    }
};
