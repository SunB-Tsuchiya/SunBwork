<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MeetingDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'recurrence' => ['required', 'in:weekly,biweekly,monthly,custom_dates'],
            'day_of_week' => ['exclude_if:recurrence,custom_dates', 'required', 'integer', 'min:0', 'max:6'],
            'week_of_month' => ['exclude_unless:recurrence,monthly', 'required', 'integer', 'min:1', 'max:5'],
            'custom_dates' => ['exclude_unless:recurrence,custom_dates', 'required', 'array', 'min:1'],
            'custom_dates.*' => ['date_format:Y-m-d', 'distinct'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'members' => ['required', 'array', 'min:1'],
            'members.*' => ['integer', 'distinct', 'exists:users,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'タイトル',
            'recurrence' => '繰り返し',
            'day_of_week' => '曜日',
            'week_of_month' => '週指定',
            'custom_dates' => '開催日',
            'custom_dates.*' => '開催日',
            'start_time' => '開始時刻',
            'end_time' => '終了時刻',
            'members' => '参加メンバー',
            'members.*' => '参加メンバー',
        ];
    }
}
