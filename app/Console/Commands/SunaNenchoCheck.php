<?php

namespace App\Console\Commands;

use App\Models\SunA\GeneralAffairs\Nencho\Submission;
use App\Support\SunA\GeneralAffairs\Nencho\NtaConformance;
use App\Support\SunA\GeneralAffairs\Nencho\NtaXmlMapper;
use Illuminate\Console\Command;

/**
 * 年末調整の保存データが、国税庁 XML 定義書の形式（型・桁数・文字種・コード値）に適合しているかを検査する。読み取り専用（データは変更しない）。
 */
class SunaNenchoCheck extends Command
{
    protected $signature = 'suna:nencho-check {submission? : 提出物の ID（省略時は全件）}';

    protected $description = '年末調整の保存データが国税庁 XML 定義書の形式に適合しているか検査する（読み取り専用）';

    public function handle(): int
    {
        $ids = $this->argument('submission') ? [(int) $this->argument('submission')] : Submission::pluck('id')->all();
        if (! $ids) {
            $this->warn('提出物がありません。');

            return self::SUCCESS;
        }
        $failed = false;
        foreach ($ids as $id) {
            $s = Submission::find($id);
            if (! $s) {
                $this->error("提出物 {$id} が見つかりません。");
                continue;
            }
            $r = NtaConformance::check(NtaXmlMapper::map($s));
            $this->info("提出物 {$s->id}（{$s->user_name}・{$s->campaign->title}・{$s->status}）: 検査 {$r['checked']} 項目 / 空タグ {$r['empty']} / 不適合 " . count($r['ng']) . ' / 定義書にない項目 ' . count($r['unknown']));
            foreach ($r['byForm'] as $form => $x) {
                $this->line(sprintf('  %s: 検査 %d 項目・不適合 %d・空タグ %d', $form, $x['checked'], $x['ng'], $x['empty']));
            }
            foreach ($r['ng'] as $n) {
                $this->error("  NG {$n['form']} {$n['tag']} {$n['path']}" . ($n['row'] ? "［{$n['row']}］" : '') . ' = ' . json_encode($n['value'], JSON_UNESCAPED_UNICODE) . ' → ' . $n['problem']);
            }
            foreach ($r['unknown'] as $u) {
                $this->error("  定義書にない項目: {$u}");
            }
            $failed = $failed || $r['ng'] || $r['unknown'];
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
