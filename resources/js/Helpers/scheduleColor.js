/**
 * 締め切りステータスに応じた色を返す。
 * 「間近」= 残り営業日3日以内（土日除く）。
 */
export function scheduleStatusColor(endDateStr, isCompleted) {
    if (isCompleted) {
        return { bg: '#dcfce7', border: '#15803d', text: '#14532d' }; // 緑: 完了
    }
    if (!endDateStr) {
        return { bg: '#dbeafe', border: '#1d4ed8', text: '#1e3a8a' }; // 青: 通常
    }

    const parts = String(endDateStr).split('T')[0].split('-').map(Number);
    if (parts.length < 3 || parts.some(isNaN)) {
        return { bg: '#dbeafe', border: '#1d4ed8', text: '#1e3a8a' };
    }
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const end = new Date(parts[0], parts[1] - 1, parts[2]);

    if (end < today) {
        return { bg: '#fee2e2', border: '#b91c1c', text: '#7f1d1d' }; // 赤: 超過
    }

    // 残り営業日数（明日〜end_date、土日除く）
    let bizDays = 0;
    const cur = new Date(today);
    cur.setDate(cur.getDate() + 1);
    while (cur <= end) {
        const dow = cur.getDay();
        if (dow !== 0 && dow !== 6) bizDays++;
        cur.setDate(cur.getDate() + 1);
    }

    if (bizDays <= 3) {
        return { bg: '#fef3c7', border: '#d97706', text: '#92400e' }; // 黄: 間近
    }
    return { bg: '#dbeafe', border: '#1d4ed8', text: '#1e3a8a' }; // 青: 通常
}
