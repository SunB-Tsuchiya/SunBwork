// 交通費の入力補助（貼り付け一括入力・複数日の指定・クイック入力・候補の当てはめ）。画面とは独立した純粋な関数。
import { normalizeText, parseYen, parseOccurrenceDate } from './transportNormalize';

const nfkc = (s) => String(s ?? '').normalize('NFKC');
const ISO = /^\d{4}-\d{2}-\d{2}$/;
const SEP_CHARS = '\\-－―−‐→⇒〜~';
const SEP_ONLY = new RegExp(`^[${SEP_CHARS}ー]$`);                       // 区切りだけのセル（結合セルの「－」など）
const ROUTE_ONE = new RegExp(`^(.+?)\\s*[${SEP_CHARS}]\\s*(.+)$`);       // 「田端－市ヶ谷」
const ROUTE_ONE_DASH = /^(.+?)\s+ー\s+(.+)$/;                            // 「ー」は駅名にも入るので、前後に空白があるときだけ区切りとして扱う

/** 対象月 (YYYY-MM) の月初・月末 'YYYY-MM-DD' */
export function monthRange(month) {
    const [y, m] = month.split('-').map(Number);
    const last = new Date(y, m, 0).getDate();
    const p = (n) => String(n).padStart(2, '0');
    return [`${y}-${p(m)}-01`, `${y}-${p(m)}-${p(last)}`];
}

/** Excel のシリアル値（例 46266）→ YYYY-MM-DD */
function fromExcelSerial(n) {
    const d = new Date(Date.UTC(1899, 11, 30) + n * 86400000);
    return `${d.getUTCFullYear()}-${String(d.getUTCMonth() + 1).padStart(2, '0')}-${String(d.getUTCDate()).padStart(2, '0')}`;
}

/** 日付として読めれば YYYY-MM-DD、読めなければ null。explicit=true なら、区切りや「月」「日」を含む形だけ認める */
function toDate(token, month, explicit = false) {
    const t = nfkc(token).trim();
    if (/^\d{5}$/.test(t) && +t > 30000 && +t < 70000) return fromExcelSerial(+t);
    if (explicit && !/[\/\-.月年日]/.test(t)) return null;
    const r = parseOccurrenceDate(t, month);
    return ISO.test(r) ? r : null;
}

const norm = (s) => nfkc(s).replace(/\s+/g, '');

/** 用件の読み取り: 既存の用件にそろえる。当てはまらなければ「その他」（内容は文字のまま） */
function resolvePurpose(text, purposes) {
    if (!text) return { purpose: 'round_trip', purpose_text: '' };
    const t = norm(text);
    for (const [key, label] of Object.entries(purposes)) {
        if (key !== 'other' && norm(label) === t) return { purpose: key, purpose_text: '' };
    }
    const hints = { round_trip: '往復', outbound: '往路', return: '帰路', direct_home: '直帰' };
    for (const [key, h] of Object.entries(hints)) {
        if (t.includes(h)) return { purpose: key, purpose_text: '' };
    }
    if (norm(purposes.round_trip ?? '').startsWith(t) && t.length >= 2) return { purpose: 'round_trip', purpose_text: '' };   // 「打合せ」だけ
    return { purpose: 'other', purpose_text: normalizeText(text) };
}

/** 1 行を区切る: タブ → カンマ → 空白 の順 */
function splitFields(line) {
    let f;
    if (line.includes('\t')) f = line.split('\t');
    else if (line.includes(',') || line.includes('，')) {
        f = line.split(/[,，]/);
        // 金額の桁区切り（1,234）で分かれたものを戻す
        for (let i = f.length - 1; i > 0; i--) {
            if (/^\d{3}\D?$/.test(f[i].trim()) && /^\s*[¥￥]?\d{1,3}\s*$/.test(f[i - 1]) && i === f.length - 1) { f.splice(i - 1, 2, f[i - 1].trim() + ',' + f[i].trim()); }
        }
    } else f = line.trim().split(/\s{2,}|\s+/);
    return f.map((x) => x.trim()).filter((x) => x !== '');
}

