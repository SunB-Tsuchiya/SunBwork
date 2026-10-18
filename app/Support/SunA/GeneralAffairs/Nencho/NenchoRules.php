<?php

namespace App\Support\SunA\GeneralAffairs\Nencho;

/**
 * 年末調整フォームの共通定数・入力整形。
 * 項目名・桁数・コード値の一次資料: z_instructions/nencho_nta/json/NTAAPP00x.json（国税庁 XML 定義書 令和8年10月版）
 */
class NenchoRules
{
    public const PREFECTURES = [
        '北海道', '青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県', '茨城県', '栃木県', '群馬県',
        '埼玉県', '千葉県', '東京都', '神奈川県', '新潟県', '富山県', '石川県', '福井県', '山梨県', '長野県',
        '岐阜県', '静岡県', '愛知県', '三重県', '滋賀県', '京都府', '大阪府', '兵庫県', '奈良県', '和歌山県',
        '鳥取県', '島根県', '岡山県', '広島県', '山口県', '徳島県', '香川県', '愛媛県', '高知県', '福岡県',
        '佐賀県', '長崎県', '熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県',
    ];

    /** 世帯主との続柄（NTAAPP001 世帯主 続柄 コード） */
    public const HOUSEHOLD_RELATIONS = [1 => '本人', 2 => '配偶者', 3 => '子', 4 => '親', 5 => '孫', 6 => '祖父母', 99 => 'その他'];

    /** 対象とする雇用区分（社員・契約社員。派遣・業務委託は対象外） */
    public const TARGET_EMPLOYMENT_TYPES = ['regular', 'contract'];

    public const EMPLOYMENT_LABELS = ['regular' => '社員', 'contract' => '契約社員', 'dispatch' => '派遣社員', 'outsource' => '業務委託'];


    // ---- 年末調整フォームのコード値（国税庁 XML 定義書 令和8年10月版 NTAAPP001/003/004 の「備考」が正） ----

    /** 配偶者有無 */
    public const SPOUSE_STATUS = [0 => 'いない', 1 => 'いる', 2 => 'いない（本年中に異動あり）'];

    /** 配偶者の種別 */
    public const SPOUSE_KINDS = [
        0 => '同一生計配偶者', 1 => '源泉控除対象配偶者', 2 => '同一生計配偶者かつ源泉控除対象配偶者', 3 => 'その他',
        4 => '退職手当等を有する配偶者', 5 => '同一生計配偶者かつ退職手当等を有する配偶者',
        6 => '源泉控除対象配偶者かつ退職手当等を有する配偶者', 7 => '同一生計配偶者かつ源泉控除対象配偶者かつ退職手当等を有する配偶者',
    ];

    /** 扶養親族の種別（決定 16: 区分の判定は行わず本人が選ぶ） */
    public const DEPENDENT_KINDS = [
        1 => '扶養親族（16歳以上）', 2 => '他の所得者が控除を受ける扶養親族等', 3 => '16歳未満の扶養親族',
        4 => '退職手当等を有する扶養親族', 5 => '扶養親族（16歳以上）かつ退職手当等を有する扶養親族',
        6 => '16歳未満の扶養親族かつ退職手当等を有する扶養親族', 7 => '特定親族', 8 => '退職手当等を有する特定親族',
        9 => 'それ以外の親族', 10 => '特定親族かつ退職手当等を有する扶養親族',
    ];

    /** 扶養親族の続柄 */
    public const DEPENDENT_RELATIONS = [3 => '子', 4 => '親', 5 => '孫', 6 => '祖父母', 7 => '曾祖父母', 99 => 'その他'];

    /** 控除を受ける他の所得者の続柄 */
    public const OTHER_EARNER_RELATIONS = [2 => '配偶者', 3 => '子', 4 => '親', 5 => '孫', 6 => '祖父母', 99 => 'その他'];

    public const ELDERLY_CLASSES = [0 => '非該当', 1 => '同居老親等', 2 => 'その他'];

    public const SPECIFIC_CLASSES = [0 => 'どちらも非該当', 1 => '特定扶養親族', 2 => '特定親族'];

    /** 障害者区分（本人: 0〜2 / 配偶者・扶養親族: 0〜3） */
    public const DISABILITY_CLASSES = [0 => '非該当', 1 => '一般の障害者', 2 => '特別障害者', 3 => '同居特別障害者'];

    public const SINGLE_PARENT_CLASSES = [0 => '非該当', 1 => '寡婦', 2 => 'ひとり親'];

    public const NONRESIDENT_REASONS = [1 => '「30歳以上70歳未満」以外', 2 => '留学', 3 => '障害者', 4 => '38万円以上の支払'];

    public const REMITTANCE_UNITS = [1 => '円', 2 => '米ドル', 3 => 'ユーロ', 4 => '英ポンド', 5 => 'スイスフラン', 6 => '豪ドル', 7 => 'カナダドル', 99 => 'その他'];

    /** 異動事由（種類別） */
    public const CHANGE_REASONS = [
        'spouse' => [1 => '結婚', 2 => '離婚', 4 => '死別', 5 => '就職', 6 => '離職', 99 => 'その他'],
        'dependent' => [1 => '結婚', 2 => '離婚', 3 => '出産', 4 => '死別', 5 => '就職', 6 => '離職', 99 => 'その他'],
        'disability' => [11 => '交付', 12 => '返納', 13 => '失効', 14 => '申請中', 99 => 'その他'],
        'single_parent' => [1 => '結婚', 2 => '離婚', 3 => '出産', 4 => '死別', 9 => '生死不明', 99 => 'その他'],
        'working_student' => [21 => '入学', 22 => '卒業', 99 => 'その他'],
    ];

