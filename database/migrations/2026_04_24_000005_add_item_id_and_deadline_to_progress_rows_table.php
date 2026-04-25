<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('progress_rows', function (Blueprint $table) {
            $table->foreignId('project_job_item_id')->nullable()->after('parent_id')
                ->constrained('project_job_items')->nullOnDelete();
            $table->date('deadline')->nullable()->after('project_job_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('progress_rows', function (Blueprint $table) {
            $table->dropForeign(['project_job_item_id']);
            $table->dropColumn(['project_job_item_id', 'deadline']);
        });
    }
};
