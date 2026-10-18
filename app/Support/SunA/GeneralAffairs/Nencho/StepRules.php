<?php

namespace App\Support\SunA\GeneralAffairs\Nencho;

use App\Models\SunA\GeneralAffairs\Nencho\InsuranceItem;
use Illuminate\Validation\Rule;

/**
 * 年末調整フォームのステップ定義と、ステップごとの入力整形・検証ルール。
 * 項目名・桁数・コード値は z_instructions/nencho_nta/json/NTAAPP001/003/004/006.json（国税庁 XML 定義書）が正。
 * ステップの保存は「形式チェックのみ」。必須の確認は提出時（Completeness）に行う（途中保存を妨げないため）。
 */
class StepRules
{
    public const STEPS = [
        1 => ['key' => 'person', 'label' => '本人情報'],
        2 => ['key' => 'category', 'label' => '本人の区分'],
        3 => ['key' => 'income', 'label' => '所得の見積り'],
        4 => ['key' => 'spouse', 'label' => '配偶者'],
        5 => ['key' => 'dependents', 'label' => '扶養親族'],
        6 => ['key' => 'insurance', 'label' => '保険料控除'],
        7 => ['key' => 'review', 'label' => '確認・提出'],
    ];

    public static function stepKey(int $step): ?string
    {
        return self::STEPS[$step]['key'] ?? null;
    }

    private const FULL_NAME = ['nullable', 'string', 'max:30', 'regex:/^\S+　\S+$/u'];

    private const KANA = ['nullable', 'string', 'max:60', 'regex:/^[ァ-ヶー]+　[ァ-ヶー]+$/u'];

    private static function money(bool $signed = false): array
    {
        return ['nullable', 'integer', $signed ? 'min:-9999999999' : 'min:0', 'max:9999999999'];
    }

    private static function code(array $map): array
    {
        return ['nullable', Rule::in(array_keys($map))];
    }

