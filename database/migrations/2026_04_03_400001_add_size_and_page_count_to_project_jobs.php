<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_jobs', function (Blueprint $table) {
            $table->unsignedBigInteger('size_id')->nullable()->after('detail');
            $table->unsignedInteger('page_count')->nullable()->after('size_id');
        });
    }

    public function down(): void
    {
        Schema::table('project_jobs', function (Blueprint $table) {
            $table->dropColumn(['size_id', 'page_count']);
        });
    }
};
