<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('events', 'meeting_definition_id')) {
            Schema::table('events', function (Blueprint $table) {
                $table->unsignedBigInteger('meeting_definition_id')->nullable()->after('project_job_id');
                $table->foreign('meeting_definition_id')->references('id')->on('meeting_definitions')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('events', 'meeting_definition_id')) {
            Schema::table('events', function (Blueprint $table) {
                $table->dropForeign(['meeting_definition_id']);
                $table->dropColumn('meeting_definition_id');
            });
        }
    }
};
