// 交通費フォームの入力整形（欄を離れたときに整える）。サーバー側は SaveExpenseRequest が同じ規則で整える。

/** 日本語テキスト（行先・駅名）: 半角英数・記号・カナを全角にそろえる */
export function normalizeText(value) {
    if (!value) return value;
    const s = value.normalize('NFKC').replace(/[!-~]/g, (c) => String.fromCharCode(c.charCodeAt(0) + 0xfee0));
    return s.replace(/[\s　]+/g, '　').trim();
}

/** 金額: 全角・カンマ・「円」を除いた整数。読めなければ null */
export function parseYen(value) {
    if (value === null || value === undefined || value === '') return 0;
    const s = String(value).normalize('NFKC').replace(/[,，、\s円¥￥]/g, '');
    return /^\d+$/.test(s) ? parseInt(s, 10) : null;
}

export function formatYen(n) {
    return n ? Number(n).toLocaleString('ja-JP') : '';
}

/**
 * 発生日: 対象月 (YYYY-MM) を補って Y-m-d にする。
 *   "22" / "22日" → 対象月の22日   "0422" / "4/22" / "4月22日" → 4月22日（年は対象月の年）
 *   "20260822" / "2026/8/22" → そのまま
 * 解釈できない・存在しない日付は元の文字列を返す（保存時にエラー表示）
 */
export function parseOccurrenceDate(value, month) {
    if (!value) return value;
    const v = value.normalize('NFKC').trim();
    const [my, mm] = month.split('-').map(Number);
    let y = my, m, d, mt;
    if ((mt = v.match(/^(\d{4})[\/\-.年](\d{1,2})[\/\-.月](\d{1,2})日?$/)) || (mt = v.match(/^(\d{4})(\d{2})(\d{2})$/))) {
        y = +mt[1]; m = +mt[2]; d = +mt[3];
    } else if ((mt = v.match(/^(\d{1,2})[\/\-.月](\d{1,2})日?$/)) || (mt = v.match(/^(\d{2})(\d{2})$/))) {
        m = +mt[1]; d = +mt[2];
    } else if ((mt = v.match(/^(\d{1,2})日?$/))) {
        m = mm; d = +mt[1];
    } else {
        return value;
    }
    const dt = new Date(y, m - 1, d);
    if (dt.getFullYear() !== y || dt.getMonth() !== m - 1 || dt.getDate() !== d) return value;
    return `${String(y).padStart(4, '0')}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
}

/** 'YYYY-MM-DD' → 'M月D日'（表示用）。それ以外はそのまま */
export function shortDate(str) {
    const mt = /^(\d{4})-(\d{2})-(\d{2})$/.exec(str || '');
    return mt ? `${+mt[2]}月${+mt[3]}日` : (str || '');
}
