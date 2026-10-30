<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClerkCalendarReminderRequest extends FormRequest
{
    public const COLORS = ['indigo', 'blue', 'cyan', 'teal', 'green', 'yellow', 'orange', 'red', 'pink', 'purple', 'gray'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:10000', function ($attribute, $value, $fail) {
                $text = trim(strip_tags((string) $value));
                if ($text === '') {
                    $fail('リマインド内容を入力してください。');
                } elseif (mb_strlen($text) > 1000) {
                    $fail('リマインド内容は1000文字以内で入力してください。');
                }
            }],
            'target_type' => ['required', Rule::in(['all', 'employees_only', 'individual'])],
            'target_block' => ['nullable', Rule::in(['all', 'honsha', 'odai'])],
            'user_ids' => ['required_if:target_type,individual', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'color_key' => ['required', Rule::in(self::COLORS)],
            'starts_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:2100-12-31'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on', 'before_or_equal:2100-12-31'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'content' => 'リマインド内容',
            'color_key' => '色',
            'target_type' => '宛先',
            'user_ids' => '宛先ユーザー',
            'starts_on' => '表示開始日',
            'ends_on' => '表示終了日',
            'is_active' => '状態',
        ];
    }
}
