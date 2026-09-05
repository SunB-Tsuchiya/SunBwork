<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 会社別データ分離（2026-09-05）。得意先統合グループを会社単位で分ける。
// sales_client_group_members.client_nameは全社共通で一意だったが、これだと別会社に
// 同名クライアントがいると壊れるため、company_idを合わせた複合一意へ変更する
// （company_idはsales_client_groupsから非正規化して持たせる。単純化のため）。
return new class extends Migration
{
    protected $connection = 'sales';

    public function up(): void
    {
        Schema::connection('sales')->table('sales_client_groups', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->after('id');
        });

        Schema::connection('sales')->table('sales_client_group_members', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->after('sales_client_group_id');
        });

        Schema::connection('sales')->table('sales_client_group_members', function (Blueprint $table) {
            $table->dropUnique(['client_name']);
            $table->unique(['company_id', 'client_name']);
        });
    }

    public function down(): void
    {
        Schema::connection('sales')->table('sales_client_group_members', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'client_name']);
            $table->unique(['client_name']);
            $table->dropColumn('company_id');
        });

        Schema::connection('sales')->table('sales_client_groups', function (Blueprint $table) {
            $table->dropColumn('company_id');
        });
    }
};