/**
 * 貼り付けたテキスト（Excel からのコピー・タブ区切り・カンマ区切り）を、明細の行に読み取る。
 * 位置ではなく中身で判断する（Excel の結合セルの空白は無視）。
 * 戻り値: [{ line, row, warnings }]
 */
export function parsePaste(text, month, purposes) {
    const [start, end] = monthRange(month);
    const out = [];
    String(text ?? '').replace(/\r/g, '').split('\n').forEach((raw, idx) => {
        if (!raw.trim()) return;
        const tokens = splitFields(raw);
        if (!tokens.length) return;
        const head = norm(tokens[0]);
        if (/^(計|小計|合計)/.test(head)) return;                       // 合計行
        if (tokens.some((t) => norm(t) === '発生日') || (tokens.some((t) => norm(t) === '行先') && tokens.some((t) => norm(t) === '金額'))) return;   // 見出し行

        const warnings = [];
        let rest = tokens.slice();

        // 日付: 先頭のセル。なければ、区切りや「月」「日」を含むセル
        let date = toDate(rest[0], month);
        if (date) rest.shift();
        else {
            const i = rest.findIndex((t) => toDate(t, month, true));
            if (i >= 0) { date = toDate(rest[i], month, true); rest.splice(i, 1); }
        }

        // 金額: 最後の数字だけのセル
        let amount = null;
        for (let i = rest.length - 1; i >= 0; i--) {
            if (/^[¥￥]?[\d,，]+円?$/.test(nfkc(rest[i]).replace(/\s/g, ''))) { amount = parseYen(rest[i].replace(/[¥￥]/g, '')); rest.splice(i, 1); break; }
        }

        // IC / 切符
        let fare = 'ic';
        const fi = rest.findIndex((t) => /^(IC|切符)$/i.test(nfkc(t).trim()));
        if (fi >= 0) { fare = /切符/.test(rest[fi]) ? 'ticket' : 'ic'; rest.splice(fi, 1); }

        // 区間: 末尾から「出発 － 到着」を取り出す
        let from = '', to = '';
        const lastN = (n) => rest.slice(-n);
        if (rest.length >= 3 && SEP_ONLY.test(nfkc(rest[rest.length - 2]).trim())) {
            [from, , to] = lastN(3); rest.splice(-3, 3);
        } else if (rest.length >= 1 && (ROUTE_ONE.test(nfkc(rest[rest.length - 1])) || ROUTE_ONE_DASH.test(nfkc(rest[rest.length - 1])))) {
            const t = nfkc(rest[rest.length - 1]);
            const m = t.match(ROUTE_ONE) || t.match(ROUTE_ONE_DASH);
            from = m[1]; to = m[2]; rest.pop();
        } else if (rest.length >= 3) {
            [from, to] = lastN(2); rest.splice(-2, 2);                    // 区切りが無いとき: 末尾の 2 つを出発・到着とみなす
        }

        // 行先は先頭、その間が用件
        const destination = rest.shift() ?? '';
        const purposeText = rest.join(' ');
        const { purpose, purpose_text } = resolvePurpose(purposeText, purposes);

        if (!destination) warnings.push('行先が読み取れません');
        if (!date) warnings.push('日付が読み取れません');
        else if (date < start || date > end) warnings.push(`日付が対象月の範囲外です（${date}）`);
        if (!from || !to) warnings.push('区間が読み取れません');
        if (amount === null || amount <= 0) warnings.push('金額が読み取れません');

        out.push({
            line: idx + 1,
            warnings,
            row: {
                occurrence_date: date, destination: normalizeText(destination), purpose, purpose_text,
                station_from: normalizeText(from), station_to: normalizeText(to), fare_type: fare,
                // 貼り付けた金額は、そのまま合計として扱う（Excel の金額は往復ぶんを含むので 2 倍にしない）
                base_amount: amount ?? 0, auto_double: false, amount: amount ?? 0,
            },
        });
    });
    return out;
}

