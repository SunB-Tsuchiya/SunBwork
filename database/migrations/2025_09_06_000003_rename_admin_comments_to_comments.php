<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diaries', function (Blueprint $table) {
            if (Schema::hasColumn('diaries', 'admin_comments') && !Schema::hasColumn('diaries', 'comments')) {
                $table->renameColumn('admin_comments', 'comments');
            }
        });
    }

    public function down(): void
    {
        Schema::table('diaries', function (Blueprint $table) {
            if (Schema::hasColumn('diaries', 'comments') && !Schema::hasColumn('diaries', 'admin_comments')) {
                $table->renameColumn('comments', 'admin_comments');
            }
        });
    }
};
