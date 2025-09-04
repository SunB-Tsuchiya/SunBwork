<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_schedule_comments', function (Blueprint $table) {
            $table->date('comment_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('project_schedule_comments', function (Blueprint $table) {
            $table->dropColumn('comment_date');
        });
    }
};
