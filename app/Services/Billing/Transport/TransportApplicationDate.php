<?php

namespace App\Services\Billing\Transport;

use App\Models\TransportExpense;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** The document date is chosen independently of the actual submission timestamp. */
class TransportApplicationDate
{
    public static function initial(string $month): string
    {
        [$start, $end] = TransportExpense::monthRange($month);
        return max($start, min($end, now('Asia/Tokyo')->toDateString()));
    }

    public static function validate(string $date, string $month): void
    {
        Validator::make(['billing_date' => $date], ['billing_date' => ['required', 'date_format:Y-m-d']],
            ['billing_date.*' => '申請日を日付として入力してください。'])->validate();
        [$start, $end] = TransportExpense::monthRange($month);
        if ($date < $start || $date > $end) {
            throw ValidationException::withMessages(['billing_date' => '申請日は対象月の'.$start.'〜'.$end.'から選んでください。']);
        }
    }
}
