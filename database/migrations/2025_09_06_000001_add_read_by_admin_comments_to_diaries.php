<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add JSON columns `read_by` and `admin_comments` if they do not exist yet.
     */
    public function up(): void
    {
        if (!Schema::hasTable('diaries')) {
            return;
        }

        if (!Schema::hasColumn('diaries', 'read_by') || !Schema::hasColumn('diaries', 'admin_comments')) {
            Schema::table('diaries', function (Blueprint $table) {
                if (!Schema::hasColumn('diaries', 'read_by')) {
                    $table->json('read_by')->nullable()->after('content')->comment('User/admin ids who marked as read (JSON array)');
                }
                if (!Schema::hasColumn('diaries', 'admin_comments')) {
                    $table->json('admin_comments')->nullable()->after('read_by')->comment('Admin comments as JSON array of objects {admin_id, admin_name, comment, created_at}');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('diaries')) {
            return;
        }
        Schema::table('diaries', function (Blueprint $table) {
            if (Schema::hasColumn('diaries', 'admin_comments')) {
                $table->dropColumn('admin_comments');
            }
            if (Schema::hasColumn('diaries', 'read_by')) {
                $table->dropColumn('read_by');
            }
        });
    }
};
