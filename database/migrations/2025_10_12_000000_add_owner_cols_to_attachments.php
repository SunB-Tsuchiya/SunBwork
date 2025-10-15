<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            if (!Schema::hasColumn('attachments', 'owner_type')) {
                $table->string('owner_type')->nullable()->after('id');
            }
            if (!Schema::hasColumn('attachments', 'owner_id')) {
                $table->unsignedBigInteger('owner_id')->nullable()->after('owner_type');
            }
            if (!Schema::hasColumn('attachments', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('user_id');
            }
            // path should be present; ensure length is sufficient
            if (Schema::hasColumn('attachments', 'path')) {
                $table->string('path', 1024)->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            if (Schema::hasColumn('attachments', 'owner_type')) $table->dropColumn('owner_type');
            if (Schema::hasColumn('attachments', 'owner_id')) $table->dropColumn('owner_id');
            if (Schema::hasColumn('attachments', 'created_by')) $table->dropColumn('created_by');
        });
    }
};
