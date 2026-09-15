<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClerkScheduleRuleRequest extends FormRequest
{
    public const COLORS = ['indigo', 'blue', 'cyan', 'teal', 'green', 'yellow', 'orange', 'red', 'pink', 'purple', 'gray'];

    // 認可はClerkルートグループのミドルウェアで行う。
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'color_key' => ['required', Rule::in(self::COLORS)],
            'recurrence' => 'required|in:monthly_day,month_end,monthly_weekday,custom_dates',
            'day_of_month' => 'exclude_unless:recurrence,monthly_day|required|integer|min:1|max:31',
            'ordinal' => 'exclude_unless:recurrence,monthly_weekday|required|integer|min:0|max:5',
            'day_of_week' => 'exclude_unless:recurrence,monthly_weekday|required|integer|min:0|max:6',
            'custom_dates' => 'exclude_unless:recurrence,custom_dates|required|array|min:1|max:366',
            'custom_dates.*' => ['required', 'date_format:Y-m-d', 'distinct', 'after_or_equal:starts_on',
                'before_or_equal:2100-12-31', Rule::when($this->filled('ends_on'), ['before_or_equal:ends_on'])],
            'starts_on' => 'required|date_format:Y-m-d|after_or_equal:1900-01-01|before_or_equal:2100-12-31',
            'ends_on' => 'nullable|date_format:Y-m-d|after_or_equal:starts_on|before_or_equal:2100-12-31',
            'is_active' => 'required|boolean',
        ];
    }

    public function attributes(): array
    {
        return ['title' => 'タイトル', 'color_key' => '色', 'starts_on' => '適用開始日', 'ends_on' => '適用終了日',
            'custom_dates' => '指定日', 'custom_dates.*' => '指定日', 'day_of_month' => '日付', 'ordinal' => '週指定', 'day_of_week' => '曜日'];
    }

    public function payload(): array
    {
        $data = $this->validated();
        foreach (['description', 'ends_on', 'day_of_month', 'ordinal', 'day_of_week', 'custom_dates'] as $field) {
            $data[$field] ??= null;
        }
        foreach (['description', 'ends_on'] as $field) {
            if ($data[$field] === '') {
                $data[$field] = null;
            }
        }
        if ($data['custom_dates']) {
            sort($data['custom_dates']);
        }

        return $data;
    }
}
