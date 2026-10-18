<?php

namespace App\Services\SunA\GeneralAffairs\Nencho;

use App\Models\SunA\GeneralAffairs\Nencho\Campaign;
use App\Models\SunA\GeneralAffairs\Nencho\Dependent;
use App\Models\SunA\GeneralAffairs\Nencho\EmployeeProfile;
use App\Models\SunA\GeneralAffairs\Nencho\InsuranceItem;
use App\Models\SunA\GeneralAffairs\Nencho\Spouse;
use App\Models\SunA\GeneralAffairs\Nencho\SubmissionEvent;
use App\Models\SunA\GeneralAffairs\Nencho\Submission;
use App\Models\User;
use App\Support\SunA\GeneralAffairs\Nencho\NenchoRules;
use Illuminate\Support\Facades\DB;

/**
 * 年末調整の提出物の作成・ステップ保存・前年読み込み・提出・取り下げ。
 */
class SubmissionService
{
    /** 提出物を作る。本人情報は社員情報（EmployeeProfile）から初期表示する */
    public function start(Campaign $campaign, User $user, ?User $enteredBy = null): Submission
    {
        $profile = EmployeeProfile::where('user_id', $user->id)->first();

        return Submission::create([
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'company_id' => $user->company_id,
            'status' => 'draft',
            'entered_by_user_id' => ($enteredBy ?? $user)->id,
            'entered_by_type' => $enteredBy && $enteredBy->id !== $user->id ? 'proxy' : 'self',
            'name' => $profile?->name ?: $user->name,
            'name_kana' => $profile?->name_kana,
            'birthday' => $profile?->birthday,
            'postal_code' => $profile?->postal_code,
            'prefecture' => $profile?->prefecture,
            'city' => $profile?->city,
            'street' => $profile?->street,
            'household_head_name' => $profile?->household_head_name,
            'household_head_relation' => $profile?->household_head_relation,
            'household_head_relation_other' => $profile?->household_head_relation_other,
        ]);
    }

    /** ステップの内容を保存（形式チェック済みの入力を受け取る） */
    public function saveStep(Submission $s, string $key, array $data): void
    {
        DB::transaction(function () use ($s, $key, $data) {
            match ($key) {
                'person' => $s->update($this->only($data, ['name', 'name_kana', 'birthday', 'postal_code', 'prefecture', 'city', 'street', 'household_head_name', 'household_head_relation', 'household_head_relation_other'])),
                'category' => $this->saveCategory($s, $data),
                'income' => $this->saveIncome($s, $data),
                'spouse' => $this->saveSpouse($s, $data),
                'dependents' => $this->saveDependents($s, $data['dependents'] ?? []),
                'insurance' => $this->saveInsurance($s, $data),
                default => null,
            };
        });
    }

    private function only(array $data, array $fields): array
    {
        return array_intersect_key($data, array_flip($fields));
    }

    private function saveCategory(Submission $s, array $d): void
    {
        if (! empty($d['no_category'])) {
            // いずれも該当しない → 区分の詳細をすべて初期化（従たる給与の提出有無は別項目）
            $s->update([
                'no_category' => true, 'disability_class' => 0, 'disability_handbook_type' => null, 'disability_issued_on' => null, 'disability_detail' => null,
                'single_parent_class' => 0, 'working_student' => false, 'school_name' => null, 'enrolled_on' => null,
                'resident_tax_single_parent_class' => 0, 'category_changes' => null, 'has_secondary_job_form' => (bool) ($d['has_secondary_job_form'] ?? false),
            ]);

            return;
        }
        $changes = [];
        foreach (['disability', 'single_parent', 'working_student'] as $k) {
            $c = $d['category_changes'][$k] ?? [];
            if (! empty($c['month']) && ! empty($c['day']) && ! empty($c['reason'])) {
                $changes[$k] = $this->only($c, ['month', 'day', 'reason', 'reason_other']);
            }
        }
        $s->update([
            'no_category' => false,
            'disability_class' => (int) ($d['disability_class'] ?? 0),
            'disability_handbook_type' => $d['disability_handbook_type'] ?? null,
            'disability_issued_on' => $d['disability_issued_on'] ?? null,
            'disability_detail' => $d['disability_detail'] ?? null,
            'single_parent_class' => (int) ($d['single_parent_class'] ?? 0),
            'working_student' => (bool) ($d['working_student'] ?? false),
            'school_name' => ! empty($d['working_student']) ? ($d['school_name'] ?? null) : null,
            'enrolled_on' => ! empty($d['working_student']) ? ($d['enrolled_on'] ?? null) : null,
            'resident_tax_single_parent_class' => (int) ($d['resident_tax_single_parent_class'] ?? 0),
            'has_secondary_job_form' => (bool) ($d['has_secondary_job_form'] ?? false),
            'category_changes' => $changes ?: null,
        ]);
    }

