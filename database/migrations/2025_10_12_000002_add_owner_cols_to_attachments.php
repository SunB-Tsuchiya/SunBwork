<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('attachments', function (Blueprint $table) {
            if (!Schema::hasColumn('attachments', 'owner_type')) {
                $table->string('owner_type', 100)->nullable()->after('path')->index();
            }
            if (!Schema::hasColumn('attachments', 'owner_id')) {
                $table->unsignedBigInteger('owner_id')->nullable()->after('owner_type')->index();
            }
            if (!Schema::hasColumn('attachments', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('owner_id');
            }
        });
    }

    public function down()
    {
        Schema::table('attachments', function (Blueprint $table) {
            if (Schema::hasColumn('attachments', 'created_by')) {
                $table->dropColumn('created_by');
            }
            if (Schema::hasColumn('attachments', 'owner_id')) {
                $table->dropColumn('owner_id');
            }
            if (Schema::hasColumn('attachments', 'owner_type')) {
                $table->dropColumn('owner_type');
            }
        });
    }
};
