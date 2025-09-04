<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diaries', function (Blueprint $table) {
            $table->boolean('admin_read')->default(false)->change();
        });
    }

    public function down(): void
    {
        // revert not implemented
    }
};
