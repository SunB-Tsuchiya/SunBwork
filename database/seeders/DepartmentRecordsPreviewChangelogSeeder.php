<?php

namespace Database\Seeders;

use App\Models\Changelog;
use Illuminate\Database\Seeder;

class DepartmentRecordsPreviewChangelogSeeder extends Seeder
{
    public function run(): void
    {
        Changelog::updateOrCreate(['version' => 'dept-records-preview-20261030'], [
            'title' => '部署の予定と作業記録の表示を試用',
            'released_at' => '2026-10-30',
            'summary' => '情報出版Super Admin限定で、部署の予定と点数を表示しない作業記録を切り替えて確認できます。',
            'body' => '<p>部署の作業に「部署の作業予定」「みんなの作業記録」を設けます。記録は今日と直近4営業日を対象に、仕事内容別の時間を表示します。本人だけに作業種類の未指定と、条件が確認できる場合の予定の空きを案内します。一般社員にはまだ公開しません。評価点・順位番号や他人の不足案内は表示せず、既存の採点式と共有予定は維持します。</p>',
            'design_files' => ['z_instructions/DEPARTMENT_RECORDS_PLAN1.md'],
            'claude_notes' => 'Codex。読取専用の専用JSON投影、既存INFO SuperAdmin試用guard。一般公開・DB移行なし。配備前に別作業との差分確認とSSH承認が必要。',
        ]);
    }
}
