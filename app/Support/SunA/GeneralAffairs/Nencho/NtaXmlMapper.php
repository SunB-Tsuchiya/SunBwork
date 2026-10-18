<?php

namespace App\Support\SunA\GeneralAffairs\Nencho;

use App\Models\SunA\GeneralAffairs\Nencho\EmployeeProfile;
use App\Models\SunA\GeneralAffairs\Nencho\InsuranceItem;
use App\Models\SunA\GeneralAffairs\Nencho\Submission;
use Carbon\CarbonInterface;

/**
 * 保存データ（提出物）を、国税庁 申告書 XML 定義書の項目（タグ）の値に変換する。
 * 対象: NTAAPP001（扶養控除等）・003（保険料控除）・004（配偶者控除等）・006（基礎控除）。
 * XML ファイルの出力（D-2）はまだ行わない。ここでは「保存データが国の形式に変換できるか」を検査するための値を作る。
 *
 * 個人番号: 本人は社員情報（会社が保有）なので「提供済み=1・個人番号は空タグ」。配偶者・扶養親族は申請で入力した 12 桁を出す（提供済み=0）。
 * 控除額・区分１/２（国の計算結果の項目）は保存していないので変換対象外（決定 10）。
 *
 * @return list<array{form: string, tag: string, path: string, value: mixed, row: string}>
 */
class NtaXmlMapper
{
    private array $out = [];

    public static function map(Submission $s): array
    {
        $s->loadMissing(['campaign', 'spouse', 'dependents', 'insuranceItems']);

        return (new self())->build($s);
    }

