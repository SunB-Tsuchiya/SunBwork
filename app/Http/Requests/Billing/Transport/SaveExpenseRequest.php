<?php

namespace App\Http\Requests\Billing\Transport;

use App\Models\Department;
use App\Services\Billing\Transport\TransportCompany;
use App\Models\TransportExpense;
use App\Models\TransportExpenseItem;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 交通費（月単位）の保存。画面で整形した値をサーバーでも同じ規則で整える。
 * 発生日は対象月の月初〜月末の範囲内のみ。
 */
class SaveExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))->map(function ($it) {
            $it = is_array($it) ? $it : [];
            foreach (['destination', 'purpose_text', 'station_from', 'station_to'] as $k) {
                $it[$k] = self::zenkaku($it[$k] ?? null);
            }
            $it['amount'] = self::yen($it['amount'] ?? 0);
            $it['base_amount'] = isset($it['base_amount']) && $it['base_amount'] !== '' ? self::yen($it['base_amount']) : null;
            $it['auto_double'] = filter_var($it['auto_double'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $it['via_stations'] = self::viaList($it['via_stations'] ?? []);
            $it['companion_ids'] = collect(is_array($it['companion_ids'] ?? null) ? $it['companion_ids'] : [])
                ->filter(fn ($v) => is_numeric($v))->map(fn ($v) => (int) $v)->unique()->values()->all();
            return $it;
        })->all();

        $this->merge(['items' => $items]);
    }

    public function rules(): array
    {
        return [
            'billing_month'           => ['required', 'date_format:Y-m'],
            'charge_department_id'    => ['nullable', 'integer'],
            'items'                   => ['required', 'array', 'min:1', 'max:200'],
            'items.*.occurrence_date' => ['nullable', 'date_format:Y-m-d'],
            'items.*.destination'     => ['nullable', 'string', 'max:100'],
            'items.*.purpose'         => ['required', Rule::in(array_keys(TransportExpenseItem::PURPOSE_LABELS))],
            'items.*.purpose_text'    => ['nullable', 'string', 'max:100'],
            'items.*.station_from'    => ['nullable', 'string', 'max:100'],
            'items.*.station_to'      => ['nullable', 'string', 'max:100'],
            'items.*.fare_type'       => ['required', 'in:ic,ticket'],
            'items.*.amount'          => ['required', 'integer', 'min:0', 'max:9999999'],
            'items.*.base_amount'     => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'items.*.auto_double'     => ['boolean'],
            'items.*.via_stations'    => ['nullable', 'array', 'max:' . TransportExpenseItem::MAX_VIA],
            'items.*.via_stations.*'  => ['string', 'max:100'],
            'items.*.companion_ids'   => ['nullable', 'array', 'max:' . TransportExpenseItem::MAX_COMPANIONS],
            'items.*.companion_ids.*' => ['integer'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            // 請求先の部門は、その会社に登録された有効な部署のみ（未指定 = 共通）
            $deptId = $this->input('charge_department_id');
            if ($deptId !== null && $deptId !== '' && !Department::where('id', $deptId)->where('active', true)
                ->where('company_id', TransportCompany::idFor($this->user()))->exists()) {
                $v->errors()->add('charge_department_id', '部門は、会社に登録された部署から選んでください。');
            }
            // 同行者は、同じ会社の社員のみ（本人・ゴーストは選べない）
            $companyId = TransportCompany::idFor($this->user());
            $allIds = collect($this->input('items', []))->flatMap(fn ($it) => $it['companion_ids'] ?? [])->unique()->values();
            $valid = $allIds->isEmpty() ? collect() : User::whereIn('id', $allIds)->where('company_id', $companyId)
                ->where('is_ghost', false)->whereNull('ghost_owner_id')->where('id', '!=', $this->user()->id)->pluck('id');
            foreach ($this->input('items', []) as $i => $it) {
                if (collect($it['companion_ids'] ?? [])->diff($valid)->isNotEmpty()) {
                    $v->errors()->add("items.$i.companion_ids", ($i + 1) . '行目の同行者は、同じ会社の社員から選んでください。');
                }
            }
            [$start, $end] = TransportExpense::monthRange($this->input('billing_month'));
            foreach ($this->input('items', []) as $i => $it) {
                $d = $it['occurrence_date'] ?? null;
                if ($d && ($d < $start || $d > $end)) {
                    $v->errors()->add("items.$i.occurrence_date", ($i + 1) . '行目の発生日は ' . $start . ' 〜 ' . $end . ' の範囲で入力してください。');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'items.*.occurrence_date.date_format' => ':position行目の発生日を日付として読み取れません。「0422」「4/22」のように入力してください。',
            'items.*.amount.integer' => ':position行目の金額は数字で入力してください。',
            'items.*.amount.min'     => ':position行目の金額は 0 円以上で入力してください。',
            'items.*.amount.max'     => ':position行目の金額が大きすぎます。',
            'items.*.base_amount.integer' => ':position行目の片道の金額は数字で入力してください。',
            'items.*.destination.max'  => ':position行目の行先は 100 文字以内で入力してください。',
            'items.*.station_from.max' => ':position行目の出発駅は 100 文字以内で入力してください。',
            'items.*.station_to.max'   => ':position行目の到着駅は 100 文字以内で入力してください。',
            'items.*.purpose_text.max' => ':position行目の用件の内容は 100 文字以内で入力してください。',
            'items.*.via_stations.max'  => ':position行目の経由地は ' . TransportExpenseItem::MAX_VIA . ' か所までです。',
            'items.*.companion_ids.max' => ':position行目の同行者は ' . TransportExpenseItem::MAX_COMPANIONS . ' 人までです。',
            'items.max' => '明細は 200 行までです。',
            'items.min' => '明細を 1 行以上入力してください。',
        ];
    }

    public function attributes(): array
    {
        return [
            'billing_month' => '対象月',
            'items' => '明細',
        ];
    }

    /** 日本語テキスト: 半角英数・記号・カナを全角、連続空白は全角 1 つ */
    public static function zenkaku(?string $s): ?string
    {
        if ($s === null || $s === '') {
            return $s;
        }
        $s = mb_convert_kana($s, 'ASKV', 'UTF-8');
        return trim(preg_replace('/[\s　]+/u', '　', $s));
    }

    /** 経由地: 駅名を整え、空は除き、重複する連続は 1 つに（上限を超えたぶんは、検証で弾くので、切らない） */
    public static function viaList(mixed $v): array
    {
        $out = [];
        foreach (is_array($v) ? $v : [] as $s) {
            $s = self::zenkaku(is_string($s) ? $s : null);
            if ($s !== null && $s !== '') {
                $out[] = $s;
            }
        }
        return $out;
    }

    /** 金額: 全角・カンマ・「円」を取り除いて整数に（数字として読めなければ元の値を返してバリデーションで弾く） */
    public static function yen(mixed $v): mixed
    {
        if (is_int($v)) {
            return $v;
        }
        $s = mb_convert_kana((string) $v, 'n', 'UTF-8');
        $s = preg_replace('/[,，、\s円¥￥]/u', '', $s);
        return preg_match('/^\d+$/', $s) ? (int) $s : ($s === '' ? 0 : $v);
    }
}
