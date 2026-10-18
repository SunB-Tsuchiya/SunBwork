<?php

namespace App\Support\SunA\GeneralAffairs\Nencho;

use App\Models\SunA\GeneralAffairs\Nencho\EmployeeProfile;
use App\Models\SunA\GeneralAffairs\Nencho\InsuranceItem;
use App\Models\SunA\GeneralAffairs\Nencho\Submission;

/**
 * 提出前の必須チェック。ステップの保存は形式チェックのみなので、未入力はここで洗い出す。
 * 戻り値: [['step' => 1, 'message' => '...'], ...]
 */
class Completeness
{
    public static function missing(Submission $s): array
    {
        $out = [];
        $add = function (int $step, string $msg) use (&$out) { $out[] = ['step' => $step, 'message' => $msg]; };
        $blank = fn ($v) => $v === null || $v === '';

        foreach ([
            'name' => '氏名', 'name_kana' => 'フリガナ', 'birthday' => '生年月日', 'postal_code' => '郵便番号', 'prefecture' => '都道府県',
            'city' => '市区町村', 'street' => '丁番地以降', 'household_head_name' => '世帯主の氏名', 'household_head_relation' => '世帯主との続柄',
        ] as $f => $label) {
            if ($blank($s->{$f})) $add(1, "本人情報: {$label}が未入力です。");
        }
        foreach (['salary_income' => '本年の給与収入', 'salary_income_estimate' => '本年の給与所得の見積額', 'other_income_estimate' => '給与所得以外の所得の見積額'] as $f => $label) {
            if ($blank($s->{$f})) $add(3, "所得の見積り: {$label}が未入力です（ない場合は 0 を入力してください）。");
        }

        $sp = $s->spouse;
        if ($sp && $sp->spouse_status === 1) {
            foreach (['name' => '氏名', 'name_kana' => 'フリガナ', 'birthday' => '生年月日', 'kind' => '配偶者の種別', 'income_estimate' => '本年中の所得の見積額'] as $f => $label) {
                if ($blank($sp->{$f})) $add(4, "配偶者: {$label}が未入力です。");
            }
            if (! $sp->address_same && ($blank($sp->postal_code) || $blank($sp->prefecture) || $blank($sp->city) || $blank($sp->street))) {
                $add(4, '配偶者: 住所が未入力です（申告者と同じ場合は「申告者と同じ」を選んでください）。');
            }
        }

        foreach ($s->dependents as $i => $d) {
            $n = $i + 1;
            foreach (['kind' => '種別', 'name' => '氏名', 'name_kana' => 'フリガナ', 'relation' => '続柄', 'birthday' => '生年月日', 'income_estimate' => '本年中の所得の見積額'] as $f => $label) {
                if ($blank($d->{$f})) $add(5, "扶養親族{$n}人目: {$label}が未入力です。");
            }
            if (! $d->address_same && ($blank($d->postal_code) || $blank($d->prefecture) || $blank($d->city) || $blank($d->street))) {
                $add(5, "扶養親族{$n}人目: 住所が未入力です。");
            }
            if ($d->kind === 2 && $blank($d->other_earner['name'] ?? null)) {
                $add(5, "扶養親族{$n}人目: 控除を受ける他の所得者の氏名が未入力です。");
            }
        }

        $required = [
            'life_general' => ['company_name' => '保険会社名', 'insurance_type' => '保険種類', 'contractor_name' => '契約者名', 'beneficiary_name' => '受取人', 'new_old' => '新・旧の区分', 'period' => '保険期間', 'amount' => '支払保険料'],
            'life_care' => ['company_name' => '保険会社名', 'insurance_type' => '保険種類', 'contractor_name' => '契約者名', 'beneficiary_name' => '受取人', 'period' => '保険期間', 'amount' => '支払保険料'],
            'life_pension' => ['company_name' => '保険会社名', 'insurance_type' => '保険種類', 'contractor_name' => '契約者名', 'beneficiary_name' => '受取人', 'new_old' => '新・旧の区分', 'pension_start_on' => '支払開始日', 'period' => '保険期間', 'amount' => '支払保険料'],
            'earthquake' => ['company_name' => '保険会社名', 'insurance_type' => '保険等の種類（目的）', 'contractor_name' => '契約者名', 'insured_name' => '居住者等の氏名', 'quake_class' => '区分', 'period' => '保険期間', 'amount' => '支払保険料'],
            'social' => ['insurance_type' => '社会保険の種類', 'payee_name' => '保険料支払先の名称', 'insured_name' => '保険料を負担する人の氏名', 'amount' => '支払った保険料'],
        ];
        foreach ($s->insuranceItems->groupBy('kind') as $kind => $items) {
            foreach ($items->values() as $i => $item) {
                foreach ($required[$kind] ?? [] as $f => $label) {
                    if ($blank($item->{$f})) $add(6, InsuranceItem::KINDS[$kind] . ($i + 1) . "件目: {$label}が未入力です。");
                }
            }
        }

        return $out;
    }

    /** 提出を妨げないが、案内したい注意（社員情報にマイナンバーが無い等） */
    public static function notices(Submission $s): array
    {
        $out = [];
        $profile = $s->user_id ? EmployeeProfile::where('user_id', $s->user_id)->first() : null;
        if (! $profile?->my_number) {
            $out[] = 'あなたのマイナンバーは、社員情報にまだ登録されていません。提出後に、総務へご連絡ください。';
        }

        return $out;
    }
}
