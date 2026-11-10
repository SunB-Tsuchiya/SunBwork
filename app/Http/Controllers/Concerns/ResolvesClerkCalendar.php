<?php

namespace App\Http\Controllers\Concerns;

use App\Support\ClerkCalendarKind;
use Illuminate\Database\Eloquent\Model;

/**
 * クラーク系カレンダー（予定・週の投稿・色・繰り返しルール・リマインダー）のコントローラー用。
 * 操作対象のカレンダーの種類（ClerkCalendarKind）を決め、会社と種類が合わないデータは 404 にする。
 * 使うクラスは、会社 ID を返す companyId() を持っていること（従来どおり）。
 * サン・ブレーン（一般会社でない）の種類は空文字で、従来の「会社単位」の動作と同じ。
 */
trait ResolvesClerkCalendar
{
    protected function calendarKind(): string
    {
        return ClerkCalendarKind::forRequest(request());
    }

    /** 画面を戻すときのルート名の前半。営業のカレンダーは 'suna.sales'、それ以外（クラーク）は従来どおり 'clerk' */
    protected function calendarRoutePrefix(): string
    {
        return request()->routeIs('suna.sales.*') ? 'suna.sales' : 'clerk';
    }

    /** 他社のデータ、または他のカレンダー（総務/経理/営業）のデータは、存在しない扱い（404） */
    protected function abortUnlessInCalendar(Model $model): void
    {
        abort_unless(
            (int) $model->company_id === $this->companyId() && (string) $model->calendar_kind === $this->calendarKind(),
            404
        );
    }
}