    private function saveIncome(Submission $s, array $d): void
    {
        $salary = $d['salary_income_estimate'] ?? null;
        $other = $d['other_income_estimate'] ?? null;
        $s->update([
            'salary_income' => $d['salary_income'] ?? null,
            'salary_income_estimate' => $salary,
            'other_income_estimate' => $other,
            // 合計は単純な足し算（控除額などの税計算ではない。決定 10）
            'total_income_estimate' => ($salary === null && $other === null) ? null : (int) $salary + (int) $other,
        ]);
    }

    private function saveSpouse(Submission $s, array $d): void
    {
        $spouse = Spouse::firstOrNew(['submission_id' => $s->id]);
        $status = (int) ($d['spouse_status'] ?? 0);
        if ($status !== 1) {
            // 配偶者がいない場合は詳細を初期化（本年中の異動ありの場合は異動情報だけ残す）
            $spouse->forceFill(array_fill_keys(['kind', 'name', 'name_kana', 'birthday', 'my_number', 'postal_code', 'prefecture', 'city', 'street', 'income_estimate', 'retirement_income', 'disability_handbook_type', 'disability_issued_on', 'disability_detail', 'remittance_amount', 'remittance_unit', 'remittance_unit_other'], null));
            $spouse->forceFill(['spouse_status' => $status, 'my_number_provided' => false, 'address_same' => true, 'nonresident' => false, 'elderly' => false, 'disability_class' => 0]);
            $spouse->change = $status === 2 ? $this->cleanChange($d['change'] ?? []) : null;
            $spouse->save();

            return;
        }
        $same = ! empty($d['address_same']);
        $nonres = ! empty($d['nonresident']);
        $spouse->forceFill([
            'spouse_status' => 1,
            'kind' => $d['kind'] ?? null,
            'name' => $d['name'] ?? null, 'name_kana' => $d['name_kana'] ?? null, 'birthday' => $d['birthday'] ?? null,
            'address_same' => $same,
            'postal_code' => $same ? null : ($d['postal_code'] ?? null), 'prefecture' => $same ? null : ($d['prefecture'] ?? null),
            'city' => $same ? null : ($d['city'] ?? null), 'street' => $same ? null : ($d['street'] ?? null),
            'income_estimate' => $d['income_estimate'] ?? null, 'retirement_income' => $d['retirement_income'] ?? null,
            'nonresident' => $nonres, 'elderly' => ! empty($d['elderly']),
            'disability_class' => (int) ($d['disability_class'] ?? 0),
            'disability_handbook_type' => $d['disability_handbook_type'] ?? null, 'disability_issued_on' => $d['disability_issued_on'] ?? null, 'disability_detail' => $d['disability_detail'] ?? null,
            'change' => $this->cleanChange($d['change'] ?? []),
            'remittance_amount' => $nonres ? ($d['remittance_amount'] ?? null) : null,
            'remittance_unit' => $nonres ? ($d['remittance_unit'] ?? null) : null,
            'remittance_unit_other' => $nonres ? ($d['remittance_unit_other'] ?? null) : null,
        ]);
        $this->applyMyNumber($spouse, $d);
        $spouse->save();
    }

