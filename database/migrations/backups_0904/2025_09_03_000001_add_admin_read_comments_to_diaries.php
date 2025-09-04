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
        Schema::table('diaries', function (Blueprint $table) {
            $table->json('read_by')->nullable()->after('content')->comment('Admin/user ids who marked as read (JSON array)');
            $table->json('admin_comments')->nullable()->after('read_by')->comment('Admin comments as JSON array of objects {admin_id, admin_name, comment, created_at}');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('diaries', function (Blueprint $table) {
            $table->dropColumn(['read_by', 'admin_comments']);
        });
    }
};
