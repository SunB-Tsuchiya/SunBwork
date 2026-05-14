<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'is_ghost')) {
                $table->boolean('is_ghost')->default(false)->after('remember_token');
            }
            if (!Schema::hasColumn('users', 'ghost_owner_id')) {
                $table->foreignId('ghost_owner_id')->nullable()->constrained('users')->nullOnDelete()->after('is_ghost');
            }
            if (!Schema::hasColumn('users', 'ghost_expires_at')) {
                $table->timestamp('ghost_expires_at')->nullable()->after('ghost_owner_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'ghost_expires_at')) {
                $table->dropColumn('ghost_expires_at');
            }
            if (Schema::hasColumn('users', 'ghost_owner_id')) {
                $table->dropForeign(['ghost_owner_id']);
                $table->dropColumn('ghost_owner_id');
            }
            if (Schema::hasColumn('users', 'is_ghost')) {
                $table->dropColumn('is_ghost');
            }
        });
    }
};