    private function saveDependents(Submission $s, array $rows): void
    {
        $keep = [];
        foreach (array_values($rows) as $i => $d) {
            $dep = ! empty($d['id']) ? Dependent::where('submission_id', $s->id)->find($d['id']) : null;
            $dep ??= new Dependent(['submission_id' => $s->id]);
            $same = ! empty($d['address_same']);
            $nonres = ! empty($d['nonresident']);
            $oe = $d['other_earner'] ?? [];
            $dep->forceFill([
                'sort_order' => $i,
                'kind' => $d['kind'] ?? null, 'name' => $d['name'] ?? null, 'name_kana' => $d['name_kana'] ?? null,
                'relation' => $d['relation'] ?? null, 'relation_other' => ($d['relation'] ?? null) == 99 ? ($d['relation_other'] ?? null) : null,
                'birthday' => $d['birthday'] ?? null,
                'elderly_class' => (int) ($d['elderly_class'] ?? 0), 'specific_class' => (int) ($d['specific_class'] ?? 0),
                'income_estimate' => $d['income_estimate'] ?? null, 'retirement_income' => $d['retirement_income'] ?? null,
                'nonresident' => $nonres, 'nonresident_reason' => $nonres ? ($d['nonresident_reason'] ?? null) : null,
                'address_same' => $same,
                'postal_code' => $same ? null : ($d['postal_code'] ?? null), 'prefecture' => $same ? null : ($d['prefecture'] ?? null),
                'city' => $same ? null : ($d['city'] ?? null), 'street' => $same ? null : ($d['street'] ?? null),
                'disability_class' => (int) ($d['disability_class'] ?? 0),
                'disability_handbook_type' => $d['disability_handbook_type'] ?? null, 'disability_issued_on' => $d['disability_issued_on'] ?? null, 'disability_detail' => $d['disability_detail'] ?? null,
                'other_earner' => ($d['kind'] ?? null) == 2 && ! empty($oe['name']) ? $this->only($oe, ['name', 'relation', 'relation_other', 'address_same', 'postal_code', 'prefecture', 'city', 'street']) : null,
                'remittance_amount' => $nonres ? ($d['remittance_amount'] ?? null) : null,
                'remittance_unit' => $nonres ? ($d['remittance_unit'] ?? null) : null,
                'remittance_unit_other' => $nonres ? ($d['remittance_unit_other'] ?? null) : null,
                'change' => $this->cleanChange($d['change'] ?? []),
            ]);
            $this->applyMyNumber($dep, $d);
            $dep->save();
            $keep[] = $dep->id;
        }
        Dependent::where('submission_id', $s->id)->whereNotIn('id', $keep)->delete();
    }

    private function saveInsurance(Submission $s, array $d): void
    {
        $s->update([
            'small_biz_kyosai' => $d['small_biz_kyosai'] ?? null, 'dc_corporate' => $d['dc_corporate'] ?? null,
            'dc_individual' => $d['dc_individual'] ?? null, 'disabled_kyosai' => $d['disabled_kyosai'] ?? null,
            'life_under23_dependent' => ! empty($d['under23']['name']) ? $this->only($d['under23'], ['name', 'name_kana', 'birthday', 'relation', 'income_estimate']) : null,
        ]);
        InsuranceItem::where('submission_id', $s->id)->delete();
        foreach (array_keys(InsuranceItem::KINDS) as $kind) {
            foreach (array_values($d['items'][$kind] ?? []) as $i => $row) {
                InsuranceItem::create($this->only($row, ['company_name', 'insurance_type', 'period', 'contractor_name', 'beneficiary_name', 'insured_name', 'payee_name', 'new_old', 'pension_start_on', 'quake_class', 'amount'])
                    + ['submission_id' => $s->id, 'kind' => $kind, 'sort_order' => $i]);
            }
        }
    }

