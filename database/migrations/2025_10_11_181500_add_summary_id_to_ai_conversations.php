<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->unsignedBigInteger('summary_id')->nullable()->after('system_prompt');
            $table->foreign('summary_id')->references('id')->on('ai_summaries')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->dropForeign(['summary_id']);
            $table->dropColumn('summary_id');
        });
    }
};
