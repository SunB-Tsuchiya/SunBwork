<?php

namespace App\Support\SunA\GeneralAffairs\Nencho;

/**
 * 国税庁 申告書 XML 定義書（令和8年10月版・版7）の読み込み。
 * 定義は resources/nta/r8_v7/NTAAPP00x.json（z_instructions/nencho_nta/json から複製。年度が変わったら差し替える）。
 * 項目のパス（階層のラベルを「/」でつないだもの）からタグ名と制約（型・桁数・文字種・コード値）を引く。
 */
class NtaDefinition
{
    private static array $cache = [];

    /** @return array{tags: array<string, array>, byPath: array<string, string>} */
    private static function load(string $form): array
    {
        if (isset(self::$cache[$form])) {
            return self::$cache[$form];
        }
        $file = resource_path("nta/r8_v7/{$form}.json");
        $data = json_decode(file_get_contents($file), true);
        $stack = [];
        $tags = [];
        $byPath = [];
        foreach ($data['items'] as $it) {
            $lv = $it['level'];
            $stack[$lv] = trim(preg_split('/\R/u', (string) $it['label'])[0]);
            foreach (array_keys($stack) as $k) {
                if ($k > $lv) {
                    unset($stack[$k]);
                }
            }
            if (! $it['tag'] || $lv < 3) {
                continue;
            }
            ksort($stack);
            $path = implode('/', array_filter($stack, fn ($v, $k) => $k >= 2, ARRAY_FILTER_USE_BOTH));
            $tags[$it['tag']] = ['path' => $path] + $it;
            $byPath[$path] = $it['tag'];
        }

        return self::$cache[$form] = ['tags' => $tags, 'byPath' => $byPath];
    }

    public static function tag(string $form, string $path): ?string
    {
        return self::load($form)['byPath'][$path] ?? null;
    }

    public static function item(string $form, string $tag): ?array
    {
        return self::load($form)['tags'][$tag] ?? null;
    }

    public static function allTags(string $form): array
    {
        return array_keys(self::load($form)['tags']);
    }

    /**
     * 項目の制約を、定義書の「型」と「備考」から読み取る。
     * @return array{type: string, len?: array, charset?: string, digits?: array, range?: array, codes?: int[]}
     */
    public static function constraint(array $item): array
    {
        $note = (string) ($item['note'] ?? '');
        $isInt = str_starts_with((string) $item['type'], 'integer');
        $c = ['type' => $isInt ? 'integer' : 'string'];

        if (! $isInt && preg_match('/(\d+)(?:～(\d+))?文字（(全角カナ|全角|半角数字|全半角|半角英数字|半角[^）]*)）/u', $note, $m)) {
            $c['len'] = [(int) $m[1], (int) (($m[2] ?? '') !== '' ? $m[2] : $m[1])];
            $c['charset'] = $m[3];
        }
        if ($isInt) {
            if (preg_match('/数[値字](\d+)(?:～(\d+))?桁/u', $note, $m)) {
                $c['digits'] = [(int) $m[1], (int) (($m[2] ?? '') !== '' ? $m[2] : $m[1])];
            } elseif (preg_match('/(-?\d+)～(-?\d+)/u', $note, $m) && ! str_contains($note, ':')) {
                $c['range'] = [(int) $m[1], (int) $m[2]];
            } elseif (preg_match_all('/(?<![\d～])(\d{1,2})\s*[:：.]/u', $note, $m) && $m[1]) {
                $c['codes'] = array_map('intval', $m[1]);
            }
            if (preg_match('/(-?\d+)～(-?\d+)/u', $note, $m) && ! isset($c['digits']) && ! isset($c['codes'])) {
                $c['range'] = [(int) $m[1], (int) $m[2]];
            }
        }

        return $c;
    }
}
