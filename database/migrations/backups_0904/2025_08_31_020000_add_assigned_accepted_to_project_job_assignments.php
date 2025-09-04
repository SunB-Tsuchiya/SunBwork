<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('project_job_assignments', function (Blueprint $table) {
            $table->boolean('assigned')->default(false)->after('desired_time');
            $table->boolean('accepted')->default(false)->after('assigned');
        });
    }

    public function down()
    {
        Schema::table('project_job_assignments', function (Blueprint $table) {
            $table->dropColumn(['assigned', 'accepted']);
        });
    }
};