/** 「1, 3, 6-10, 15」→ 対象月の日付の配列（昇順・重複なし）。読めなかった指定は invalid に */
export function parseDays(text, month) {
    const [y, m] = month.split('-').map(Number);
    const last = new Date(y, m, 0).getDate();
    const days = new Set(); const invalid = [];
    for (const part of nfkc(text).replace(/日/g, '').split(/[,、\s]+/).filter(Boolean)) {
        const r = part.match(/^(\d{1,2})\s*[-〜~]\s*(\d{1,2})$/);
        const one = part.match(/^(\d{1,2})$/);
        if (r) {
            const a = +r[1], b = +r[2];
            if (a >= 1 && b <= last && a <= b) for (let d = a; d <= b; d++) days.add(d); else invalid.push(part);
        } else if (one && +one[1] >= 1 && +one[1] <= last) days.add(+one[1]);
        else invalid.push(part);
    }
    const p = (n) => String(n).padStart(2, '0');
    return { dates: [...days].sort((a, b) => a - b).map((d) => `${y}-${p(m)}-${p(d)}`), invalid };
}

const katakana = (s) => nfkc(s).toLowerCase().replace(/[ぁ-ゖ]/g, (c) => String.fromCharCode(c.charCodeAt(0) + 0x60));

/** 候補（登録＋履歴）から、キーワードに合うものを、合う順に返す。完全一致 > 前方一致 > 部分一致（行先・駅名）。同点は登録済み・使った回数の多い順 */
export function findSuggestions(keyword, suggestions, limit = 8) {
    const k = katakana(keyword).replace(/\s+/g, '');
    if (!k) return [];
    const scored = [];
    suggestions.forEach((s, idx) => {
        const d = katakana(s.destination).replace(/\s+/g, '');
        let score = 0;
        if (d === k) score = 4;
        else if (d.startsWith(k)) score = 3;
        else if (d.includes(k)) score = 2;
        else if (katakana(`${s.station_from}${s.station_to}`).includes(k)) score = 1;
        if (score > 0) scored.push({ s, score, idx });   // suggestions は登録済み・回数順に並んでいるので、同点では元の順を保つ
    });
    return scored.sort((x, y) => y.score - x.score || x.idx - y.idx).slice(0, limit).map((x) => x.s);
}

/** 最も合う 1 件 */
export function findSuggestion(keyword, suggestions) {
    return findSuggestions(keyword, suggestions, 1)[0] ?? null;
}

/**
 * クイック入力「0901 国際」「0901 0903 国際」。先頭の日付（複数可）＋行先のキーワード。
 * 戻り値: { dates: [...](空なら日付なし), keyword, suggestion }
 */
export function parseQuick(text, month, suggestions) {
    const tokens = nfkc(text).trim().split(/[\s,、]+/).filter(Boolean);
    const dates = [];
    let i = 0;
    for (; i < tokens.length; i++) {
        const d = /^[\d\/\-.月日]+$/.test(tokens[i]) ? toDate(tokens[i], month) : null;
        if (!d) break;
        dates.push(d);
    }
    const keyword = tokens.slice(i).join(' ');
    return { dates, keyword, suggestion: findSuggestion(keyword, suggestions), candidates: findSuggestions(keyword, suggestions) };
}

/** 候補を、明細の行に当てはめる（日付は変えない） */
export function applySuggestion(row, s) {
    const doubled = !!s.auto_double && s.purpose === 'round_trip';
    return {
        ...row,
        destination: s.destination, purpose: s.purpose, purpose_text: s.purpose_text ?? '',
        station_from: s.station_from ?? '', station_to: s.station_to ?? '', fare_type: s.fare_type,
        base_amount: s.base_amount, auto_double: doubled, amount: s.base_amount * (doubled ? 2 : 1),
    };
}
