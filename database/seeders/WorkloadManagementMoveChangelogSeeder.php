<?php

namespace Database\Seeders;

use App\Models\Changelog;
use Illuminate\Database\Seeder;

class WorkloadManagementMoveChangelogSeeder extends Seeder
{
    public function run(): void
    {
        Changelog::updateOrCreate(['version' => 'workload-management-20261030'], [
            'title' => '管理者・リーダーの作業量分析を新しい分析画面へ統一',
            'released_at' => '2026-10-30',
            'summary' => '新しい作業量分析を各管理メニューから利用できるようにし、役割に合う画面と戻り先へ統一します。',
            'body' => '<p>作業実績の内訳・詳しい分析・ガイドを、各役割の作業量分析へまとめます。管理者は自社、リーダーは所属ユニットの対象者を確認できます。「作業記録の試用」のメニューは外し、既存記録は保持します。点数式や一般社員への公開範囲は変更しません。</p>',
            'design_files' => ['z_instructions/WORKLOAD_MANAGEMENT_MOVE_PLAN1.md'],
            'claude_notes' => 'Codex。閲覧専用の認可と会社/部署/ユニットスコープを入力試用権限から分離。各role prefixのGET API、AppLayoutと役割色、既存V3/V2比較・秘匿・アーカイブを維持。migrationなし。別途許可前のデプロイ禁止。',
        ]);
    }
}
