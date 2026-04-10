<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserMonthlyBreak extends Model
{
    protected $table = 'user_monthly_breaks';

    protected $fillable = ['user_id', 'year_month', 'schedule'];

    protected $casts = ['schedule' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 指定日（YYYY-MM-DD）の休憩設定を返す。
     * 戻り値: ['start' => 'HH:MM', 'end' => 'HH:MM'] または null（設定なし）
     */
    public static function breakForDate(int $userId, string $date): ?array
    {
        $ym = substr($date, 0, 7);
        $dd = substr($date, 8, 2);

        $monthly = static::where('user_id', $userId)->where('year_month', $ym)->first();
        if (! $monthly) {
            return null;
        }

        $entry = $monthly->schedule[$dd] ?? null;
        if (! $entry || empty($entry['start']) || empty($entry['end'])) {
            return null;
        }

        return ['start' => $entry['start'], 'end' => $entry['end']];
    }
}