    private function build(Submission $s): array
    {
        $profile = $s->user_id ? EmployeeProfile::where('user_id', $s->user_id)->first() : null;
        $hasMyNumber = (bool) ($s->my_number || $profile?->my_number);
        $c = $s->campaign;
        $sp = $s->spouse;

        foreach (['NTAAPP001', 'NTAAPP003', 'NTAAPP004', 'NTAAPP006'] as $form) {
            $this->header($form, $s, $profile, $c);
        }

        // ---- NTAAPP001: 扶養控除等（異動）申告書 ----
        $f = 'NTAAPP001';
        $base = '提出に関する情報/';
        $this->put($f, $base . '申告者個人番号提供済み', $hasMyNumber ? 1 : 0);
        $this->dateParts($f, $base . '申告者生年月日/生年月日（%s）', $s->birthday, ['西暦', '元号', '年', '月', '日']);
        $this->put($f, $base . '世帯主/世帯主の氏名', $s->household_head_name);
        $this->put($f, $base . '世帯主/続柄/コード', $s->household_head_relation);
        $this->put($f, $base . '世帯主/続柄/その他続柄', $s->household_head_relation == 99 ? $s->household_head_relation_other : null);
        $this->put($f, $base . '従たる給与についての扶養控除等申告書の提出の有無', $s->has_secondary_job_form ? 1 : 0);
        $o = $base . '申告者のその他情報/';
        $this->put($f, $o . 'いずれも該当しない', $s->no_category ? 1 : 0);
        if (! $s->no_category) {
            $this->disability($f, $o . '障害者である事実/', $s->disability_class, $s->disability_handbook_type, $s->disability_issued_on, $s->disability_detail, $s->category_changes['disability'] ?? null, '障害者の異動月日', true);
            $this->put($f, $o . 'ひとり親・寡婦に該当する事実/ひとり親・寡婦の区分', $s->single_parent_class);
            $this->change($f, $o . 'ひとり親・寡婦に該当する事実/ひとり親・寡婦の異動月日', $s->category_changes['single_parent'] ?? null);
            if ($s->working_student) {
                $w = $o . '勤労学生に該当する事実/';
                $this->put($f, $w . '学校名', $s->school_name);
                $this->dateParts($f, $w . '入学年月日/入学月日（%s）', $s->enrolled_on, ['年', '月', '日'], true);
                $this->change($f, $w . '勤労学生の異動月日', $s->category_changes['working_student'] ?? null);
            }
            $this->put($f, $base . '申告者のその他情報/ひとり親・寡婦に該当する事実（住民税に関する事項）/ひとり親・寡婦の区分（住民税に関する事項）', $s->resident_tax_single_parent_class);
        }
        $this->put($f, $o . '本年中の所得/本年の給与収入', $s->salary_income);
        $this->put($f, $o . '本年中の所得/本年の給与所得の見積額', $s->salary_income_estimate);
        $this->put($f, $o . '本年中の所得/給与所得以外の所得の見積額', $s->other_income_estimate);
        $this->put($f, $o . '本年中の所得/本年の合計所得の見積額', $s->total_income_estimate);

        // 配偶者
        $this->put($f, '配偶者/配偶者有無', $sp?->spouse_status ?? 0);
        if ($sp && $sp->spouse_status === 1) {
            $this->put($f, '配偶者/配偶者の種別', $sp->kind);
            $b = '配偶者/配偶者情報/';
            $this->put($f, $b . '配偶者フリガナ', $sp->name_kana);
            $this->put($f, $b . '配偶者氏名', $sp->name);
            $this->put($f, $b . '配偶者個人番号提供済み', 0);
            $this->put($f, $b . '配偶者個人番号', $sp->my_number);
            $this->dateParts($f, $b . '配偶者生年月日/生年月日（%s）', $sp->birthday, ['西暦', '元号', '年', '月', '日']);
            $this->put($f, $b . '本年中の所得の見積額', $sp->income_estimate);
            $this->put($f, $b . '源泉徴収される退職手当等に係る退職所得', $sp->retirement_income);
            $this->put($f, $b . '非居住者である親族', $sp->nonresident ? 1 : 0);
            $this->address($f, $b, '郵便番号', '住所又は居所/', '市区町村（海外住所の場合有り）', '丁番目以降（海外住所の場合有り）', $sp, $s, true);
            $this->change($f, $b . '異動情報', $sp->change);
            $this->disability($f, $b . '障害者である事実/', $sp->disability_class, $sp->disability_handbook_type, $sp->disability_issued_on, $sp->disability_detail, null, '障害者の異動月日', false);
            if ($sp->nonresident) {
                $this->put($f, $b . '生計を一にする事実/送金額', $sp->remittance_amount);
                $this->put($f, $b . '生計を一にする事実/単位/コード', $sp->remittance_unit);
                $this->put($f, $b . '生計を一にする事実/単位/その他単位', $sp->remittance_unit == 99 ? $sp->remittance_unit_other : null);
            }
        } elseif ($sp && $sp->spouse_status === 2) {
            $this->change($f, '配偶者/配偶者情報/異動情報', $sp->change);
        }

        // 扶養親族（繰り返し）
        foreach ($s->dependents as $i => $d) {
            $r = '扶養親族' . ($i + 1) . '人目';
            $p = '扶養親族情報　繰り返し/';
            $this->put($f, $p . '扶養親族の種別', $d->kind, $r);
            $this->put($f, $p . 'フリガナ', $d->name_kana, $r);
            $this->put($f, $p . '氏名', $d->name, $r);
            $this->put($f, $p . '個人番号提供済み', 0, $r);
            $this->put($f, $p . '個人番号', $d->my_number, $r);
            $this->put($f, $p . '続柄/コード', $d->relation, $r);
            $this->put($f, $p . '続柄/その他続柄', $d->relation == 99 ? $d->relation_other : null, $r);
            $this->dateParts($f, $p . '生年月日/生年月日（%s）', $d->birthday, ['西暦', '元号', '年', '月', '日'], false, $r);
            $this->put($f, $p . '老人扶養親族', $d->elderly_class, $r);
            $this->put($f, $p . '特定扶養親族/特定親族', $d->specific_class, $r);
            $this->put($f, $p . '本年中の所得の見積額', $d->income_estimate, $r);
            $this->put($f, $p . '源泉徴収される退職手当等に係る退職所得', $d->retirement_income, $r);
            $this->put($f, $p . '非居住者である親族/控除対象外国外扶養親族', $d->nonresident ? 1 : 0, $r);
            $this->put($f, $p . '非居住者である親族の該当する項目', $d->nonresident ? $d->nonresident_reason : null, $r);
            if ($d->nonresident) {
                $this->put($f, $p . '生計を一にする事実/送金額', $d->remittance_amount, $r);
                $this->put($f, $p . '生計を一にする事実/単位/コード', $d->remittance_unit, $r);
                $this->put($f, $p . '生計を一にする事実/単位/その他単位', $d->remittance_unit == 99 ? $d->remittance_unit_other : null, $r);
            }
            $this->address($f, $p, '郵便番号', '住所又は居所/', '市区町村（海外住所の場合有り）', '丁番目以降（海外住所の場合有り）', $d, $s, true, $r);
            $this->disability($f, $p . '障害者である事実/', $d->disability_class, $d->disability_handbook_type, $d->disability_issued_on, $d->disability_detail, null, '障害者の異動月日', false, $r);
            if ($d->kind == 2 && ! empty($d->other_earner['name'])) {
                $oe = $d->other_earner;
                $q = $p . '控除を受ける他の所得者/';
                $this->put($f, $q . '氏名', $oe['name'], $r);
                $this->put($f, $q . '続柄/コード', $oe['relation'] ?? null, $r);
                $this->put($f, $q . '続柄/その他続柄', ($oe['relation'] ?? null) == 99 ? ($oe['relation_other'] ?? null) : null, $r);
                $same = ! empty($oe['address_same']);
                $this->put($f, $q . '郵便番号', $same ? $s->postal_code : ($oe['postal_code'] ?? null), $r);
                $this->put($f, $q . '住所又は居所/申告者と同じ', $same ? 1 : 0, $r);
                $this->put($f, $q . '住所又は居所/都道府県', $same ? $s->prefecture : ($oe['prefecture'] ?? null), $r);
                $this->put($f, $q . '住所又は居所/市区町村（海外住所の場合有り）', $same ? $s->city : ($oe['city'] ?? null), $r);
                $this->put($f, $q . '住所又は居所/丁番目以降（海外住所の場合有り）', $same ? $s->street : ($oe['street'] ?? null), $r);
            }
            $this->change($f, $p . '異動情報', $d->change, $r);
        }

        // ---- NTAAPP004: 配偶者控除等申告書 ----
        $f = 'NTAAPP004';
        $this->put($f, '合計所得の見積額/あなたの本年中の合計所得金額の見積額', $s->total_income_estimate);
        $this->put($f, '合計所得の見積額/あなたの本年中の給与所得金額の見積額', $s->salary_income_estimate);
        $this->put($f, '合計所得の見積額/あなたの本年中の給与所得以外の所得金額の見積額', $s->other_income_estimate);
        if ($sp && $sp->spouse_status === 1) {
            $b = '配偶者/';
            $this->put($f, $b . 'フリガナ', $sp->name_kana);
            $this->put($f, $b . '氏名', $sp->name);
            $this->put($f, $b . '個人番号提供済み', 0);
            $this->put($f, $b . '個人番号', $sp->my_number);
            $this->dateParts($f, $b . '生年月日/生年月日（%s）', $sp->birthday, ['西暦', '元号', '年', '月', '日']);
            $this->address($f, $b, '郵便番号', '住所又は居所/', '市区町村（海外住所の場合有り）', '丁番目以降（海外住所の場合有り）', $sp, $s, true);
            $this->put($f, $b . '非居住者である配偶者', $sp->nonresident ? 1 : 0);
            $this->put($f, $b . '配偶者の本年中の合計所得金額の見積額', $sp->income_estimate);
            $this->put($f, $b . '老人控除対象配偶者', $sp->elderly ? 1 : 0);
            if ($sp->nonresident) {
                $this->put($f, $b . '生計を一にする事実/送金額', $sp->remittance_amount);
                $this->put($f, $b . '生計を一にする事実/単位名/コード', $sp->remittance_unit);
                $this->put($f, $b . '生計を一にする事実/単位名/その他単位', $sp->remittance_unit == 99 ? $sp->remittance_unit_other : null);
            }
        }

        // ---- NTAAPP006: 基礎控除申告書 ----
        $f = 'NTAAPP006';
        $this->put($f, '合計所得の見積額/あなたの本年中の合計所得金額の見積額', $s->total_income_estimate);
        $this->put($f, '合計所得の見積額/あなたの本年中の給与所得金額の見積額', $s->salary_income_estimate);
        $this->put($f, '合計所得の見積額/あなたの本年中の給与所得以外の所得金額の見積額', $s->other_income_estimate);

        // ---- NTAAPP003: 保険料控除申告書 ----
        $f = 'NTAAPP003';
        $groups = [
            'life_general' => ['一般の生命保険料　繰り返し/繰り返し項目/', ['保険会社名' => 'company_name', '保険種類' => 'insurance_type', '保険期間' => 'period', '契約者名' => 'contractor_name', '受取人' => 'beneficiary_name', '新・旧の区分' => 'new_old', '本年中に支払った保険料等の金額' => 'amount']],
            'life_care' => ['介護医療保険料　繰り返し/繰り返し項目/', ['保険会社名' => 'company_name', '保険種類' => 'insurance_type', '保険期間' => 'period', '契約者名' => 'contractor_name', '受取人' => 'beneficiary_name', '本年中に支払った保険料等の金額' => 'amount']],
            'life_pension' => ['個人年金保険料　繰り返し/繰り返し項目/', ['保険会社名' => 'company_name', '保険種類' => 'insurance_type', '保険期間' => 'period', '契約者名' => 'contractor_name', '受取人' => 'beneficiary_name', '新・旧の区分' => 'new_old', '本年中に支払った保険料等の金額' => 'amount']],
            'earthquake' => ['地震保険料控除　繰り返し/繰り返し項目/', ['保険会社名' => 'company_name', '保険等の種類（目的）' => 'insurance_type', '保険期間' => 'period', '契約者名' => 'contractor_name', '保険等の対象となった家屋等に居住または家財を利用してる者等の氏名' => 'insured_name', '区分' => 'quake_class', '本年中に支払った保険料等の金額' => 'amount']],
            'social' => ['社会保険料控除　繰り返し/繰り返し項目/', ['社会保険の種類' => 'insurance_type', '保険料支払先の名称' => 'payee_name', '保険料を負担することとなっている人の氏名' => 'insured_name', '支払った保険料の金額' => 'amount']],
        ];
        $counters = [];
        foreach ($s->insuranceItems as $item) {
            [$prefix, $fields] = $groups[$item->kind];
            $n = $counters[$item->kind] = ($counters[$item->kind] ?? 0) + 1;
            $r = InsuranceItem::KINDS[$item->kind] . $n . '件目';
            foreach ($fields as $label => $col) {
                $this->put($f, $prefix . $label, $item->{$col}, $r);
            }
            if ($item->kind === 'life_pension') {
                $this->dateParts($f, $prefix . '支払開始日/年月日（%s）', $item->pension_start_on, ['西暦', '元号', '年', '月', '日'], false, $r);
            }
        }
        if ($u = $s->life_under23_dependent) {
            $b = '一般の生命保険料　繰り返し/23歳未満扶養親族の情報/';
            $this->put($f, $b . 'フリガナ', $u['name_kana'] ?? null);
            $this->put($f, $b . '氏名', $u['name'] ?? null);
            $this->put($f, $b . '個人番号提供済み', 0);
            $birth = ! empty($u['birthday']) ? \Carbon\Carbon::parse($u['birthday']) : null;
            $this->dateParts($f, $b . '生年月日/生年月日（%s）', $birth, ['西暦', '元号', '年', '月', '日']);
            $this->put($f, $b . '続柄/コード', $u['relation'] ?? null);
            $this->put($f, $b . '本年中の所得金額の見積額', $u['income_estimate'] ?? null);
        }
        $k = '小規模企業共済等掛金控除　繰り返し/';
        $this->put($f, $k . '独立行政法人中小企業基盤整備機構の共済契約の掛金', $s->small_biz_kyosai);
        $this->put($f, $k . '確定拠出年金法に規定する企業型年金加入者掛金', $s->dc_corporate);
        $this->put($f, $k . '確定拠出年金法に規定する個人型年金加入者掛金', $s->dc_individual);
        $this->put($f, $k . '心身障害者扶養共済制度に関する契約の掛金', $s->disabled_kyosai);

        return $this->out;
    }

