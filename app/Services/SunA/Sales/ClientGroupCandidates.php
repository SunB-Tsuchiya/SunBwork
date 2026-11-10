<?php

namespace App\Services\SunA\Sales;

use App\Models\SunA\Sales\ClientGroupMember;
use App\Models\SunA\Sales\Fact;
use App\Services\SalesAnalysis\ClientNameNormalizer;

/**
 * 名寄せの「統合の候補」（SUNASALES_PLAN1.md §17）。名前の前後の法人格を取り除いて同じ名前になる、未所属の得意先のまとまり。
 * 候補を出すだけで、統合は必ず人が確定する。括弧内の部署名など、法人格以外の違いがある名称は候補にしない。
 * 会社のコンテキスト（SunaCompanyContext）が必要。
 */
class ClientGroupCandidates
{
    /** 法人格の語（正規化後の表記: 半角括弧）。名前の前後にあるものを取り除く。必要になれば、ここに足す */
    public const CORPORATE_FORMS = [
        '株式会社', '有限会社', '合資会社', '合名会社', '合同会社',
        '学校法人', '医療法人', '社会福祉法人', '宗教法人', '特定非営利活動法人', 'NPO法人', '国立大学法人', '公立大学法人', '独立行政法人', '地方独立行政法人',
        '一般財団法人', '公益財団法人', '財団法人', '一般社団法人', '公益社団法人', '社団法人', '協同組合',
        '(株)', '(有)', '(資)', '(名)', '(同)', '(学)', '(医)', '(福)', '(宗)', '(特非)', '(一財)', '(公財)', '(財)', '(一社)', '(公社)', '(社)', '(協)', '(独)',
    ];

    /** 1文字にまとまった法人格の記号 → 通常の表記 */
    private const SYMBOLS = ['㈱' => '(株)', '㈲' => '(有)', '㈳' => '(社)', '㈶' => '(財)', '㈻' => '(学)', '㈼' => '(監)', '㈴' => '(名)', '㈵' => '(特)', '㈷' => '(祝)', '㈸' => '(労)', '㈹' => '(代)'];

    /**
     * 法人格を取り除いた名前（比較用）。除いたあとが2文字未満なら null（候補にしない）。
     */
    public static function strip(string $name): ?string
    {
        $text = ClientNameNormalizer::normalize(strtr($name, self::SYMBOLS));
        $forms = array_map(fn ($f) => ClientNameNormalizer::normalize($f), self::CORPORATE_FORMS);
        // 長い語から先に取り除く（「一般財団法人」を「財団法人」より先に）
        usort($forms, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        do {
            $before = $text;
            foreach ($forms as $form) {
                if (str_starts_with($text, $form)) {
                    $text = mb_substr($text, mb_strlen($form));
                }
                if ($text !== '' && str_ends_with($text, $form)) {
                    $text = mb_substr($text, 0, mb_strlen($text) - mb_strlen($form));
                }
            }
        } while ($text !== $before);

        return mb_strlen($text) >= 2 ? $text : null;
    }

    /** 候補を探した対象の数（まだどのグループにも入っていない得意先の数）。画面で「N社を調べた」と出すのに使う */
    public function countChecked(): int
    {
        $grouped = ClientGroupMember::query()->pluck('client_key')->all();

        return Fact::query()->when($grouped !== [], fn ($q) => $q->whereNotIn('client_key', $grouped))->distinct()->count('client_key');
    }

    /**
     * 統合の候補（まとまりごと。まとまりの中は累計売上の多い順、まとまりも先頭の売上の多い順）。
     *
     * @return array<int, array{clients: array<int, array{client_key:string, name:string, total_amount:float, last_year:int}>}>
     */
    public function build(): array
    {
        $grouped = ClientGroupMember::query()->pluck('client_key')->all();
        $rows = Fact::query()
            ->selectRaw('client_key, MAX(client_name) as name, SUM(amount) as total, MAX(sales_year) as last_year')
            ->when($grouped !== [], fn ($q) => $q->whereNotIn('client_key', $grouped))
            ->groupBy('client_key')->get();

        $clusters = [];
        foreach ($rows as $r) {
            $key = self::strip((string) $r->name);
            if ($key === null) {
                continue;
            }
            $clusters[$key][] = ['client_key' => (string) $r->client_key, 'name' => (string) $r->name, 'total_amount' => (float) $r->total, 'last_year' => (int) $r->last_year];
        }

        $out = [];
        foreach ($clusters as $clients) {
            if (count($clients) < 2) {
                continue;
            }
            usort($clients, fn ($a, $b) => $b['total_amount'] <=> $a['total_amount'] ?: strcmp($a['client_key'], $b['client_key']));
            $out[] = ['clients' => $clients];
        }
        usort($out, fn ($a, $b) => $b['clients'][0]['total_amount'] <=> $a['clients'][0]['total_amount']);

        return $out;
    }
}