    /** 保険の新旧区分 */
    public const NEW_OLD = [0 => '旧契約', 1 => '新契約'];

    /** 地震保険の区分 */
    public const QUAKE_CLASSES = [0 => '旧長期損害保険', 1 => '地震保険'];

    /**
     * 金額の整形。全角・カンマ・空白・「円」を取り除き、半角数字の整数にする。
     * 「450万」「450万円」のような万単位の入力も円に直す（例: 450万 → 4500000）。空は null。
     */
    public static function amount($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $text = mb_convert_kana((string) $value, 'ns');                  // 全角数字・全角スペース → 半角
        $text = str_replace([',', '，', '、', ' ', '円', '¥', '￥'], '', $text);
        $text = preg_replace('/[‐‑‒–—―−ーｰ]/u', '-', $text);            // 各種のハイフン・マイナスを半角「-」に
        if (preg_match('/^(-?\d+(?:\.\d+)?)万(\d*)$/u', $text, $m)) { // 例: 450万 / 450万5000
            return (int) round((float) $m[1] * 10000) + (int) ($m[2] ?? 0);
        }
        $digits = preg_replace('/[^\d\-]/', '', $text);

        return $digits === '' || $digits === '-' ? null : (int) $digits;
    }

    /**
     * 住所・氏名などの日本語テキストを、国の様式（全角）にそろえる。
     *  - 英数字・記号・空白・半角カナを全角にする（例: 3-7-7 → ３－７－７）
     *  - ハイフン類（‐ ‑ ‒ – — ― − など）を全角「－」にそろえる。数字の間の長音「ー」もハイフンとして扱う
     *  - 連続する空白は全角スペース 1 つにする
     */
    public static function zenkakuText(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }
        $text = preg_replace('/(?<=[0-9０-９])ー(?=[0-9０-９])/u', '-', $value);     // 3ー7 → 3-7
        $text = preg_replace('/[‐‑‒–—―−]/u', '-', $text);
        $text = mb_convert_kana($text, 'ASKV');                                         // 英数字・記号（ハイフンは全角「－」U+FF0D）・空白・半角カナ → 全角
        $text = preg_replace('/[\s　]+/u', '　', trim($text));

        return $text;
    }


    /**
     * 日付の整形。次の入力を「Y-m-d」にする。解釈できない場合は入力をそのまま返す（検証でエラーにする）。
     *  19770905 / 1977/09/05 / 1977-9-5 / 1977.9.5 / 1977年9月5日 / 昭和52年9月5日 / S52.9.5 / 令和元年5月1日
     */
    public static function date(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $v = trim(mb_convert_kana($value, 'ns'));
        if ($v === '') {
            return null;
        }
        $eras = ['明治' => 1867, 'M' => 1867, '大正' => 1911, 'T' => 1911, '昭和' => 1925, 'S' => 1925, '平成' => 1988, 'H' => 1988, '令和' => 2018, 'R' => 2018];
        $y = $m = $d = null;
        if (preg_match('/^(明治|大正|昭和|平成|令和|[MTSHR])\s*(元|\d{1,2})\s*[年.\/\-]\s*(\d{1,2})\s*[月.\/\-]\s*(\d{1,2})\s*日?$/iu', $v, $x)) {
            $era = mb_strlen($x[1]) === 1 ? strtoupper($x[1]) : $x[1];
            $y = $eras[$era] + ($x[2] === '元' ? 1 : (int) $x[2]);
            [$m, $d] = [(int) $x[3], (int) $x[4]];
        } elseif (preg_match('/^(\d{4})[\/\-.年\s]\s*(\d{1,2})[\/\-.月\s]\s*(\d{1,2})\s*日?$/u', $v, $x)) {
            [$y, $m, $d] = [(int) $x[1], (int) $x[2], (int) $x[3]];
        } elseif (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $v, $x)) {
            [$y, $m, $d] = [(int) $x[1], (int) $x[2], (int) $x[3]];
        }
        if ($y === null || ! checkdate($m, $d, $y)) {
            return $value;
        }

        return sprintf('%04d-%02d-%02d', $y, $m, $d);
    }

    /** 表示用: 1 つだけのキーを配列にして Vue に渡すためのオプション形式 */
    public static function options(array $map): array
    {
        $out = [];
        foreach ($map as $value => $label) {
            $out[] = ['value' => $value, 'label' => $label];
        }

        return $out;
    }

    /** 数字（郵便番号・法人番号など）を半角数字のみにする（全角・ハイフン・空白を除去） */
    public static function digits(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return preg_replace('/\D/', '', mb_convert_kana($value, 'n'));
    }

    /** 半角英数にそろえる（社員番号） */
    public static function alnum(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return preg_replace('/[^A-Za-z0-9]/', '', mb_convert_kana($value, 'a'));
    }

    /** 氏名などの空白を全角スペース 1 つにそろえる（姓 + 空白 + 名） */
    public static function fullWidthSpace(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return self::zenkakuText($value);
    }

    /** フリガナ: 全角カナにそろえ、空白は全角スペース 1 つ */
    public static function kana(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return preg_replace('/[\s　]+/u', '　', trim(mb_convert_kana($value, 'KCVS')));
    }
}