    /** 申告者・支払者・対象年分・参考情報（4 つの申告書に共通） */
    private function header(string $f, Submission $s, ?EmployeeProfile $profile, $c): void
    {
        $b = '提出に関する情報/';
        $zip = $f === 'NTAAPP001' ? '申告者郵便番号' : '申告者の郵便番号';
        $this->put($f, $b . '対象年分/控除申告年分（西暦）', $c->fiscal_year);
        $this->put($f, $b . '対象年分/控除申告年分（元号）', 5);
        $this->put($f, $b . '対象年分/控除申告年分（年）', $c->fiscal_year - 2018);
        $this->put($f, $b . '所轄税務署', $c->tax_office_name);
        if ($f === 'NTAAPP001') {
            $this->put($f, $b . '所轄市区町村', $c->municipality_name);
        }
        $this->put($f, $b . '支払者/給与の支払者の名称', $c->payer_name);
        $this->put($f, $b . '支払者/給与の支払者の法人番号', $c->payer_corporate_number);
        $this->put($f, $b . '支払者/給与の支払者の郵便番号', $c->payer_postal_code);
        $this->put($f, $b . '支払者/給与の支払者の所在地/都道府県', $c->payer_prefecture);
        $this->put($f, $b . '支払者/給与の支払者の所在地/市区町村', $c->payer_city);
        $this->put($f, $b . '支払者/給与の支払者の所在地/丁番地以降', $c->payer_street);
        $this->put($f, $b . '申告者フリガナ', $s->name_kana);
        $this->put($f, $b . '申告者氏名', $s->name);
        $this->put($f, $b . $zip, $s->postal_code);
        $this->put($f, $b . '申告者住所又は居所/都道府県', $s->prefecture);
        $this->put($f, $b . '申告者住所又は居所/市区町村', $s->city);
        $this->put($f, $b . '申告者住所又は居所/丁番地以降', $s->street);
        $at = $s->submitted_at ?? now();
        $this->put($f, '参考情報/提出年月日/年月日（西暦）', (int) $at->format('Y'));
        $this->put($f, '参考情報/提出年月日/年月日（元号）', 5);
        $this->put($f, '参考情報/提出年月日/年月日（年）', (int) $at->format('Y') - 2018);
        $this->put($f, '参考情報/提出年月日/年月日（月）', (int) $at->format('n'));
        $this->put($f, '参考情報/提出年月日/年月日（日）', (int) $at->format('j'));
        $this->put($f, '参考情報/ID（社員番号等）', $profile?->employee_number);
    }

