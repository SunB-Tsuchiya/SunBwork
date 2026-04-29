<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_jobs', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('shared_from_id');
            $table->string('original_filename')->nullable()->after('image_path');
        });
    }

    public function down(): void
    {
        Schema::table('project_jobs', function (Blueprint $table) {
            $table->dropColumn(['image_path', 'original_filename']);
        });
    }
};