    /** 入力の整形（全角・ハイフン・カンマなどを国の様式に合わせる） */
    public static function prepare(string $key, array $in): array
    {
        $fn = fn ($v) => NenchoRules::fullWidthSpace(is_string($v) ? $v : null);
        $kana = fn ($v) => NenchoRules::kana(is_string($v) ? $v : null);
        $digits = fn ($v) => NenchoRules::digits(is_string($v) ? $v : null);
        $amt = fn ($v) => NenchoRules::amount($v);
        $blank = fn ($v) => ($v === '' ? null : $v);
        // 日付（19770905 / 1977/09/05 / 昭和52年9月5日 など）は Y-m-d にそろえる
        $date = fn ($v) => NenchoRules::date(is_string($v) ? $v : null);
        // 住所・学校名などの日本語テキストは国の様式（全角）にそろえる（英数字・ハイフンも全角）
        $text = fn ($v) => NenchoRules::zenkakuText(is_string($v) ? $v : null);

        switch ($key) {
            case 'person':
                foreach (['name', 'household_head_name'] as $f) {
                    if (array_key_exists($f, $in)) $in[$f] = $fn($in[$f]);
                }
                if (array_key_exists('name_kana', $in)) $in['name_kana'] = $kana($in['name_kana']);
                if (array_key_exists('postal_code', $in)) $in['postal_code'] = $digits($in['postal_code']);
                foreach (['city', 'street', 'household_head_relation_other'] as $f) {
                    if (array_key_exists($f, $in)) $in[$f] = $text($in[$f]);
                }
                if (array_key_exists('birthday', $in)) $in['birthday'] = $date($in['birthday']);
                if (array_key_exists('household_head_relation', $in)) $in['household_head_relation'] = $blank($in['household_head_relation']);
                break;
            case 'category':
                foreach (['school_name', 'disability_handbook_type', 'disability_detail'] as $f) {
                    if (array_key_exists($f, $in)) $in[$f] = $text($in[$f]);
                }
                foreach (['disability_issued_on', 'enrolled_on'] as $f) {
                    if (array_key_exists($f, $in)) $in[$f] = $date($in[$f]);
                }
                foreach (['disability', 'single_parent', 'working_student'] as $k) {
                    if (isset($in['category_changes'][$k]['reason_other'])) $in['category_changes'][$k]['reason_other'] = $text($in['category_changes'][$k]['reason_other']);
                }
                break;
            case 'income':
                foreach (['salary_income', 'salary_income_estimate', 'other_income_estimate'] as $f) {
                    if (array_key_exists($f, $in)) $in[$f] = $amt($in[$f]);
                }
                break;
            case 'spouse':
                foreach (['name'] as $f) { if (array_key_exists($f, $in)) $in[$f] = $fn($in[$f]); }
                if (array_key_exists('name_kana', $in)) $in['name_kana'] = $kana($in['name_kana']);
                foreach (['postal_code', 'my_number'] as $f) { if (array_key_exists($f, $in)) $in[$f] = $digits($in[$f]); }
                foreach (['city', 'street', 'disability_handbook_type', 'disability_detail', 'remittance_unit_other'] as $f) {
                    if (array_key_exists($f, $in)) $in[$f] = $text($in[$f]);
                }
                if (isset($in['change']['reason_other'])) $in['change']['reason_other'] = $text($in['change']['reason_other']);
                foreach (['income_estimate', 'retirement_income', 'remittance_amount'] as $f) {
                    if (array_key_exists($f, $in)) $in[$f] = $amt($in[$f]);
                }
                foreach (['birthday', 'disability_issued_on'] as $f) {
                    if (array_key_exists($f, $in)) $in[$f] = $date($in[$f]);
                }
                foreach (['kind', 'remittance_unit'] as $f) {
                    if (array_key_exists($f, $in)) $in[$f] = $blank($in[$f]);
                }
                break;
            case 'dependents':
                foreach (($in['dependents'] ?? []) as $i => $row) {
                    foreach (['name'] as $f) { if (isset($row[$f])) $row[$f] = $fn($row[$f]); }
                    if (isset($row['name_kana'])) $row['name_kana'] = $kana($row['name_kana']);
                    foreach (['postal_code', 'my_number'] as $f) { if (isset($row[$f])) $row[$f] = $digits($row[$f]); }
                    foreach (['city', 'street', 'relation_other', 'disability_handbook_type', 'disability_detail', 'remittance_unit_other'] as $f) {
                        if (isset($row[$f])) $row[$f] = $text($row[$f]);
                    }
                    if (isset($row['change']['reason_other'])) $row['change']['reason_other'] = $text($row['change']['reason_other']);
                    if (isset($row['other_earner']) && is_array($row['other_earner'])) {
                        $oe = $row['other_earner'];
                        if (isset($oe['name'])) $oe['name'] = $fn($oe['name']);
                        if (isset($oe['postal_code'])) $oe['postal_code'] = $digits($oe['postal_code']);
                        foreach (['city', 'street', 'relation_other'] as $f) {
                            if (isset($oe[$f])) $oe[$f] = $text($oe[$f]);
                        }
                        $row['other_earner'] = $oe;
                    }
                    foreach (['income_estimate', 'retirement_income', 'remittance_amount'] as $f) {
                        if (array_key_exists($f, $row)) $row[$f] = $amt($row[$f]);
                    }
                    foreach (['birthday', 'disability_issued_on'] as $f) {
                        if (array_key_exists($f, $row)) $row[$f] = $date($row[$f]);
                    }
                    foreach (['kind', 'relation', 'remittance_unit', 'nonresident_reason'] as $f) {
                        if (array_key_exists($f, $row)) $row[$f] = $blank($row[$f]);
                    }
                    $in['dependents'][$i] = $row;
                }
                break;
            case 'insurance':
                foreach (($in['items'] ?? []) as $kind => $rows) {
                    foreach ((array) $rows as $i => $row) {
                        // 保険の氏名・種類・支払先は国の様式（全角）にそろえる（保険会社名は全半角どちらも可なのでそのまま）
                        foreach (['contractor_name', 'beneficiary_name', 'insured_name'] as $f) {
                            if (isset($row[$f])) $row[$f] = $fn($row[$f]);
                        }
                        foreach (['insurance_type', 'payee_name'] as $f) {
                            if (isset($row[$f])) $row[$f] = $text($row[$f]);
                        }
                        if (array_key_exists('amount', $row)) $row['amount'] = $amt($row['amount']);
                        if (array_key_exists('period', $row)) $row['period'] = $amt($row['period']);
                        if (array_key_exists('pension_start_on', $row)) $row['pension_start_on'] = $date($row['pension_start_on']);
                        foreach (['new_old', 'quake_class'] as $f) {
                            if (array_key_exists($f, $row)) $row[$f] = $blank($row[$f]);
                        }
                        $in['items'][$kind][$i] = $row;
                    }
                }
                foreach (['small_biz_kyosai', 'dc_corporate', 'dc_individual', 'disabled_kyosai'] as $f) {
                    if (array_key_exists($f, $in)) $in[$f] = $amt($in[$f]);
                }
                if (isset($in['under23'])) {
                    $u = $in['under23'];
                    if (isset($u['name'])) $u['name'] = $fn($u['name']);
                    if (isset($u['name_kana'])) $u['name_kana'] = $kana($u['name_kana']);
                    if (isset($u['birthday'])) $u['birthday'] = $date($u['birthday']);
                    if (array_key_exists('income_estimate', $u)) $u['income_estimate'] = $amt($u['income_estimate']);
                    $in['under23'] = $u;
                }
                break;
        }

        return $in;
    }