    /** 住所（郵便番号・申告者と同じ・都道府県・市区町村・丁番地以降）。「申告者と同じ」のときは申告者の住所を設定する（定義書の指定） */
    private function address(string $f, string $base, string $zipLabel, string $addrBase, string $cityLabel, string $streetLabel, $o, Submission $s, bool $hasSameFlag, string $row = ''): void
    {
        $same = (bool) $o->address_same;
        $this->put($f, $base . $zipLabel, $same ? $s->postal_code : $o->postal_code, $row);
        if ($hasSameFlag) {
            $this->put($f, $base . $addrBase . '申告者と同じ', $same ? 1 : 0, $row);
        }
        $this->put($f, $base . $addrBase . '都道府県', $same ? $s->prefecture : $o->prefecture, $row);
        $this->put($f, $base . $addrBase . $cityLabel, $same ? $s->city : $o->city, $row);
        $this->put($f, $base . $addrBase . $streetLabel, $same ? $s->street : $o->street, $row);
    }

    /** 障害者である事実（区分・手帳・交付年月日・障害の内容・異動）。区分が 0（非該当）のときは区分だけ */
    private function disability(string $f, string $base, $class, $handbook, $issued, $detail, ?array $change, string $changeLabel, bool $withChange, string $row = ''): void
    {
        $this->put($f, $base . '障害者区分', (int) $class, $row);
        if ((int) $class === 0) {
            return;
        }
        $this->put($f, $base . '手帳の種類', $handbook, $row);
        $this->dateParts($f, $base . '交付年月日/交付月日（%s）', $issued, ['年', '月', '日'], true, $row);
        $this->put($f, $base . '障害の内容', $detail, $row);
        if ($withChange) {
            $this->change($f, $base . $changeLabel, $change, $row);
        }
    }

