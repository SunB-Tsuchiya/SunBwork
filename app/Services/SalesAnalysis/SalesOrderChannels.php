<?php

namespace App\Services\SalesAnalysis;

use App\Models\Company;
use Illuminate\Support\Facades\Cache;

/**
 * Phase 20: サン・ブレーンの受注経路（サンエー印刷経由 / 独自受注）。
 *
 * 対象は会社コード SUNBRAIN（サン・ブレーン）だけ。他社は常に standard のみを持ち、
 * 経路UI・経路フィルタそのものを表示しない。会社コードをハードコードで散らばらせず、
 * 「経路対応会社かどうか」の判定をこのクラスへ集約する。
 */
class SalesOrderChannels
{
    public const STANDARD = 'standard';

    public const DIRECT = 'direct';

    /** APIフィルタ専用。DBには保存しない（standard+directの合算を表す） */
    public const ALL = 'all';

    private const SUNBRAIN_CODE = 'SUNBRAIN';

    private const LABELS = [
        self::STANDARD => 'サンエー印刷経由',
        self::DIRECT => '独自受注',
    ];

    public static function label(string $channel): string
    {
        return self::LABELS[$channel] ?? $channel;
    }

    /** @return array<string, string> key => label */
    public static function labels(): array
    {
        return self::LABELS;
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return [self::STANDARD, self::DIRECT];
    }

    public static function isValidChannel(string $channel): bool
    {
        return in_array($channel, self::values(), true);
    }

    /** サン・ブレーンだけが複数経路の区別を持つ。それ以外の会社は常にstandard固定。 */
    public static function supportsChannelsFor(int $companyId): bool
    {
        return Cache::remember("sales_order_channels.supports.{$companyId}", 60, function () use ($companyId) {
            return Company::where('id', $companyId)->where('code', self::SUNBRAIN_CODE)->exists();
        });
    }

    /**
     * ファイル名（basename、拡張子込み）を厳格な命名規則で解析し、対象部署ラベル・期間種別・
     * 年・月・終了月・受注経路を再構築する。フロント側の自動入力は表示補助に過ぎず、
     * サン・ブレーンではこの解析結果をサーバー側の正とする（PLAN Phase20 20.3）。
     *
     * 許可形式:
     *   企画_2025年.xlsx                 -> annual / standard
     *   企画_2025年_独自.xlsx            -> annual / direct
     *   制作_2026年08月.xlsx             -> monthly / standard
     *   制作_2026年08月_独自.xlsx        -> monthly / direct
     *   オンデマンド_2026年01-06月.xlsx  -> range / standard
     *   オンデマンド_2026年01-06月_独自.xlsx -> range / direct
     *
     * @param  array<int, string>  $departmentLabels  会社に登録されている部署ラベル一覧
     * @return array{department_label:string, source_type:string, source_year:int, source_month:?int, source_month_end:?int, order_channel:string}|null
     *              規則外のファイル名はnullを返す（呼び出し側でblockingエラーにする）
     */
    public static function parseFilename(string $basename, array $departmentLabels): ?array
    {
        $departmentLabels = array_values(array_filter($departmentLabels, fn ($l) => $l !== ''));
        if (empty($departmentLabels)) {
            return null;
        }

        // 部署ラベルが他ラベルの接頭辞になっているケース（将来の追加に備え）に対応するため、
        // 長い順に試す
        $labels = $departmentLabels;
        usort($labels, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        $labelPattern = implode('|', array_map(fn ($l) => preg_quote($l, '/'), $labels));

        $pattern = '/^(' . $labelPattern . ')_(\d{4})年(?:(\d{1,2})-(\d{1,2})月|(\d{1,2})月)?(_独自)?\.xlsx$/u';

        if (! preg_match($pattern, $basename, $m)) {
            return null;
        }

        $departmentLabel = $m[1];
        $year = (int) $m[2];
        $rangeStart = isset($m[3]) && $m[3] !== '' ? (int) $m[3] : null;
        $rangeEnd = isset($m[4]) && $m[4] !== '' ? (int) $m[4] : null;
        $singleMonth = isset($m[5]) && $m[5] !== '' ? (int) $m[5] : null;
        $isDirect = isset($m[6]) && $m[6] !== '';

        // 正規表現は\d{1,2}のため1〜99を受理してしまう。実在する月（1〜12）かつ
        // 範囲は開始<=終了であることを確認する（Codexレビュー指摘: 不正な月がフォーム値を
        // 上書きしてtargetMonths()に渡ると13〜99月のactive pointerが作られ得る）
        if ($rangeStart !== null && $rangeEnd !== null) {
            if ($rangeStart < 1 || $rangeStart > 12 || $rangeEnd < 1 || $rangeEnd > 12 || $rangeStart > $rangeEnd) {
                return null;
            }
            $sourceType = 'range';
            $sourceMonth = $rangeStart;
            $sourceMonthEnd = $rangeEnd;
        } elseif ($singleMonth !== null) {
            if ($singleMonth < 1 || $singleMonth > 12) {
                return null;
            }
            $sourceType = 'monthly';
            $sourceMonth = $singleMonth;
            $sourceMonthEnd = null;
        } else {
            $sourceType = 'annual';
            $sourceMonth = null;
            $sourceMonthEnd = null;
        }

        return [
            'department_label' => $departmentLabel,
            'source_type' => $sourceType,
            'source_year' => $year,
            'source_month' => $sourceMonth,
            'source_month_end' => $sourceMonthEnd,
            'order_channel' => $isDirect ? self::DIRECT : self::STANDARD,
        ];
    }
}
