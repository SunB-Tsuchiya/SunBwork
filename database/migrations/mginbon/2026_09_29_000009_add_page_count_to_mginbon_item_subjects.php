<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mginbon')->table('mginbon_item_subjects', function (Blueprint $table) {
            $table->unsignedSmallInteger('page_count')->nullable()->after('mginbon_subject_id');
        });
    }

    public function down(): void
    {
        Schema::connection('mginbon')->table('mginbon_item_subjects', function (Blueprint $table) {
            $table->dropColumn('page_count');
        });
    }
};