    /** 空欄=変更なし。clear_my_number=削除。番号があれば暗号化して保存 */
    private function applyMyNumber(Spouse|Dependent $model, array $d): void
    {
        if (! empty($d['clear_my_number'])) {
            $model->my_number = null;
        } elseif (! empty($d['my_number'])) {
            $model->my_number = $d['my_number'];
        }
        $model->my_number_provided = (bool) $model->my_number;
    }

    private function cleanChange(array $c): ?array
    {
        return ! empty($c['month']) && ! empty($c['day']) && ! empty($c['reason']) ? $this->only($c, ['month', 'day', 'reason', 'reason_other']) : null;
    }

    /** 同じ従業員の、前年以前の提出済みの申告（最新の年度） */
    public function previous(Submission $s): ?Submission
    {
        $year = $s->campaign->fiscal_year;

        return Submission::with('campaign')
            ->where('user_id', $s->user_id)->where('id', '!=', $s->id)->where('status', 'submitted')
            ->whereHas('campaign', fn ($q) => $q->where('fiscal_year', '<', $year))
            ->get()->sortByDesc(fn ($p) => $p->campaign->fiscal_year)->first();
    }

    /** 前年の内容を読み込む（保険は契約情報だけ。金額は今年分を入力する）。読み込んだら true */
    public function carryOver(Submission $s, string $key): bool
    {
        $prev = $this->previous($s);
        if (! $prev) {
            return false;
        }
        DB::transaction(function () use ($s, $prev, $key) {
            switch ($key) {
                case 'category':
                    $s->update($this->only($prev->getAttributes(), ['no_category', 'disability_class', 'disability_handbook_type', 'disability_issued_on', 'disability_detail', 'single_parent_class', 'working_student', 'school_name', 'enrolled_on', 'resident_tax_single_parent_class', 'has_secondary_job_form']) + ['category_changes' => null]);
                    break;
                case 'spouse':
                    Spouse::where('submission_id', $s->id)->delete();
                    if ($prev->spouse) {
                        $copy = $prev->spouse->replicate();
                        $copy->submission_id = $s->id;
                        $copy->change = null;      // 異動は今年の分を入力し直す
                        $copy->save();
                    }
                    break;
                case 'dependents':
                    Dependent::where('submission_id', $s->id)->delete();
                    foreach ($prev->dependents as $dep) {
                        $copy = $dep->replicate();
                        $copy->submission_id = $s->id;
                        $copy->change = null;
                        $copy->save();
                    }
                    break;
                case 'insurance':
                    InsuranceItem::where('submission_id', $s->id)->delete();
                    foreach ($prev->insuranceItems as $item) {
                        $copy = $item->replicate();
                        $copy->submission_id = $s->id;
                        $copy->amount = null;      // 金額は今年分を入力する
                        $copy->save();
                    }
                    $s->update(['small_biz_kyosai' => null, 'dc_corporate' => null, 'dc_individual' => null, 'disabled_kyosai' => null, 'life_under23_dependent' => $prev->life_under23_dependent]);
                    break;
            }
        });

        return true;
    }

    /** 提出: 社員情報のマイナンバーを申請へ暗号化コピーし、その時点の内容をスナップショットとして保存 */
    public function submit(Submission $s, ?User $actor = null, ?array $confirmation = null): void
    {
        DB::transaction(function () use ($s, $actor, $confirmation) {
            $profile = $s->user_id ? EmployeeProfile::where('user_id', $s->user_id)->first() : null;
            if ($profile?->my_number) {
                $s->my_number = $profile->my_number;     // encrypted キャストで暗号化して保存
                $s->my_number_provided = true;
            }
            $s->status = 'submitted';
            $s->submitted_at = now();
            $s->current_step = 7;
            // 差戻しからの再提出: 差戻しの表示は消す（履歴は events に残る）
            $s->return_comment = null;
            $s->return_steps = null;
            $s->returned_at = null;
            $s->returned_by_name = null;
            // 代理入力の提出: 本人確認の記録（任意）を残す
            $meta = null;
            if ($confirmation !== null && $s->entered_by_type === 'proxy') {
                $s->proxy_confirmation = [
                    'paper' => (bool) ($confirmation['paper'] ?? false),
                    'person' => (bool) ($confirmation['person'] ?? false),
                    'note' => $confirmation['note'] ?? null,
                    'confirmed_by' => $actor?->name,
                    'confirmed_at' => now('Asia/Tokyo')->format('Y-m-d H:i'),
                ];
                $meta = ['proxy' => true, 'confirmation' => $s->proxy_confirmation];
            } elseif ($s->entered_by_type === 'proxy') {
                $meta = ['proxy' => true];
            }
            $s->save();
            $s->refresh()->load(['campaign', 'spouse', 'dependents', 'insuranceItems']);
            $s->snapshot = $this->snapshot($s);   // マイナンバー（hidden）は含めない
            $s->save();
            $this->log($s, 'submitted', $actor, null, $meta);
        });
    }

