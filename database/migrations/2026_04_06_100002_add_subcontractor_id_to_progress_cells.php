<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('progress_cells', function (Blueprint $table) {
            $table->unsignedBigInteger('value_subcontractor_id')->nullable()->after('value_user_id');

            if (DB::getDriverName() !== 'sqlite') {
                $table->foreign('value_subcontractor_id')->references('id')->on('subcontractors')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('progress_cells', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign(['value_subcontractor_id']);
            }
            $table->dropColumn('value_subcontractor_id');
        });
    }
};