    /** @return array{0: array, 1: array, 2: array} [rules, messages, attributes] */
    public static function rules(string $key): array
    {
        $messages = [
            '*.regex' => '入力の形式が正しくありません。',
            'name.regex' => '氏名は「姓」と「名」の間にスペースを入れてください。',
            'name_kana.regex' => 'フリガナは全角カナで、「姓」と「名」の間にスペースを入れてください。',
            'postal_code.regex' => '郵便番号は半角数字7桁で入力してください。',
            'my_number.regex' => 'マイナンバーは半角数字12桁で入力してください。',
        ];
        $change = fn (string $p, string $type) => [
            "$p.month" => ['nullable', 'integer', 'between:1,12'],
            "$p.day" => ['nullable', 'integer', 'between:1,31'],
            "$p.reason" => self::code(NenchoRules::CHANGE_REASONS[$type]),
            "$p.reason_other" => ['nullable', 'string', 'max:50'],
        ];
        $disability = fn (string $p, int $max) => [
            "{$p}disability_class" => ['nullable', Rule::in(range(0, $max))],
            "{$p}disability_handbook_type" => ['nullable', 'string', 'max:20'],
            "{$p}disability_issued_on" => ['nullable', 'date_format:Y-m-d'],
            "{$p}disability_detail" => ['nullable', 'string', 'max:50'],
        ];
        $address = fn (string $p) => [
            "{$p}address_same" => ['nullable', 'boolean'],
            "{$p}postal_code" => ['nullable', 'regex:/^\d{7}$/'],
            "{$p}prefecture" => ['nullable', Rule::in(NenchoRules::PREFECTURES)],
            "{$p}city" => ['nullable', 'string', 'max:35'],
            "{$p}street" => ['nullable', 'string', 'max:100'],
        ];

        switch ($key) {
            case 'person':
                return [[
                    'name' => self::FULL_NAME, 'name_kana' => self::KANA,
                    'birthday' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before:today'],
                    'postal_code' => ['nullable', 'regex:/^\d{7}$/'],
                    'prefecture' => ['nullable', Rule::in(NenchoRules::PREFECTURES)],
                    'city' => ['nullable', 'string', 'max:35'], 'street' => ['nullable', 'string', 'max:100'],
                    'household_head_name' => self::FULL_NAME,
                    'household_head_relation' => self::code(NenchoRules::HOUSEHOLD_RELATIONS),
                    'household_head_relation_other' => ['nullable', 'required_if:household_head_relation,99', 'string', 'max:10'],
                ], $messages, ['name' => '氏名', 'name_kana' => 'フリガナ', 'birthday' => '生年月日', 'postal_code' => '郵便番号', 'prefecture' => '都道府県', 'city' => '市区町村', 'street' => '丁番地以降', 'household_head_name' => '世帯主の氏名', 'household_head_relation' => '世帯主との続柄', 'household_head_relation_other' => '続柄（その他）']];

            case 'category':
                return [array_merge([
                    'no_category' => ['nullable', 'boolean'],
                    'single_parent_class' => self::code(NenchoRules::SINGLE_PARENT_CLASSES),
                    'working_student' => ['nullable', 'boolean'],
                    'school_name' => ['nullable', 'string', 'max:30'],
                    'enrolled_on' => ['nullable', 'date_format:Y-m-d'],
                    'resident_tax_single_parent_class' => self::code(NenchoRules::SINGLE_PARENT_CLASSES),
                    'has_secondary_job_form' => ['nullable', 'boolean'],
                    'category_changes' => ['nullable', 'array'],
                ], $disability('', 2), $change('category_changes.disability', 'disability'), $change('category_changes.single_parent', 'single_parent'), $change('category_changes.working_student', 'working_student')), $messages, ['school_name' => '学校名', 'disability_handbook_type' => '手帳の種類', 'disability_detail' => '障害の内容']];

            case 'income':
                return [[
                    'salary_income' => self::money(), 'salary_income_estimate' => self::money(), 'other_income_estimate' => self::money(true),
                ], $messages, ['salary_income' => '本年の給与収入', 'salary_income_estimate' => '本年の給与所得の見積額', 'other_income_estimate' => '給与所得以外の所得の見積額']];

            case 'spouse':
                return [array_merge([
                    'spouse_status' => ['required', Rule::in(array_keys(NenchoRules::SPOUSE_STATUS))],
                    'kind' => self::code(NenchoRules::SPOUSE_KINDS),
                    'name' => self::FULL_NAME, 'name_kana' => self::KANA,
                    'birthday' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before:today'],
                    'my_number' => ['nullable', 'regex:/^\d{12}$/'], 'clear_my_number' => ['nullable', 'boolean'],
                    'income_estimate' => self::money(), 'retirement_income' => self::money(),
                    'nonresident' => ['nullable', 'boolean'], 'elderly' => ['nullable', 'boolean'],
                    'remittance_amount' => self::money(), 'remittance_unit' => self::code(NenchoRules::REMITTANCE_UNITS),
                    'remittance_unit_other' => ['nullable', 'string', 'max:15'],
                    'change' => ['nullable', 'array'],
                ], $address(''), $disability('', 3), $change('change', 'spouse')), $messages, ['name' => '配偶者の氏名', 'name_kana' => '配偶者のフリガナ', 'birthday' => '配偶者の生年月日', 'income_estimate' => '配偶者の所得の見積額', 'my_number' => '配偶者のマイナンバー']];

            case 'dependents':
                $rules = ['dependents' => ['nullable', 'array', 'max:20']];
                foreach ([
                    'id' => ['nullable', 'integer'],
                    'kind' => self::code(NenchoRules::DEPENDENT_KINDS),
                    'name' => self::FULL_NAME, 'name_kana' => self::KANA,
                    'my_number' => ['nullable', 'regex:/^\d{12}$/'], 'clear_my_number' => ['nullable', 'boolean'],
                    'relation' => self::code(NenchoRules::DEPENDENT_RELATIONS),
                    'relation_other' => ['nullable', 'string', 'max:10'],
                    'birthday' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before:today'],
                    'elderly_class' => self::code(NenchoRules::ELDERLY_CLASSES),
                    'specific_class' => self::code(NenchoRules::SPECIFIC_CLASSES),
                    'income_estimate' => self::money(), 'retirement_income' => self::money(),
                    'nonresident' => ['nullable', 'boolean'], 'nonresident_reason' => self::code(NenchoRules::NONRESIDENT_REASONS),
                    'remittance_amount' => self::money(), 'remittance_unit' => self::code(NenchoRules::REMITTANCE_UNITS),
                    'remittance_unit_other' => ['nullable', 'string', 'max:15'],
                    'other_earner' => ['nullable', 'array'],
                    'other_earner.name' => ['nullable', 'string', 'max:30'],
                    'other_earner.relation' => self::code(NenchoRules::OTHER_EARNER_RELATIONS),
                    'other_earner.relation_other' => ['nullable', 'string', 'max:10'],
                    'other_earner.address_same' => ['nullable', 'boolean'],
                    'other_earner.postal_code' => ['nullable', 'regex:/^\d{7}$/'],
                    'other_earner.prefecture' => ['nullable', Rule::in(NenchoRules::PREFECTURES)],
                    'other_earner.city' => ['nullable', 'string', 'max:35'],
                    'other_earner.street' => ['nullable', 'string', 'max:100'],
                    'change' => ['nullable', 'array'],
                ] + $address('') + $disability('', 3) + $change('change', 'dependent') as $f => $r) {
                    $rules["dependents.*.$f"] = $r;
                }

                return [$rules, $messages, ['dependents' => '扶養親族', 'dependents.*.name' => '扶養親族の氏名', 'dependents.*.name_kana' => '扶養親族のフリガナ', 'dependents.*.birthday' => '扶養親族の生年月日', 'dependents.*.my_number' => '扶養親族のマイナンバー', 'dependents.*.income_estimate' => '扶養親族の所得の見積額']];

            case 'insurance':
                $rules = [
                    'items' => ['nullable', 'array'],
                    'small_biz_kyosai' => self::money(), 'dc_corporate' => self::money(), 'dc_individual' => self::money(), 'disabled_kyosai' => self::money(),
                    'under23' => ['nullable', 'array'], 'under23.name' => self::FULL_NAME, 'under23.name_kana' => self::KANA,
                    'under23.birthday' => ['nullable', 'date_format:Y-m-d'], 'under23.relation' => self::code(NenchoRules::DEPENDENT_RELATIONS),
                    'under23.income_estimate' => self::money(),
                ];
                foreach (array_keys(InsuranceItem::KINDS) as $kind) {
                    $rules["items.$kind"] = ['nullable', 'array', 'max:' . InsuranceItem::MAX_PER_KIND];
                    foreach ([
                        'company_name' => ['nullable', 'string', 'max:30'], 'insurance_type' => ['nullable', 'string', 'max:30'],
                        'period' => ['nullable', 'integer', 'min:0', 'max:999'], 'contractor_name' => ['nullable', 'string', 'max:30'],
                        'beneficiary_name' => ['nullable', 'string', 'max:30'], 'insured_name' => ['nullable', 'string', 'max:30'],
                        'payee_name' => ['nullable', 'string', 'max:30'], 'new_old' => self::code(NenchoRules::NEW_OLD),
                        'pension_start_on' => ['nullable', 'date_format:Y-m-d'], 'quake_class' => self::code(NenchoRules::QUAKE_CLASSES),
                        'amount' => ['nullable', 'integer', 'min:0', 'max:9999999999'],
                    ] as $f => $r) {
                        $rules["items.$kind.*.$f"] = $r;
                    }
                }

                return [$rules, $messages, ['small_biz_kyosai' => '小規模企業共済等の掛金', 'dc_corporate' => '企業型年金加入者掛金', 'dc_individual' => '個人型年金加入者掛金', 'disabled_kyosai' => '心身障害者扶養共済の掛金']];
        }

        return [[], $messages, []];
    }
}