    public function snapshot(Submission $s): array
    {
        $c = $s->campaign;

        return [
            'campaign' => $c->only(['id', 'company_id', 'fiscal_year', 'title', 'tax_office_name', 'municipality_name', 'payer_name', 'payer_corporate_number', 'payer_postal_code', 'payer_prefecture', 'payer_city', 'payer_street']),
            'submission' => (clone $s)->withoutRelations()->toArray(),
            'spouse' => $s->spouse?->toArray(),
            'dependents' => $s->dependents->map->toArray()->all(),
            'insurance_items' => $s->insuranceItems->map->toArray()->all(),
            'submitted_at' => $s->submitted_at?->toIso8601String(),
        ];
    }

    /** 取り下げ: 提出済み → 入力中に戻して編集できるようにする */
    public function withdraw(Submission $s, ?User $actor = null): void
    {
        $s->update(['status' => 'draft', 'submitted_at' => null]);
        $this->log($s, 'withdrawn', $actor);
    }

    /**
     * 差戻し（総務 → 従業員）: コメントと、直してほしい工程（任意）を付けて、従業員が修正できる状態に戻す。
     * 差戻しは締切後でも、その従業員だけ修正できる（SubmissionController / MyNenchoController::editable）。
     *
     * @param  int[]  $steps  直してほしい工程の番号（1〜6）
     */
    public function returnToEmployee(Submission $s, User $actor, ?string $comment, array $steps = []): void
    {
        $steps = array_values(array_unique(array_filter(array_map('intval', $steps), fn ($n) => $n >= 1 && $n <= 6)));
        sort($steps);
        DB::transaction(function () use ($s, $actor, $comment, $steps) {
            $s->update([
                'status' => 'returned',
                'return_comment' => $comment ?: null,
                'return_steps' => $steps ?: null,
                'returned_at' => now(),
                'returned_by_name' => $actor->name,
                'submitted_at' => null,
                'current_step' => $steps ? min($steps) : 1,
            ]);
            $this->log($s, 'returned', $actor, $comment, ['steps' => $steps]);
        });
    }

    /** 代理入力の開始（総務が従業員の分を入力し始める）。入力者を「総務の代理」にして履歴に残す */
    public function markProxy(Submission $s, User $actor, bool $log = true): void
    {
        $s->update(['entered_by_user_id' => $actor->id, 'entered_by_type' => 'proxy']);
        // 履歴には、代理入力の開始を 1 回だけ残す（同じ人が何度開いても増やさない）
        if ($log && ! SubmissionEvent::where('submission_id', $s->id)->where('action', 'proxy_started')->where('actor_user_id', $actor->id)->exists()) {
            $this->log($s, 'proxy_started', $actor);
        }
    }

    /** 操作履歴を残す */
    public function log(Submission $s, string $action, ?User $actor = null, ?string $comment = null, ?array $meta = null): void
    {
        $actor ??= $s->user;
        SubmissionEvent::create([
            'submission_id' => $s->id,
            'action' => $action,
            'actor_user_id' => $actor?->id,
            'actor_name' => $actor?->name ?? '（不明）',
            'comment' => $comment,
            'meta' => $meta,
            'created_at' => now(),
        ]);
    }
}
