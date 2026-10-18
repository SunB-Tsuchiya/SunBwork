// 年末調整フォーム・社員情報の入力整形（欄を離れたときに自動で整える）。
// サーバー側（NenchoRules）と同じ規則: 氏名・住所は国の様式（全角）、数字・金額は半角。サーバー側でも同じ整形をするので、画面を通さなくても同じ結果になる。

const HYPHENS = /[‐‑‒–—―−]/g;

/** 半角の ASCII（英数字・記号）を全角にする。空白は全角スペース */
const asciiToFull = (s) => s.replace(/[!-~]/g, (c) => String.fromCharCode(c.charCodeAt(0) + 0xfee0)).replace(/ /g, '　');

/** 氏名・住所などの日本語テキスト: 英数字・ハイフン・半角カナ・空白を全角にそろえる（例: 3-7-7 → ３－７－７） */
export function normalizeText(value) {
    if (!value) return value;
    let s = value.replace(/([0-9０-９])ー(?=[0-9０-９])/g, '$1-').replace(HYPHENS, '-');   // 3ー7 → 3-7
    s = s.normalize('NFKC');                                                                  // 半角カナ → 全角カナ（英数字は半角になるので、次で全角に戻す）
    s = asciiToFull(s);
    return s.replace(/[\s　]+/g, '　').trim();
}

/** フリガナ: ひらがな・半角カナ → 全角カナ、空白は全角スペース 1 つ */
export function normalizeKana(value) {
    if (!value) return value;
    let s = value.normalize('NFKC').replace(/[ぁ-ゖ]/g, (c) => String.fromCharCode(c.charCodeAt(0) + 0x60));
    return s.replace(/[\s　]+/g, '　').trim();
}

/** 数字だけ（郵便番号・マイナンバー）: 全角・ハイフン・空白・「〒」を取り除く */
export function normalizeDigits(value) {
    return value ? value.normalize('NFKC').replace(/\D/g, '') : value;
}

/** 半角英数字（社員番号） */
export function normalizeAlnum(value) {
    return value ? value.normalize('NFKC').replace(/[^A-Za-z0-9]/g, '') : value;
}

/** 金額（円）: 全角・カンマ・「円」・空白を取り除き、「450万」「450万円」は円に直して、カンマ区切りで表示する */
export function normalizeYen(value) {
    if (!value) return value;
    let s = value.normalize('NFKC').replace(/[,，、\s円¥￥]/g, '').replace(/[‐‑‒–—―−ー]/g, '-');
    const man = s.match(/^(-?\d+(?:\.\d+)?)万(\d*)$/);
    if (man) s = String(Math.round(parseFloat(man[1]) * 10000) + (man[2] ? parseInt(man[2], 10) : 0));
    return /^-?\d+$/.test(s) ? Number(s).toLocaleString('ja-JP') : value;   // 数字として読めない場合は、そのまま（保存時にエラー表示）
}

const ERAS = { 明治: 1867, M: 1867, 大正: 1911, T: 1911, 昭和: 1925, S: 1925, 平成: 1988, H: 1988, 令和: 2018, R: 2018 };

/**
 * 日付: 19770905 / 1977/09/05 / 1977-9-5 / 1977年9月5日 / 昭和52年9月5日 / S52.9.5 / 令和元年5月1日 → 1977-09-05（Y-m-d）
 * 解釈できない場合は、そのまま返す（保存時にエラー表示）
 */
export function normalizeDate(value) {
    if (!value) return value;
    const v = value.normalize('NFKC').trim();
    let y, m, d, mt;
    if ((mt = v.match(/^(明治|大正|昭和|平成|令和|[MTSHRmtshr])\s*(元|\d{1,2})\s*[年./\-]\s*(\d{1,2})\s*[月./\-]\s*(\d{1,2})\s*日?$/))) {
        const era = ERAS[mt[1].length === 1 ? mt[1].toUpperCase() : mt[1]];
        y = era + (mt[2] === '元' ? 1 : parseInt(mt[2], 10)); m = parseInt(mt[3], 10); d = parseInt(mt[4], 10);
    } else if ((mt = v.match(/^(\d{4})[\/\-.年\s]\s*(\d{1,2})[\/\-.月\s]\s*(\d{1,2})\s*日?$/)) || (mt = v.match(/^(\d{4})(\d{2})(\d{2})$/))) {
        y = parseInt(mt[1], 10); m = parseInt(mt[2], 10); d = parseInt(mt[3], 10);
    } else {
        return value;
    }
    const dt = new Date(y, m - 1, d);
    if (dt.getFullYear() !== y || dt.getMonth() !== m - 1 || dt.getDate() !== d) return value;   // 存在しない日付（2/31 など）
    return `${String(y).padStart(4, '0')}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
}

/** 金額の入力中に、3 桁ごとのカンマを自動で入れる（カーソル位置は保つ）。万・円などを含む途中の入力は、欄を離れたときに直す */
function liveYen(el) {
    if (el.__composing) return;
    const raw = el.value;
    const stripped = raw.normalize('NFKC').replace(/[,，\s]/g, '');
    if (!/^-?\d+$/.test(stripped)) return;
    const neg = stripped.startsWith('-');
    const formatted = (neg ? '-' : '') + Number(stripped.replace('-', '')).toLocaleString('ja-JP');
    if (formatted === raw) return;
    const caret = el.selectionStart ?? raw.length;
    const digitsBefore = raw.slice(0, caret).normalize('NFKC').replace(/\D/g, '').length;
    let pos = 0;
    for (let seen = 0; pos < formatted.length && seen < digitsBefore; pos++) if (/\d/.test(formatted[pos])) seen++;
    el.value = formatted;
    try { el.setSelectionRange(pos, pos); } catch { /* type=number などでは無視 */ }
    el.__formatting = true;
    el.dispatchEvent(new Event('input', { bubbles: true }));
    el.__formatting = false;
}

const NORMALIZERS = { name: normalizeText, text: normalizeText, kana: normalizeKana, zip: normalizeDigits, date: normalizeDate, digits: normalizeDigits, alnum: normalizeAlnum, yen: normalizeYen };

/**
 * 使い方: <input v-model="form.name" v-norm="'name'" />
 * 欄を離れたとき（blur）に値を整え、v-model に反映する。kind: name / text / kana / zip / digits / alnum / yen / date
 * yen は入力中にもカンマ区切りにする
 */
export const vNorm = {
    mounted(el, binding) {
        if (binding.value === 'yen') {
            // 金額: 入力中に自動でカンマ区切り（日本語入力の変換中は、確定してから）
            el.addEventListener('compositionstart', () => { el.__composing = true; });
            el.addEventListener('compositionend', () => { el.__composing = false; liveYen(el); });
            el.addEventListener('input', () => { if (!el.__formatting) liveYen(el); });
        }
        el.addEventListener('blur', () => {
            const fn = NORMALIZERS[binding.value];
            if (!fn || el.disabled) return;
            const next = fn(el.value);
            if (next !== el.value) {
                el.value = next;
                el.dispatchEvent(new Event('input', { bubbles: true }));
            }
        });
    },
};
