<?php

namespace App\Http\Requests\SunA\GeneralAffairs;

use App\Models\SunA\GeneralAffairs\ApplicationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplicationTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'feature_key' => ['nullable', Rule::in(array_keys(ApplicationType::FEATURES))],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => '申請の名前',
            'description' => '説明',
            'feature_key' => '連携する機能',
            'sort_order' => '並び順',
            'is_active' => '状態',
        ];
    }
}
