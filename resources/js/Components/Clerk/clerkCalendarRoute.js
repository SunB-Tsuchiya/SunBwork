import { usePage } from '@inertiajs/vue3';

// クラーク系カレンダーのルート名を、いまのカレンダーに合わせて作る。
// 総務・経理は 'clerk'、営業は 'suna.sales'（サーバーが共有する clerkCalendarRoutePrefix。共有されていなければ 'clerk'）。
// 使い方: calRoute('calendar.events.index') → route('clerk.calendar.events.index') または route('suna.sales.calendar.events.index')
export const calendarRoutePrefix = () => usePage().props.clerkCalendarRoutePrefix || 'clerk';

export const calRoute = (name, params = undefined) => route(`${calendarRoutePrefix()}.${name}`, params);
