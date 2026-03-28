<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserMonthlySchedule extends Model
{
    protected $fillable = ['user_id', 'year_month', 'schedule'];

    protected $casts = ['schedule' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 指定日（YYYY-MM-DD）の worktype_id を返す。なければ null。
     */
    public static function worktypeIdForDate(int $userId, string $date): ?int
    {
        $ym = substr($date, 0, 7);
        $dd = substr($date, 8, 2);

        $monthly = static::where('user_id', $userId)->where('year_month', $ym)->first();
        if (! $monthly) {
            return null;
        }

        return isset($monthly->schedule[$dd]) ? (int) $monthly->schedule[$dd] : null;
    }
}
