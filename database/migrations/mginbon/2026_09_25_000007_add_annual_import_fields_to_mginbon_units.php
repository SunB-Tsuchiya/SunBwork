<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mginbon')->table('mginbon_production_units', function (Blueprint $table) {
            $table->string('alpha_group', 20)->nullable()->after('n_category');
            $table->string('exam_session', 255)->nullable()->after('alpha_group');
            $table->unsignedInteger('source_row_number')->nullable()->after('exam_session');
            $table->json('source_data')->nullable()->after('source_row_number');
            $table->json('import_warnings')->nullable()->after('source_data');
        });
    }

    public function down(): void
    {
        Schema::connection('mginbon')->table('mginbon_production_units', function (Blueprint $table) {
            $table->dropColumn(['alpha_group', 'exam_session', 'source_row_number', 'source_data', 'import_warnings']);
        });
    }
};
