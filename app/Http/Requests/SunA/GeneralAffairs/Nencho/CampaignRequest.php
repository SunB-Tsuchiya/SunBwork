<?php

namespace App\Http\Requests\SunA\GeneralAffairs\Nencho;

use App\Models\SunA\GeneralAffairs\Nencho\Campaign;
use App\Support\SunA\GeneralAffairs\Nencho\NenchoRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'payer_corporate_number' => NenchoRules::digits($this->input('payer_corporate_number')),
            'payer_postal_code' => NenchoRules::digits($this->input('payer_postal_code')),
            // 会社側情報（税務署・支払者）は国の様式（全角）にそろえる
            'tax_office_name' => NenchoRules::zenkakuText($this->input('tax_office_name')),
            'municipality_name' => NenchoRules::zenkakuText($this->input('municipality_name')),
            'payer_name' => NenchoRules::zenkakuText($this->input('payer_name')),
            'payer_city' => NenchoRules::zenkakuText($this->input('payer_city')),
            'payer_street' => NenchoRules::zenkakuText($this->input('payer_street')),
        ]);
    }

    public function rules(): array
    {
        /** @var Campaign|null $campaign */
        $campaign = $this->route('campaign');
        // 受付は会社ごと。作成時は入力された会社、編集時は受付の会社（変更不可）
        $companyId = $campaign?->company_id ?? $this->input('company_id');

        return [
            'company_id' => $campaign ? ['nullable'] : ['required', 'integer', Rule::exists('companies', 'id')],
            'fiscal_year' => [
                'required', 'integer', 'min:2020', 'max:2100',
                Rule::unique('suna_ga_nencho_campaigns', 'fiscal_year')
                    ->where('company_id', $companyId)
                    ->ignore($campaign?->id),
            ],
            'title' => ['required', 'string', 'max:100'],
            'opens_on' => ['required', 'date_format:Y-m-d'],
            'due_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:opens_on'],
            'message' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(array_keys(Campaign::STATUSES))],
            'tax_office_name' => ['nullable', 'string', 'max:10'],
            'municipality_name' => ['nullable', 'string', 'max:35'],
            'payer_name' => ['nullable', 'string', 'max:30'],
            'payer_corporate_number' => ['nullable', 'regex:/^\d{13}$/'],
            'payer_postal_code' => ['nullable', 'regex:/^\d{7}$/'],
            'payer_prefecture' => ['nullable', Rule::in(NenchoRules::PREFECTURES)],
            'payer_city' => ['nullable', 'string', 'max:35'],
            'payer_street' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return [
            'company_id' => '会社',
            'fiscal_year' => '年度（西暦）',
            'title' => 'タイトル',
            'opens_on' => '受付開始日',
            'due_on' => '提出締切日',
            'message' => '案内文',
            'status' => '状態',
            'tax_office_name' => '所轄税務署',
            'municipality_name' => '所轄市区町村',
            'payer_name' => '給与の支払者の名称',
            'payer_corporate_number' => '法人番号',
            'payer_postal_code' => '支払者の郵便番号',
            'payer_prefecture' => '支払者の都道府県',
            'payer_city' => '支払者の市区町村',
            'payer_street' => '支払者の丁番地以降',
        ];
    }

    public function messages(): array
    {
        return [
            'fiscal_year.unique' => 'この年度の受付はすでに作成されています。',
            'payer_corporate_number.regex' => '法人番号は半角数字13桁で入力してください。',
            'payer_postal_code.regex' => '郵便番号は半角数字7桁で入力してください。',
        ];
    }
}
