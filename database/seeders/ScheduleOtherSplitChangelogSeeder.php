<?php

namespace Database\Seeders;

use App\Models\Changelog;
use Illuminate\Database\Seeder;

class ScheduleOtherSplitChangelogSeeder extends Seeder
{
    public function run(): void
    {
        Changelog::updateOrCreate(['version' => 'schedule-other-20261029'], [
            'title' => '予定の「そのほか」を業務と私用に分けて試算',
            'released_at' => '2026-10-29',
            'summary' => '既存の「そのほか」を業務として扱い、新しい試算では通常の時間点の0.9倍で集計します。私用は本人が種類を変更して集計から除外できます。',
            'body' => '<p>予定の種類を「そのほか（業務）」「そのほか（私用）」に分けました。新しい試算では、業務は非公開でも時間と点数を数え、私用は対象外です。従来の「そのほか」は業務になりますので、私用の予定は種類を変更してください。「作業（予定）」の新規選択は終了しました。</p>',
            'design_files' => ['z_instructions/EVALUATION_SCORE_PLAN1.md'],
            'claude_notes' => 'Codex。既存otherのIDと旧式係数を維持して改名、personal_other追加。V3業務係数0.9、私用はイベント/紐付き記録とも除外。既存イベント行の一括書換えなし。情報出版SuperAdminの評価試用境界を維持。',
        ]);
    }
}
