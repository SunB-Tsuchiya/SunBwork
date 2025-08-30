<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('project_schedules', function (Blueprint $table) {
            // nullable hex color (e.g. #ff0000) or named label
            $table->string('color', 32)->nullable()->after('end_date')->index();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('project_schedules', function (Blueprint $table) {
            if (Schema::hasColumn('project_schedules', 'color')) {
                $table->dropColumn('color');
            }
        });
    }
};
