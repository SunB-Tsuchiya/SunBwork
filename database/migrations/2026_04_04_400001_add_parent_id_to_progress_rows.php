<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('progress_rows', function (Blueprint $table) {
            if (DB::getDriverName() === 'sqlite') {
                $table->unsignedBigInteger('parent_id')->nullable();
            } else {
                $table->unsignedBigInteger('parent_id')->nullable()->after('order');
                $table->foreign('parent_id')
                    ->references('id')
                    ->on('progress_rows')
                    ->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('progress_rows', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign(['parent_id']);
            }
            $table->dropColumn('parent_id');
        });
    }
};
