<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meeting_definitions', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->after('id');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });

        // バックフィル: メンバーの company_id から推定する
        DB::statement("
            UPDATE meeting_definitions md
            JOIN (
                SELECT mdm.meeting_definition_id, u.company_id
                FROM meeting_definition_members mdm
                JOIN users u ON u.id = mdm.user_id
                WHERE u.company_id IS NOT NULL
                GROUP BY mdm.meeting_definition_id, u.company_id
            ) sub ON sub.meeting_definition_id = md.id
            SET md.company_id = sub.company_id
            WHERE md.company_id IS NULL
        ");

        // バックフィルで埋まらない場合は作成者の company_id で補完
        DB::statement("
            UPDATE meeting_definitions md
            JOIN users u ON u.id = md.created_by
            SET md.company_id = u.company_id
            WHERE md.company_id IS NULL AND u.company_id IS NOT NULL
        ");
    }

    public function down(): void
    {
        Schema::table('meeting_definitions', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });
    }
};
