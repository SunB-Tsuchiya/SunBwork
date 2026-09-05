<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 会社別データ分離（2026-09-05）。これまでの売上分析データは全てサン・ブレーンのものなので、
// 既存行のcompany_idを一括でサン・ブレーンのIDへ後方補完する。companies（通常DB）はこの
// migrationがsales接続を対象とするため参照だけ通常DB接続（mysql）を明示して行う。
// company_type='sunbrain'ではなくcode='SUNBRAIN'で判定する（company_typeはCompanySeeder経由の
// 再構築だと'general'のままになる環境があり確実でないため、codeの方が一貫して設定されている）。
return new class extends Migration
{
    public function up(): void
    {
        $sunbrainCompanyId = DB::connection('mysql')->table('companies')
            ->where('code', 'SUNBRAIN')
            ->value('id');

        if ($sunbrainCompanyId === null) {
            // ローカル未セットアップ環境等でサン・ブレーンが無い場合は何もしない
            return;
        }

        DB::connection('sales')->table('sales_imports')->whereNull('company_id')->update(['company_id' => $sunbrainCompanyId]);
        DB::connection('sales')->table('sales_active_months')->whereNull('company_id')->update(['company_id' => $sunbrainCompanyId]);
        DB::connection('sales')->table('sales_client_groups')->whereNull('company_id')->update(['company_id' => $sunbrainCompanyId]);
        DB::connection('sales')->table('sales_client_group_members')->whereNull('company_id')->update(['company_id' => $sunbrainCompanyId]);
    }

    public function down(): void
    {
        // データ後方補完のため、down()では何もしない（company_id列自体は各create/addのdown()で削除される）
    }
};
