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
        Schema::table('job_assignment_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('job_assignment_messages', 'message_id')) {
                $table->unsignedBigInteger('message_id')->nullable()->after('id')->index();
                $table->foreign('message_id')->references('id')->on('messages')->onDelete('set null');
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
        Schema::table('job_assignment_messages', function (Blueprint $table) {
            if (Schema::hasColumn('job_assignment_messages', 'message_id')) {
                $table->dropForeign(['message_id']);
                $table->dropColumn('message_id');
            }
        });
    }
};
