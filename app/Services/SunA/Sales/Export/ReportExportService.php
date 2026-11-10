<?php

namespace App\Services\SunA\Sales\Export;

use App\Services\SunA\Sales\Analysis\AnnualAnalysisService;
use App\Services\SunA\Sales\Analysis\AttentionService;
use App\Services\SunA\Sales\Analysis\ClientAnalysisService;
use App\Services\SunA\Sales\Analysis\CompareService;
use App\Services\SunA\Sales\Analysis\DepartmentComparisonService;
use App\Services\SunA\Sales\Analysis\DepartmentFilter;
use App\Services\SunA\Sales\Analysis\SameMonthService;

/**
 * 売上レポートのExcel出力（SUNASALES_PLAN1.md §19）。**画面と同じ集計サービスの結果**をそのままExcelにする（別の集計を書かない）。
 * 各メソッドは ['filename' => 出力ファイル名, 'workbook' => ReportWorkbook] を返す。会社のコンテキスト（SunaCompanyContext）が必要。
 */
class ReportExportService
{
    private const MONTHS = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];

    public function __construct(
        private AnnualAnalysisService $annual,
        private SameMonthService $sameMonth,
        private DepartmentComparisonService $departmentComparison,
        private CompareService $compare,
        private ClientAnalysisService $client,
        private DepartmentFilter $departments,
        private AttentionService $attention,
    ) {
    }

    /** @return array{filename:string, workbook:ReportWorkbook} */
    public function annual(int $year, string $dept): array
    {
        $s = $this->annual->summary($year, $dept, 5);
        if (! ($s['has_data'] ?? false)) {
            throw new ReportExportException("{$year}年の売上データがありません。");
        }
        $deptLabel = $this->deptLabel($dept);
        $conditions = ["年: {$year}年", "部門: {$deptLabel}", '集計期間: ' . $s['period']['label']];
        if ($s['prior']['available']) {
            $conditions[] = "前年との比較: {$s['prior']['year']}年の同じ月と比べています";
        } else {
            $conditions[] = '前年との比較: 前年のデータがないため、前年比・新規・終了は出していません';
        }
        $wb = $this->workbook('年次分析', $conditions);

        $k = $s['kpi'];
        $wb->sheet('サマリー')->table(
            [['項目', 'text', 18], ['当期間', 'int'], ['前年同期間', 'int'], ['差', 'int'], ['増減率', 'pct']],
            array_map(fn ($label, $m) => [$label, $m['value'], $m['prior'], $m['diff'], $m['rate']], ['売上（円）', '受注件数', '平均受注単価（円）', '取引社数（名寄せ後）'], [$k['amount'], $k['order_count'], $k['avg_order_price'], $k['client_count']]),
        );

        $wb->sheet('月別売上')->note('登録のない月は空欄です（0円ではありません）。')->table(
            array_merge([['年', 'text', 10]], array_map(fn ($m) => ["{$m}月", 'int', 14], self::MONTHS)),
            array_map(fn ($y) => array_merge(["{$y['year']}年"], array_map(fn ($m) => $y['months'][$m] ?? null, self::MONTHS)), $s['monthly']),
        );

        $wb->sheet('部門別')->note('部門の選択に関わらず、全部門の内訳です。営業第三部（全体）は課・直轄の合計です。')->table(
            [['部門', 'text', 26], ['売上', 'int'], ['前年同期間', 'int'], ['差', 'int'], ['増減率', 'pct'], ['構成比', 'pct'], ['取引社数', 'int']],
            array_map(fn ($d) => [$this->indent($d['label'], $d['level']), $d['amount'], $d['prior_amount'], $d['diff'], $d['rate'], $d['share_pct'], $d['client_count']], $s['by_department']),
        );

        $clients = $this->annual->clientRows($year, $dept);
        $rows = array_values(array_filter($clients['rows'], fn ($r) => $r['amount'] > 0 || ($r['status'] ?? null) === 'ended'));
        usort($rows, fn ($a, $b) => $b['amount'] <=> $a['amount'] ?: ($b['prior_amount'] ?? 0) <=> ($a['prior_amount'] ?? 0) ?: strcmp($a['label'], $b['label']));
        $wb->sheet('得意先別')->note('名寄せ後の得意先です。売上の多い順（終了した得意先は末尾）。')->table(
            [['得意先', 'text', 34], ['売上', 'int'], ['前年同期間', 'int'], ['差', 'int'], ['増減率', 'pct'], ['構成比', 'pct'], ['区分', 'text', 10]],
            array_map(fn ($r) => [$r['label'], $r['amount'], $r['prior_amount'], $r['diff'], $r['rate'], $r['share_pct'], $this->status($r['status'] ?? null)], $rows),
        );

        return ['filename' => $this->filename('年次分析', ["{$year}年", $deptLabel]), 'workbook' => $wb];
    }

    /** 注目ポイント。新規・休眠は、画面の上位件数ではなく全件を出す */
    public function attention(int $year, string $dept, int $dormantMonths): array
    {
        $s = $this->attention->summary($year, $dept, $dormantMonths, null);
        if (! ($s['has_data'] ?? false)) {
            throw new ReportExportException("{$year}年の売上データがありません。");
        }
        $deptLabel = $this->deptLabel($dept);
        $d = $s['dormant'];
        $conditions = ["年: {$year}年", "部門: {$deptLabel}", '集計期間: ' . $s['period']['label'], "休眠の期間: {$dormantMonths}か月"];
        $conditions[] = $s['comparison_available'] ? "前年との比較: {$s['prior']['year']}年の同じ月と比べています" : '前年との比較: 前年のデータがないため、増加・減少・新規は出していません';
        $wb = $this->workbook('注目ポイント', $conditions);

        $diffCols = [['得意先', 'text', 34], ['売上', 'int'], ['前年同期間', 'int'], ['差', 'int'], ['増減率', 'pct']];
        $diffRow = fn ($r) => [$r['label'], $r['amount'], $r['prior_amount'], $r['diff'], $r['rate']];
        $wb->sheet('大きく増えた得意先')->note('前年との差額が大きい順の上位10社です。')->table($diffCols, array_map($diffRow, $s['increase']['rows'] ?? []));
        $wb->sheet('大きく減った得意先')->note('前年との差額が大きい（減った額が大きい）順の上位10社です。')->table($diffCols, array_map($diffRow, $s['decrease']['rows'] ?? []));
        $wb->sheet('新規の得意先')->note('前年に売上がなく、今の期間に売上がある得意先です（今年の売上の大きい順・全件）。')->table(
            [['得意先', 'text', 34], ['売上', 'int']],
            array_map(fn ($r) => [$r['label'], $r['amount']], $s['new']['rows'] ?? []),
        );
        $note = $d['available']
            ? "直近の登録月（{$d['latest_label']}）から数えて{$d['months']}か月（{$d['silent_label']}）売上がなく、その前の12か月（{$d['prior_label']}）には売上があった得意先です（全件）。年・部門の選択のうち、部門は前の12か月の計上部門で絞っています。"
            : $d['message'];
        $wb->sheet('休眠の得意先')->note($note)->table(
            [['得意先', 'text', 34], ['前の12か月の売上', 'int'], ['最後に売上があった月', 'text', 22], ['売上のない期間（か月）', 'int']],
            array_map(fn ($r) => [$r['label'], $r['prior_amount'], $r['last_label'], $r['silent_months']], $d['rows']),
        );

        return ['filename' => $this->filename('注目ポイント', ["{$year}年", $deptLabel, "休眠{$dormantMonths}か月"]), 'workbook' => $wb];
    }

    /** @return array{filename:string, workbook:ReportWorkbook} */
    public function sameMonth(int $month, string $dept): array
    {
        $s = $this->sameMonth->summary($month, $dept);
        if (! ($s['has_data'] ?? false)) {
            throw new ReportExportException("{$month}月は、どの年にも売上が登録されていません。");
        }
        $deptLabel = $this->deptLabel($dept);
        $wb = $this->workbook('同月比較', ["月: {$month}月", "部門: {$deptLabel}", '比べる年: ' . implode('・', array_column($s['years'], 'year')) . '年（その月が未登録の年は含めません）']);

        $wb->sheet('年別')->note('前年差・前年比は、前の年にもその月の登録があるときだけ出しています。')->table(
            [['年', 'text', 14], ['売上', 'int'], ['前年差', 'int'], ['前年比', 'pct'], ['受注件数', 'int'], ['平均受注単価', 'int'], ['取引社数', 'int']],
            array_map(fn ($y) => ["{$y['year']}年{$month}月", $y['amount'], $y['diff'], $y['rate'], $y['order_count'], $y['avg_order_price'], $y['client_count']], $s['years']),
        );

        $matrix = $s['by_department'];
        $wb->sheet('部門別×年')->note('部門の選択に関わらず、全部門の内訳です。')->table(
            array_merge([['部門', 'text', 26]], array_map(fn ($y) => ["{$y}年", 'int', 16], $matrix['years'])),
            array_map(fn ($r) => array_merge([$this->indent($r['label'], $r['level'])], array_map(fn ($y) => $r['amounts'][$y] ?? null, $matrix['years'])), $matrix['rows']),
        );

        return ['filename' => $this->filename('同月比較', ["{$month}月", $deptLabel]), 'workbook' => $wb];
    }

    /** @return array{filename:string, workbook:ReportWorkbook} */
    public function department(string $basis): array
    {
        $s = $this->departmentComparison->summary($basis);
        if (! ($s['has_data'] ?? false)) {
            throw new ReportExportException('売上データがありません。');
        }
        $wb = $this->workbook('部門比較', ['集計の期間: ' . $s['label'], $basis === 'year' ? '通年（1〜12月）で集計。途中までの登録の年は、その年の登録済みの月だけの合計です' : '最新年の登録済みの月に、全ての年を揃えて集計']);
        $partial = array_values(array_filter($s['years'], fn ($y) => $y['is_partial'] || ! $y['has_data']));
        $notes = array_map(fn ($y) => "{$y['year']}年: " . ($y['has_data'] ? ($basis === 'year' ? '途中（' : '一部の月が未登録（登録済み: ') . $y['partial_label'] . '）' : '未登録（この期間の売上の登録がない）'), $partial);

        $header = fn () => array_merge([['部門', 'text', 26]], array_map(fn ($y) => ["{$y['year']}年", 'int', 16], $s['years']));
        $table = function (string $sheet, string $field, string $type) use ($wb, $s, $notes) {
            $wb->sheet($sheet);
            foreach ($notes as $n) {
                $wb->note($n);
            }
            $wb->table(
                array_merge([['部門', 'text', 26]], array_map(fn ($y) => ["{$y['year']}年", $type, 16], $s['years'])),
                array_map(fn ($r) => array_merge([$this->indent($r['label'], $r['level'])], array_map(fn ($y) => $r[$field][$y['year']] ?? null, $s['years'])), $s['rows']),
            );
        };
        $table('売上', 'amounts', 'int');
        $table('構成比', 'shares', 'pct');
        $table('前年比', 'yoy', 'pct');

        return ['filename' => $this->filename('部門比較', [$basis === 'year' ? '通年' : '同じ月まで']), 'workbook' => $wb];
    }

    /**
     * @param  array{year:int, month?:int}  $a
     * @param  array{year:int, month?:int}  $b
     * @return array{filename:string, workbook:ReportWorkbook}
     */
    public function compare(string $mode, array $a, array $b, string $dept): array
    {
        $s = $this->compare->summary($mode, $a, $b, $dept);
        if (! ($s['has_data'] ?? false)) {
            throw new ReportExportException($s['reason'] ?? '比べられません。');
        }
        $deptLabel = $this->deptLabel($dept);
        $wb = $this->workbook('左右比較', ['期間A: ' . $s['a']['label'], '期間B: ' . $s['b']['label'], "部門: {$deptLabel}", '差・増減率は、Aに対するB']);

        $k = $s['kpi'];
        $wb->sheet('サマリー')->table(
            [['項目', 'text', 22], ['A', 'int'], ['B', 'int'], ['差', 'int'], ['増減率', 'pct']],
            array_map(fn ($label, $m) => [$label, $m['a'], $m['b'], $m['diff'], $m['rate']], ['売上（円）', '受注件数', '平均受注単価（円）', '取引社数（名寄せ後）'], [$k['amount'], $k['order_count'], $k['avg_order_price'], $k['client_count']]),
        );
        $wb->sheet('部門別')->note('部門の選択に関わらず、全部門の内訳です。')->table(
            [['部門', 'text', 26], ['A', 'int'], ['B', 'int'], ['差', 'int'], ['増減率', 'pct']],
            array_map(fn ($d) => [$this->indent($d['label'], $d['level']), $d['a'], $d['b'], $d['diff'], $d['rate']], $s['by_department']),
        );

        $c = $s['clients'];
        $rows = array_map(fn ($r) => [$r['label'], $r['a'], $r['b'], $r['diff'], $r['rate'], $this->status($r['status'])], $c['top']);
        if ($c['others']) {
            $o = $c['others'];
            $rows[] = ["その他（{$o['count']}社）", $o['a'], $o['b'], $o['diff'], $o['rate'], ''];
        }
        $wb->sheet('得意先別')->note("Bの売上の多い順に上位15社（全{$c['total_clients']}社）。名寄せ後です。")->table(
            [['得意先', 'text', 34], ['A', 'int'], ['B', 'int'], ['差', 'int'], ['増減率', 'pct'], ['区分', 'text', 10]],
            $rows,
        );
        if ($c['ended']) {
            $wb->sheet('終了した得意先')->note("Aに売上があり、Bにない得意先（Aの売上の多い順に上位10社。全{$c['ended_total']}社）。")->table(
                [['得意先', 'text', 34], ['A', 'int'], ['B', 'int']],
                array_map(fn ($r) => [$r['label'], $r['a'], $r['b']], $c['ended']),
            );
        }

        $tag = $mode === 'year' ? ["{$a['year']}年", "{$b['year']}年"] : ["{$a['year']}年{$a['month']}月", "{$b['year']}年{$b['month']}月"];

        return ['filename' => $this->filename('左右比較', [$tag[0] . '対' . $tag[1], $deptLabel]), 'workbook' => $wb];
    }

    /** @return array{filename:string, workbook:ReportWorkbook} */
    public function client(string $clientKey): array
    {
        $d = $this->client->detail($clientKey);
        if ($d === null) {
            throw new ReportExportException('得意先が見つかりません。');
        }
        $conditions = ['得意先: ' . $d['name'], "期間: {$d['first_year']}年〜{$d['last_year']}年"];
        if ($d['members']) {
            $conditions[] = '名寄せ済み（' . count($d['members']) . '社をまとめて集計）: ' . implode('、', array_column($d['members'], 'name'));
        }
        $wb = $this->workbook('得意先分析', $conditions);

        $wb->sheet('年別')->note('前年差・前年比は、その年の登録済みの月を、前年の同じ月と比べています（前年にデータがない最初の年は出していません）。')->table(
            [['年', 'text', 10], ['売上', 'int'], ['前年差', 'int'], ['前年比', 'pct'], ['受注件数', 'int'], ['平均受注単価', 'int'], ['登録月数', 'int', 12], ['担当部門', 'text', 28]],
            array_map(fn ($y) => ["{$y['year']}年", $y['amount'], $y['diff'], $y['rate'], $y['order_count'], $y['avg_order_price'], count($y['registered_months']), implode('・', $y['departments'])], $d['years']),
        );
        $wb->sheet('月別')->note('登録のない月は空欄です（0円ではありません）。')->table(
            array_merge([['年', 'text', 10]], array_map(fn ($m) => ["{$m}月", 'int', 14], self::MONTHS)),
            array_map(fn ($y) => array_merge(["{$y['year']}年"], array_map(fn ($m) => $y['months'][$m]['amount'] ?? null, self::MONTHS)), $d['years']),
        );
        $wb->sheet('担当部門の履歴')->table(
            [['年', 'text', 10], ['担当部門', 'text', 28], ['前の年から', 'text', 14]],
            array_map(fn ($h) => ["{$h['year']}年", $h['label'], $h['changed'] ? '変更あり' : ''], $d['department_history']),
        );

        return ['filename' => $this->filename('得意先分析', [$d['name']]), 'workbook' => $wb];
    }

    private function workbook(string $title, array $conditions): ReportWorkbook
    {
        return new ReportWorkbook("売上レポート {$title}", $conditions, now('Asia/Tokyo')->format('Y-m-d H:i'));
    }

    private function deptLabel(string $key): string
    {
        foreach ($this->departments->options() as $o) {
            if ($o['key'] === $key) {
                return $o['label'];
            }
        }

        return $key;
    }

    private function indent(string $label, int $level): string
    {
        return $level >= 1 ? '　└ ' . $label : $label;
    }

    private function status(?string $status): string
    {
        return match ($status) {
            'new' => '新規', 'ended' => '終了', 'continuing' => '継続', default => '',
        };
    }

    /** ファイル名: 「画面名_条件_日付.xlsx」。ファイル名に使えない文字は「_」にする */
    private function filename(string $screen, array $parts): string
    {
        $name = implode('_', array_merge([$screen], $parts, [now('Asia/Tokyo')->format('Ymd')]));
        $name = preg_replace('/[\\\\\/:*?"<>|\s（）()]+/u', '_', $name);

        return trim(mb_substr($name, 0, 100), '_') . '.xlsx';
    }
}
