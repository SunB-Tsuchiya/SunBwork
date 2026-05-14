<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkRecord extends Model
{
    protected $fillable = [
        'user_id', 'company_id', 'department_id', 'worktype_id',
        'date', 'start_time', 'end_time',
        'scheduled_start', 'scheduled_end',
        'overtime_minutes', 'early_leave_minutes', 'note',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function worktype()
    {
        return $this->belongsTo(Worktype::class);
    }

    /**
     * 時刻文字列 "HH:MM" を分に変換（夜勤などで終業が翌日になる場合を考慮）
     */
    public static function timeToMinutes(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', $time));
        return $h * 60 + $m;
    }

    /**
     * overtime/early_leave を計算して属性にセットする
     *
     * 前倒し出勤（規定開始より早く来た分）は残業に含めない。
     * 残業・早退の判断は「規定終業時刻との差分」のみで行う。
     */
    public function calcOvertime(): void
    {
        if (!$this->scheduled_start || !$this->scheduled_end || !$this->start_time || !$this->end_time) {
            $this->overtime_minutes    = 0;
            $this->early_leave_minutes = 0;
            return;
        }

        $scheduledStart = self::timeToMinutes(substr($this->scheduled_start, 0, 5));
        $scheduledEnd   = self::timeToMinutes(substr($this->scheduled_end,   0, 5));
        $actualStart    = self::timeToMinutes(substr($this->start_time,      0, 5));
        $actualEnd      = self::timeToMinutes(substr($this->end_time,        0, 5));

        // 夜勤対応: 終業が始業より早い場合は翌日扱い
        if ($scheduledEnd < $scheduledStart) $scheduledEnd += 1440;
        if ($actualEnd    < $actualStart)    $actualEnd    += 1440;

        // 前倒し開始は残業に含めない: 規定開始時刻より早い分はカウントしない
        $effectiveStart   = max($actualStart, $scheduledStart);
        $scheduledMinutes = $scheduledEnd - $scheduledStart;
        $actualMinutes    = $actualEnd - $effectiveStart;
        $diff             = $actualMinutes - $scheduledMinutes;

        $this->overtime_minutes    = max(0, $diff);
        $this->early_leave_minutes = max(0, -$diff);
    }

    /**
     * 勤務時間（分）を計算。終業 < 始業 なら翌日扱い（夜勤対応）
     */
    private static function scheduledDuration(string $start, string $end): int
    {
        $s = self::timeToMinutes(substr($start, 0, 5));
        $e = self::timeToMinutes(substr($end,   0, 5));
        if ($e < $s) $e += 1440; // 翌日
        return $e - $s;
    }
}
