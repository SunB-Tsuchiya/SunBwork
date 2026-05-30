<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_jobs', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->after('client_id');
            $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
            $table->index('company_id');
        });

        // バックフィル: client.company_id 経由で補完
        DB::statement('
            UPDATE project_jobs pj
            JOIN clients c ON pj.client_id = c.id
            SET pj.company_id = c.company_id
            WHERE pj.company_id IS NULL
        ');
    }

    public function down(): void
    {
        Schema::table('project_jobs', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropIndex(['company_id']);
            $table->dropColumn('company_id');
        });
    }
};
