<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('project_schedule_comments', function (Blueprint $table) {
            if (!Schema::hasColumn('project_schedule_comments', 'date')) {
                $table->date('date')->nullable()->after('body')->index();
            }
        });
    }

    public function down()
    {
        Schema::table('project_schedule_comments', function (Blueprint $table) {
            if (Schema::hasColumn('project_schedule_comments', 'date')) {
                $table->dropColumn('date');
            }
        });
    }
};
