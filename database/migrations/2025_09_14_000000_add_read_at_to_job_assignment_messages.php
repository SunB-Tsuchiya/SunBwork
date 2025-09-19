<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('job_assignment_messages')) return;

        Schema::table('job_assignment_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('job_assignment_messages', 'read_at')) {
                $table->timestamp('read_at')->nullable()->after('attachments');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('job_assignment_messages')) return;

        Schema::table('job_assignment_messages', function (Blueprint $table) {
            if (Schema::hasColumn('job_assignment_messages', 'read_at')) {
                $table->dropColumn('read_at');
            }
        });
    }
};
