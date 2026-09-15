export const scheduleTypes = [
    { value: 'monthly_day', label: '毎月の日付' },
    { value: 'month_end', label: '月末' },
    { value: 'monthly_weekday', label: '毎月の曜日' },
    { value: 'custom_dates', label: '日付を指定' },
];
export const weekdays = ['日', '月', '火', '水', '木', '金', '土'];
export const changeNotice = '今後の未完了・個別変更していない予定に反映します。過去・完了済み・個別変更済みの予定は残ります。';
export function scheduleLabel(rule) {
    if (rule.recurrence === 'monthly_day') return `毎月${rule.day_of_month}日`;
    if (rule.recurrence === 'month_end') return '毎月末日';
    if (rule.recurrence === 'monthly_weekday')
        return `毎月${Number(rule.ordinal) === 0 ? '最終' : `第${rule.ordinal}`}${weekdays[rule.day_of_week]}曜日`;
    return `日付指定（${rule.custom_dates?.length ?? 0}日）`;
}
