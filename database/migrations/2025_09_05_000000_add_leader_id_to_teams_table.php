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
        Schema::table('teams', function (Blueprint $table) {
            if (!Schema::hasColumn('teams', 'leader_id')) {
                $table->unsignedBigInteger('leader_id')->nullable()->after('user_id');
                $table->foreign('leader_id')->references('id')->on('users')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('teams', function (Blueprint $table) {
            if (Schema::hasColumn('teams', 'leader_id')) {
                $table->dropForeign(['leader_id']);
                $table->dropColumn('leader_id');
            }
        });
    }
};
