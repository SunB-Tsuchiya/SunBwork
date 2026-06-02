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

        // バックフィル: メンバーの company_id から推定する（SQLite/MySQL 共通の Query Builder で実装）
        DB::table('meeting_definitions')
            ->whereNull('company_id')
            ->get(['id'])
            ->each(function ($def) {
                $companyId = DB::table('meeting_definition_members')
                    ->join('users', 'users.id', '=', 'meeting_definition_members.user_id')
                    ->where('meeting_definition_members.meeting_definition_id', $def->id)
                    ->whereNotNull('users.company_id')
                    ->value('users.company_id');

                if ($companyId) {
                    DB::table('meeting_definitions')
                        ->where('id', $def->id)
                        ->update(['company_id' => $companyId]);
                }
            });

        // バックフィルで埋まらない場合は作成者の company_id で補完
        DB::table('meeting_definitions')
            ->whereNull('company_id')
            ->get(['id', 'created_by'])
            ->each(function ($def) {
                $companyId = DB::table('users')
                    ->where('id', $def->created_by)
                    ->whereNotNull('company_id')
                    ->value('company_id');

                if ($companyId) {
                    DB::table('meeting_definitions')
                        ->where('id', $def->id)
                        ->update(['company_id' => $companyId]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('meeting_definitions', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });
    }
};
