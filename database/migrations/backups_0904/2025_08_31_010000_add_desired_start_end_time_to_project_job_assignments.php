<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('project_job_assignments', function (Blueprint $table) {
            $table->date('desired_start_date')->nullable()->after('difficulty');
            $table->date('desired_end_date')->nullable()->after('desired_start_date');
            $table->time('desired_time')->nullable()->after('desired_end_date');
        });
    }

    public function down()
    {
        Schema::table('project_job_assignments', function (Blueprint $table) {
            $table->dropColumn(['desired_start_date', 'desired_end_date', 'desired_time']);
        });
    }
};
