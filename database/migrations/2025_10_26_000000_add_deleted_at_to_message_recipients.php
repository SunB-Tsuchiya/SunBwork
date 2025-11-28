<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('message_recipients', function (Blueprint $table) {
            if (!Schema::hasColumn('message_recipients', 'deleted_at')) {
                $table->timestamp('deleted_at')->nullable()->after('read_at')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('message_recipients', function (Blueprint $table) {
            if (Schema::hasColumn('message_recipients', 'deleted_at')) {
                $table->dropColumn('deleted_at');
            }
        });
    }
};
