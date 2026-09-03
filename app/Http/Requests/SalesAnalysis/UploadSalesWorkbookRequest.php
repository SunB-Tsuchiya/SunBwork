<?php

namespace App\Http\Requests\SalesAnalysis;

use App\Http\Requests\SalesAnalysis\Concerns\ValidatesXlsxFile;
use App\Services\SalesAnalysis\SalesDepartments;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UploadSalesWorkbookRequest extends FormRequest
{
    use ValidatesXlsxFile;

    public function authorize(): bool
    {
        // アクセス制御は sales_analysis ミドルウェアで実施済み
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => $this->xlsxFileRules(),
            'department_key' => ['required', 'string', Rule::in(SalesDepartments::ENABLED_KEYS)],
            'source_type' => ['required', 'string', 'in:annual,monthly,range'],
            'source_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            // monthly: 対象月。range: 開始月
            'source_month' => ['required_if:source_type,monthly,range', 'nullable', 'integer', 'min:1', 'max:12'],
            'source_month_end' => ['required_if:source_type,range', 'nullable', 'integer', 'min:1', 'max:12', 'gte:source_month'],
            // 検証エラーのある受注を明示的に除外して再検証する際に使う（ユーザーが画面で選択した受注No）
            'excluded_order_numbers' => ['sometimes', 'array'],
            'excluded_order_numbers.*' => ['string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->validateXlsxMagicBytes($validator));
    }
}
