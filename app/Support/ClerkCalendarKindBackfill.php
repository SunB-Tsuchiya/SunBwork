<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * クラーク系カレンダーの calendar_kind の移行（SUNASALES_PLAN1.md §14）。
 * 一般会社（company_type='general'。サンエー印刷）の既存の行だけ、空文字（従来のカレンダー）を 'soumu' に埋める。
 * サン・ブレーン等の行は触らない。何度実行しても、結果は同じ（空文字の行だけが対象）。
 */
class ClerkCalendarKindBackfill
{
    public const TABLES = [
        'clerk_events',
        'clerk_week_posts',
        'clerk_schedule_rules',
        'clerk_calendar_reminders',
        'clerk_calendar_colors',
    ];

    /** @return int 更新した行数 */
    public static function run(): int
    {
        $updated = 0;
        foreach (self::TABLES as $name) {
            $updated += DB::table($name)
                ->where('calendar_kind', ClerkCalendarKind::LEGACY)
                ->whereIn('company_id', fn ($q) => $q->select('id')->from('companies')->where('company_type', 'general'))
                ->update(['calendar_kind' => ClerkCalendarKind::SOUMU]);
        }

        return $updated;
    }
}