    /** 異動（月日・事由）。値がなければ空タグ */
    private function change(string $f, string $base, ?array $c, string $row = ''): void
    {
        if (empty($c['month']) || empty($c['day']) || empty($c['reason'])) {
            return;
        }
        $this->put($f, $base . '/異動月日（月）', (int) $c['month'], $row);
        $this->put($f, $base . '/異動月日（日）', (int) $c['day'], $row);
        $this->put($f, $base . '/異動事由/コード', (int) $c['reason'], $row);
        $this->put($f, $base . '/異動事由/その他事由', (int) $c['reason'] === 99 ? ($c['reason_other'] ?? null) : null, $row);
    }

    /**
     * 日付を、西暦・元号コード・和暦の年・月・日 に分けて設定する。
     * 元号コード: 1明治 2大正 3昭和 4平成 5令和（定義書の指定）
     */
    private function dateParts(string $f, string $pathFormat, $date, array $parts, bool $onlyYmd = false, string $row = ''): void
    {
        if (! $date) {
            return;
        }
        $d = $date instanceof CarbonInterface ? $date : \Carbon\Carbon::parse($date);
        $y = (int) $d->format('Y');
        $iso = $d->format('Y-m-d');
        [$era, $ey] = match (true) {
            $iso >= '2019-05-01' => [5, $y - 2018], $iso >= '1989-01-08' => [4, $y - 1988], $iso >= '1926-12-25' => [3, $y - 1925],
            $iso >= '1912-07-30' => [2, $y - 1911], default => [1, $y - 1867],
        };
        $values = ['西暦' => $y, '元号' => $era, '年' => $onlyYmd ? $y : $ey, '月' => (int) $d->format('n'), '日' => (int) $d->format('j')];
        foreach ($parts as $part) {
            $this->put($f, sprintf($pathFormat, $part), $values[$part], $row);
        }
    }

    private function put(string $form, string $path, $value, string $row = ''): void
    {
        $tag = NtaDefinition::tag($form, $path);
        $this->out[] = ['form' => $form, 'tag' => $tag ?? '(定義書にない項目: ' . $path . ')', 'path' => $path, 'value' => $value === '' ? null : $value, 'row' => $row, 'known' => $tag !== null];
    }
}
