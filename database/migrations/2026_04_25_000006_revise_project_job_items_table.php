<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_job_items', function (Blueprint $table) {
            // 旧 FK・カラムを削除
            $table->dropForeign(['project_job_id']);
            $table->dropColumn(['project_job_id', 'category', 'start_date', 'deadline']);

            // 新カラムを追加
            $table->foreignId('progress_sheet_id')
                ->after('id')
                ->constrained('progress_sheets')
                ->cascadeOnDelete();

            $table->enum('type', ['row', 'column'])->default('row')->after('name');

            $table->foreignId('row_id')
                ->nullable()
                ->after('type')
                ->constrained('progress_rows')
                ->nullOnDelete();

            $table->string('col_key')->nullable()->after('row_id');
            $table->string('parent_label')->nullable()->after('col_key');
            $table->boolean('calendar_linked')->default(false)->after('parent_label');
        });
    }

    public function down(): void
    {
        Schema::table('project_job_items', function (Blueprint $table) {
            $table->dropForeign(['progress_sheet_id']);
            $table->dropForeign(['row_id']);
            $table->dropColumn(['progress_sheet_id', 'type', 'row_id', 'col_key', 'parent_label', 'calendar_linked']);

            $table->foreignId('project_job_id')->constrained()->cascadeOnDelete();
            $table->string('category')->nullable();
            $table->date('start_date')->nullable();
            $table->date('deadline')->nullable();
        });
    }
};
