<?php

namespace App\Http\Requests\SalesAnalysis;

use App\Services\SalesAnalysis\SalesDepartments;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UploadSalesWorkbookRequest extends FormRequest
{
    public function authorize(): bool
    {
        // アクセス制御は sales_analysis ミドルウェアで実施済み
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'extensions:xlsx',
                'mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/zip',
                'max:10240', // 暫定10MB（PLAN 3.4）
            ],
            'department_key' => ['required', 'string', Rule::in(SalesDepartments::ENABLED_KEYS)],
            'source_type' => ['required', 'string', 'in:annual,monthly'],
            'source_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'source_month' => ['required_if:source_type,monthly', 'nullable', 'integer', 'min:1', 'max:12'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $file = $this->file('file');
            if (! $file || ! $file->isValid()) {
                return;
            }

            // xlsxはZIP形式。先頭シグネチャで簡易検証する（拡張子偽装対策）
            $handle = fopen($file->getRealPath(), 'rb');
            $magic = $handle ? fread($handle, 4) : '';
            if ($handle) {
                fclose($handle);
            }

            if (! in_array($magic, ["PK\x03\x04", "PK\x05\x06"], true)) {
                $validator->errors()->add('file', 'ファイル形式が不正です（xlsxとして認識できません）。');
            }
        });
    }
}
