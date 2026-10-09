<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_work_entries', function (Blueprint $table) {
            $table->string('activity_type', 50)->nullable()->after('work_category');
            $table->unsignedSmallInteger('actual_minutes')->nullable()->after('actual_unit');
        });
    }

    public function down(): void
    {
        Schema::table('personal_work_entries', function (Blueprint $table) {
            $table->dropColumn(['activity_type', 'actual_minutes']);
        });
    }
};
