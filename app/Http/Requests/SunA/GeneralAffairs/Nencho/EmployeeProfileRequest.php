<?php

namespace App\Http\Requests\SunA\GeneralAffairs\Nencho;

use App\Support\SunA\GeneralAffairs\Nencho\NenchoRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => NenchoRules::fullWidthSpace($this->input('name')),
            'name_kana' => NenchoRules::kana($this->input('name_kana')),
            'household_head_name' => NenchoRules::fullWidthSpace($this->input('household_head_name')),
            'postal_code' => NenchoRules::digits($this->input('postal_code')),
            'employee_number' => NenchoRules::alnum($this->input('employee_number')),
            'my_number' => NenchoRules::digits($this->input('my_number')),
            'birthday' => NenchoRules::date($this->input('birthday')),
            // 住所・最寄り駅は国の様式（全角）にそろえる
            'city' => NenchoRules::zenkakuText($this->input('city')),
            'street' => NenchoRules::zenkakuText($this->input('street')),
            'nearest_station' => NenchoRules::zenkakuText($this->input('nearest_station')),
            'household_head_relation_other' => NenchoRules::zenkakuText($this->input('household_head_relation_other')),
        ]);
    }

    public function rules(): array
    {
        // 氏名は「姓 + 全角スペース + 名」（国の様式の制約）
        $fullName = ['nullable', 'string', 'min:3', 'max:30', 'regex:/^\S+　\S+$/u'];

        return [
            'name' => $fullName,
            'name_kana' => ['nullable', 'string', 'min:3', 'max:60', 'regex:/^[ァ-ヶー]+　[ァ-ヶー]+$/u'],
            'birthday' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before:today'],
            'postal_code' => ['nullable', 'regex:/^\d{7}$/'],
            'prefecture' => ['nullable', Rule::in(NenchoRules::PREFECTURES)],
            'city' => ['nullable', 'string', 'max:35'],
            'street' => ['nullable', 'string', 'max:100'],
            'household_head_name' => $fullName,
            'household_head_relation' => ['nullable', Rule::in(array_keys(NenchoRules::HOUSEHOLD_RELATIONS))],
            'household_head_relation_other' => ['nullable', 'required_if:household_head_relation,99', 'string', 'max:10'],
            'employee_number' => ['nullable', 'regex:/^[A-Za-z0-9]{1,20}$/'],
            'nearest_station' => ['nullable', 'string', 'max:50'],
            // マイナンバー: 空欄＝変更なし。入力・削除には「マイナンバーを見る」権限が必要（コントローラーで確認）
            'my_number' => ['nullable', 'regex:/^\d{12}$/'],
            'clear_my_number' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => '氏名',
            'name_kana' => 'フリガナ',
            'birthday' => '生年月日',
            'postal_code' => '郵便番号',
            'prefecture' => '都道府県',
            'city' => '市区町村',
            'street' => '丁番地以降',
            'household_head_name' => '世帯主の氏名',
            'household_head_relation' => '世帯主との続柄',
            'household_head_relation_other' => '続柄（その他）',
            'employee_number' => '社員番号',
            'nearest_station' => '最寄り駅',
            'my_number' => 'マイナンバー',
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => '氏名は「姓」と「名」の間にスペースを入れてください。',
            'household_head_name.regex' => '氏名は「姓」と「名」の間にスペースを入れてください。',
            'name_kana.regex' => 'フリガナは全角カナで、「姓」と「名」の間にスペースを入れてください。',
            'postal_code.regex' => '郵便番号は半角数字7桁で入力してください。',
            'employee_number.regex' => '社員番号は半角英数字20文字以内で入力してください。',
            'my_number.regex' => 'マイナンバーは半角数字12桁で入力してください。',
            'household_head_relation_other.required_if' => '続柄が「その他」の場合は内容を入力してください。',
        ];
    }
}
