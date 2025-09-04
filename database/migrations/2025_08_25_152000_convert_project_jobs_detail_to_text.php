<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_jobs', function (Blueprint $table) {
            // change detail column to text if not already
            $table->text('detail')->nullable()->change();
        });
    }

    public function down(): void
    {
        // revert not implemented
    }
};
