<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_job_assignments', function (Blueprint $table) {
            $table->unsignedBigInteger('field_type_val')->nullable()->after('size_id');
            $table->unsignedBigInteger('field_stage_val')->nullable()->after('field_type_val');
            $table->unsignedBigInteger('field_size_val')->nullable()->after('field_stage_val');
        });
    }

    public function down(): void
    {
        Schema::table('project_job_assignments', function (Blueprint $table) {
            $table->dropColumn(['field_type_val', 'field_stage_val', 'field_size_val']);
        });
    }
};
