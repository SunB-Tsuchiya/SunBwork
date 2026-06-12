<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import axios from 'axios';
import * as XLSX from 'xlsx';

defineProps({ script: Object });

const GRADE_OPTIONS = ['1年', '2年', '3年', '4年', '5年', '6年'];
const ITEM_SYMBOLS  = '①②③④⑤⑥⑦⑧⑨⑩⑪⑫⑬⑭⑮';
const GRADE_ORDER   = ['1年','2年','3年','4年','5年','6年','新3年','新4年','新5年','新6年'];

// ── V1 と共有：ルートマップ・学校表示ルール ──────────────────
const ROUTE_ORDER = ['A1','B1','C1','D1','E1','F1','G1','H1','I1','A2','B2','C2','D2','E2','F2','G2','H2','I2'];
const FALLBACK_ROUTE_MAP = (() => {
    const routes = {
        A1: ['DL','DG','DM','DH','CP','CJ'], B1: ['AL','AK','AM','AS','AB','AY'],
        C1: ['DT','DK','DC','DR','AG'],      D1: ['CD','CB','CN','CX','AC','AN'],
        E1: ['DE','DO','DA','DS','DY'],      F1: ['FI','FR','FF','FT','FE'],
        G1: ['EI','EA'],                     H1: ['CK','CH','DJ','CM','CT'],
        I1: ['BC','BF','BT','BM','EL','EG'],
        A2: ['BI','EH','EK','EJ','EF','EC'], B2: ['GA','FB','EP','EY'],
        C2: ['AD','AI','AA','AP','AR'],      D2: ['CW','CO','AX','AO','AT','EX'],
        E2: ['DN','BS','BK','BN','BR','BJ'], F2: ['EE','FS','FK','FJ','EN','ET'],
        G2: ['ED','ES'],                     H2: ['CC','CL','CA','CF','CY','CU'],
        I2: ['AW','AH','AJ','AF','ER','EM'],
    };
    const map = {};
    for (const [route, codes] of Object.entries(routes)) {
        codes.forEach((code, i) => { map[code] = { route, stop: i + 1 }; });
    }
    return map;
})();
const NO_SUFFIX_KEYWORDS = ['コバ','向学館','関東物流','NTS','別館','関東本部','職員','本部','受付','ロジ','研究','調査','情報','人材','業務','法人','学力'];
const SPECIAL_ENTRY_KEYWORDS = { '東海本部': '$tokai', 'ユリウス': '$julius', 'アトラス': '$julius', '予備': '$yobi' };
const SPECIAL_SORT = { $tokai: 50158, $yobi: 50160, $julius: 50162 };
const PW = 729;  // Canvas / jsPDF ページサイズ（pt）
const PH = 516;

// シート名から MMDD を抽出（「日」抜けtypoも対応 — V1 extractDateCode と同じロジック）
function extractDateCode(sheetName) {
    const n = sheetName.normalize('NFKC');
    const m = n.match(/(\d+)月(\d+)日?(?=[^月]|$)/);
    if (!m) return null;
    return m[1].padStart(2, '0') + m[2].padStart(2, '0');
}

// ヘッダー行から学年列を自動検出（V1 parseSheet と同じロジック）
function detectGradeCols(rows) {
    const cols = {};
    for (let r = 0; r <= Math.min(7, rows.length - 1); r++) {
        const row = rows[r] || [];
        for (let c = 0; c < Math.min(row.length, 16); c++) {
            const cell = String(row[c] || '').normalize('NFKC').trim();
            const gm = cell.match(/^(\d年)生?$/);
            if (gm) cols[c] = gm[1];
        }
    }
    // 検出できない場合は標準レイアウトにフォールバック
    if (Object.keys(cols).length === 0) {
        Object.assign(cols, { 2:'3年', 3:'4年', 4:'5年', 5:'6年', 8:'3年', 9:'4年', 10:'5年', 11:'6年' });
    }
    return cols; // { colIndex: '3年', ... }
}

// ── 共通ユーティリティ（V1 と同じアルゴリズム）──────────────
function normCode(s) {
    return String(s || '').trim().normalize('NFKC').toUpperCase().replace(/\s/g, '');
}
function needsSchoolSuffix(name) {
    return !NO_SUFFIX_KEYWORDS.some(kw => name.includes(kw));
}
function splitBoxes(qty, maxBox) {
    const boxes = []; let rem = qty;
    while (rem > 0) { boxes.push(Math.min(rem, maxBox)); rem -= maxBox; }
    return boxes;
}
function inferMaxBox(text) {
    const n = text.normalize('NFKC');
    if (/DI答案|答案用紙/.test(n)) return 250;
    if (/解答|解説/.test(n)) return 100;
    if (/問題/.test(n)) return 50;
    return 100;
}
function parseItemLabelFromRest(rest) {
    // "解答" → {subject:'', itemLabel:'解答'}
    // "国算解答" → {subject:'国算', itemLabel:'解答'}
    // "社会DI答案用紙" → {subject:'社会', itemLabel:'DI答案用紙'}
    const PATS = ['DI答案', 'DI', '解答', '解説', '問題', '答案'];
    for (const pat of PATS) {
        const idx = rest.indexOf(pat);
        if (idx >= 0) return { subject: rest.slice(0, idx).trim(), itemLabel: rest.slice(idx).trim() };
    }
    return { subject: '', itemLabel: rest };
}
// アイテム凡例を抽出（NFKC-safe: normalize 前に ① を検出する）
// ⚠️ normalize('NFKC') は ①→1 に変換するため、生文字列で先に sym を確認すること
function extractLegendItemsV2(rows) {
    const found = [];
    const seen = new Set();
    for (let r = 0; r < Math.min(rows.length, 20); r++) {
        const row = rows[r] || [];
        for (let c = 7; c < Math.min(row.length, 18); c++) {
            const raw = String(row[c] || '').trim();
            if (!raw) continue;
            const sym = raw[0];
            if (!ITEM_SYMBOLS.includes(sym) || seen.has(sym)) continue;
            seen.add(sym);
            const rest = raw.slice(1).normalize('NFKC').trim();
            const { subject, itemLabel } = parseItemLabelFromRest(rest);
            found.push({ sym, subject, itemLabel, maxBox: inferMaxBox(raw.normalize('NFKC')) });
        }
    }
    return found;
}
// データ開始行の自動検出（V1と同じく4列を試す）
function detectDataStartRow(data) {
    for (let r = 3; r < Math.min(data.length, 20); r++) {
        const row = data[r] || [];
        for (const col of [0, 1, 6, 7]) {
            const code = normCode(String(row[col] || ''));
            if (!/^[A-Z]{2}$/.test(code)) continue;
            const name = String(row[col + 1] || '').trim();
            if (name && !/^\d+$/.test(name)) return r;
        }
    }
    return 7;
}
// 教室データ（code, name, 学年別部数）を抽出
// ⚠️ このExcelは col 0 が空欄で col 1 にコード、col 7 に右ブロックコードがある
//    V1 と同じく [0,1,6,7] の4列を試す
function parseSchoolRows(data, gradeCols) {
    const SKIP = /小計|合計|本部計|総合計/;
    const schools = {};
    const dataStart = detectDataStartRow(data);
    for (let r = dataStart; r < data.length; r++) {
        const row = data[r] || [];
        const seenCodes = new Set(); // 同一行内での重複処理防止
        for (const codeCol of [0, 1, 6, 7]) {
            if (codeCol >= row.length) continue;
            const code = normCode(row[codeCol]);
            if (!/^[A-Z]{2}$/.test(code) || seenCodes.has(code)) continue;
            const name = String(row[codeCol + 1] || '').trim();
            if (!name || SKIP.test(name) || /^\d+$/.test(name)) continue;
            seenCodes.add(code);
            const schoolKey = schools[code] ? `${code}_${r}` : code;
            if (!schools[schoolKey])
                schools[schoolKey] = { code, name, grades: {}, rowIdx: r, colIdx: codeCol <= 1 ? 0 : 1 };
            const school = schools[schoolKey];
            for (const [absColStr, gradeLabel] of Object.entries(gradeCols)) {
                const absCol = Number(absColStr);
                const relPos = absCol - codeCol;
                if (relPos < 1 || relPos > 6) continue;
                const qty = parseInt(String(row[absCol] || '').replace(/,/g, ''), 10);
                if (!isNaN(qty) && qty > 0) school.grades[gradeLabel] = qty;
            }
        }
        // 特殊行（東海本部・ユリウス・予備）: コードなし行で名前列にキーワード
        for (const nameCol of [1, 2]) {
            const cellName = String(row[nameCol] || '').normalize('NFKC').trim();
            for (const [kw, specialCode] of Object.entries(SPECIAL_ENTRY_KEYWORDS)) {
                if (cellName.includes(kw) && !schools[specialCode]) {
                    const grades = {};
                    for (const [absColStr, gradeLabel] of Object.entries(gradeCols)) {
                        const absCol = Number(absColStr);
                        const relPos = absCol - nameCol;
                        if (relPos < 1 || relPos > 6) continue;
                        const qty = parseInt(String(row[absCol] || '').replace(/,/g, ''), 10);
                        if (!isNaN(qty) && qty > 0) grades[gradeLabel] = qty;
                    }
                    if (Object.keys(grades).length > 0)
                        schools[specialCode] = { code: specialCode, name: cellName, grades, rowIdx: r, colIdx: 0 };
                    break;
                }
            }
        }
    }
    // Pass 1: 小計行からエリア境界を収集
    const BOUNDARY_RE = /^(.+?)(?:小計|本部計)$/;
    const boundaries = [];
    for (let r = dataStart; r < data.length; r++) {
        const row = data[r] || [];
        for (const c of [0, 1, 2]) {
            const cell = String(row[c] || '').trim();
            const m = cell.match(BOUNDARY_RE);
            if (m) { boundaries.push({ rowIdx: r, area: m[1] }); break; }
        }
    }
    // Pass 2: 各教室に最も近い次の境界のエリアを割り当て
    if (boundaries.length > 0) {
        for (const school of Object.values(schools)) {
            const boundary = boundaries.find(b => b.rowIdx > school.rowIdx);
            if (boundary) school.area = boundary.area;
        }
    }

    return schools;
}
// 一式シート（シート名に「一式」を含む）の黄色セル行を抽出
// 左ブロック（名前col=2, 学年col=3-7）・右ブロック（名前col=8, 学年col=9-13）両方を対象
// returns [{ name, grades: { '3年': N, ... } }, ...]
function parseIsshikiRows(ws, data, gradeCols) {
    const SKIP = /小計|合計|本部計|総合計|テストサービス|NTSテスト/;
    const destinations = [];
    const dataStart = detectDataStartRow(data);
    const BLOCKS = [
        { nameCol: 2, gradeMin: 2, gradeMax: 7 },   // 左ブロック
        { nameCol: 8, gradeMin: 8, gradeMax: 13 },  // 右ブロック
    ];
    for (let r = dataStart; r < data.length; r++) {
        const row = data[r] || [];
        for (const { nameCol, gradeMin, gradeMax } of BLOCKS) {
            const cellAddr = XLSX.utils.encode_cell({ r, c: nameCol });
            const cell = ws[cellAddr];
            if (!cell) continue;
            const fg = cell.s?.fgColor?.rgb ?? '';
            if (fg !== 'FFFF00') continue;
            const name = String(cell.v ?? '').trim();
            if (!name || SKIP.test(name)) continue;
            const grades = {};
            for (const [absColStr, gradeLabel] of Object.entries(gradeCols)) {
                const absCol = Number(absColStr);
                if (absCol <= gradeMin || absCol > gradeMax) continue;
                const qty = parseInt(String(row[absCol] || '').replace(/,/g, ''), 10);
                if (!isNaN(qty) && qty > 0) grades[gradeLabel] = qty;
            }
            if (!destinations.find(d => d.name === name))
                destinations.push({ name, grades });
        }
    }
    return destinations;
}
// ラベル並び順キー（V1 labelSortKey と同じ）
function labelSortKey(label) {
    const icode = label._internalCode ?? label.schoolCode;
    if (icode === 'SS') return -10000;
    if (icode === 'PA') return 999999;
    if (SPECIAL_SORT[icode] !== undefined) return SPECIAL_SORT[icode];
    if (label._routeOrder >= 0) return label._routeOrder * 1000 + label._stopOrder;
    return 50000 + label._rowIdx * 2 + label._colIdx;
}
// MMDD → 表示日付文字列（"0330" → "2026年3月30日"）
function dateCodeToDisplay(code) {
    if (!code || code === '__common') return '';
    const m = code.match(/^(\d{2})(\d{2})$/);
    if (!m) return code;
    return `${new Date().getFullYear()}年${parseInt(m[1])}月${parseInt(m[2])}日`;
}
// シンボル別学校データからラベルオブジェクト配列を構築
function buildLabelsFromEntry(symEntry, grade, group) {
    const { schools, subject, itemLabel, maxBox } = symEntry;
    const dateDisplay = dateCodeToDisplay(group.mmdd);
    const labels = [];
    for (const school of Object.values(schools)) {
        const qty = school.grades[grade];
        if (!qty || qty <= 0) continue;
        const displayName = needsSchoolSuffix(school.name) ? school.name + '校' : school.name;
        const boxes = splitBoxes(qty, maxBox);
        const ri = effectiveRouteMap.value[school.code];
        boxes.forEach((boxQty, bi) => {
            labels.push({
                routeCode:     ri?.route ?? '',
                schoolCode:    school.code.startsWith('$') ? '' : school.code,
                schoolName:    displayName,
                _internalCode: school.code,
                boxNum:        bi + 1,
                boxTotal:      boxes.length,
                quantity:      boxQty,
                serial:        0,
                testName:      group.testName,
                date:          dateDisplay,
                grade,
                subject,
                itemLabel,
                _routeOrder:   ri ? ROUTE_ORDER.indexOf(ri.route) : -1,
                _stopOrder:    ri?.stop ?? 9999,
                _rowIdx:       school.rowIdx,
                _colIdx:       school.colIdx ?? 0,
            });
        });
    }
    labels.sort((a, b) => labelSortKey(a) - labelSortKey(b));
    labels.forEach((l, i) => { l.serial = i + 1; });
    return labels;
}

// ── マスタ管理（DB API）──────────────────────────────────────
const schoolMaster          = ref([]);
const testNameMaster        = ref([]);
const itemTypeMaster        = ref([]);
const isshikiMaster         = ref([]);  // 一式宛先マスタ
const areaMaster            = ref([]);  // エリアマスタ
const routeMaster           = ref([]);  // 社内便ルートマスタ
const masterLoading         = ref(false);

const mapSchool    = s => ({
    id: s.id, code: s.code, name: s.display_name,
    route: s.route || '', stopOrder: s.stop_order || '',
});
const mapTestName  = t => ({ id: t.id, name: t.name, isActive: t.is_active !== false });
const mapItemType  = t => ({ id: t.id, name: t.name, isActive: t.is_active !== false });
const mapIsshiki   = t => ({ id: t.id, name: t.name, sortOrder: t.sort_order, isActive: t.is_active !== false });
const mapArea      = t => ({ id: t.id, name: t.name, sortOrder: t.sort_order, isActive: t.is_active !== false });

async function loadMasters() {
    masterLoading.value = true;
    try {
        const [sch, tn, it, ish, ar, rt] = await Promise.all([
            axios.get('/label-masters/schools'),
            axios.get('/label-masters/test-names'),
            axios.get('/label-masters/item-types'),
            axios.get('/label-masters/isshiki-destinations'),
            axios.get('/label-masters/area-masters'),
            axios.get('/label-masters/routes'),
        ]);
        schoolMaster.value    = sch.data.map(mapSchool);
        testNameMaster.value  = tn.data.map(mapTestName);
        itemTypeMaster.value  = it.data.map(mapItemType);
        isshikiMaster.value   = ish.data.map(mapIsshiki);
        areaMaster.value      = ar.data.map(mapArea);
        routeMaster.value     = rt.data;
    } catch (e) {
        console.error('マスタ読み込みエラー', e);
    } finally {
        masterLoading.value = false;
    }
}

onMounted(loadMasters);

// ── 社内便マスタ ──────────────────────────────────────────────
const routeCourse       = ref(1);           // 表示コース 1 or 2
const editingStop       = ref(null);        // 編集中セル {routeId, routeCode, stopOrder, id?, ...}
const editingRoute      = ref(null);        // 編集中ルートヘッダー
const STOP_ORDERS       = [2,3,4,5,6,7,8,9,10];

const course1Routes = computed(() => routeMaster.value.filter(r => r.course === 1));
const course2Routes = computed(() => routeMaster.value.filter(r => r.course === 2));
const activeRoutes  = computed(() => routeCourse.value === 1 ? course1Routes.value : course2Routes.value);

const activeStopOrders = computed(() => {
    const s = new Set();
    for (const r of activeRoutes.value) {
        for (const st of (r.stops ?? [])) s.add(st.stop_order);
    }
    for (let i = 2; i <= 10; i++) s.add(i);
    return [...s].sort((a, b) => a - b);
});

const CELL_COLOR = {
    honbu:   'bg-amber-100',
    kanto:   'bg-green-100',
    busho:   'bg-yellow-100',
    henkou:  'bg-pink-100',
    kakunin: 'bg-red-100',
    ng:      'bg-sky-100',
};
const LEGEND = [
    { key: 'honbu',   label: '本部系教室', cls: 'bg-amber-200' },
    { key: 'kanto',   label: '関東系教室', cls: 'bg-green-200' },
    { key: 'busho',   label: '部署等',     cls: 'bg-yellow-200' },
    { key: 'henkou',  label: '変更',       cls: 'bg-pink-200' },
    { key: 'kakunin', label: '確認',       cls: 'bg-red-200' },
    { key: 'ng',      label: 'NG便',       cls: 'bg-sky-200' },
];

function cellColorClass(stop) {
    return stop?.color_category ? (CELL_COLOR[stop.color_category] ?? '') : '';
}

function getStop(route, stopOrder) {
    return route.stops?.find(s => s.stop_order === stopOrder) ?? null;
}

function openStopEdit(route, stopOrder) {
    const stop = getStop(route, stopOrder);
    editingStop.value = {
        routeId: route.id, routeCode: route.code, stopOrder,
        id:             stop?.id ?? null,
        school_name:    stop?.school_name ?? '',
        school_code:    stop?.school_code ?? '',
        arrival_time:   stop?.arrival_time ?? '',
        notes:          stop?.notes ?? '',
        color_category: stop?.color_category ?? null,
    };
    editingRoute.value = null;
}

function openRouteEdit(route) {
    editingRoute.value = { ...route };
    editingStop.value  = null;
}

function closeRouteEdit() { editingRoute.value = null; }
function closeStopEdit()  { editingStop.value = null; }

async function saveStopEdit() {
    const s = editingStop.value;
    if (!s) return;
    try {
        let data;
        const payload = {
            school_name:    s.school_name,
            school_code:    s.school_code || null,
            arrival_time:   s.arrival_time || null,
            notes:          s.notes || null,
            color_category: s.color_category || null,
        };
        if (s.id) {
            ({ data } = await axios.put(`/label-masters/route-stops/${s.id}`, payload));
        } else {
            ({ data } = await axios.post(`/label-masters/routes/${s.routeId}/stops`, {
                ...payload, stop_order: s.stopOrder,
            }));
        }
        // ローカル状態を更新
        const route = routeMaster.value.find(r => r.id === s.routeId);
        if (route) {
            const idx = route.stops.findIndex(st => st.stop_order === s.stopOrder);
            if (idx >= 0) route.stops[idx] = data;
            else route.stops.push(data);
            route.stops.sort((a, b) => a.stop_order - b.stop_order);
        }
    } catch (e) { alert('保存エラー: ' + (e.response?.data?.message || e.message)); }
    editingStop.value = null;
}

async function deleteStop() {
    const s = editingStop.value;
    if (!s?.id || !confirm('この停留所を削除しますか？')) return;
    try {
        await axios.delete(`/label-masters/route-stops/${s.id}`);
        const route = routeMaster.value.find(r => r.id === s.routeId);
        if (route) route.stops = route.stops.filter(st => st.stop_order !== s.stopOrder);
    } catch (e) { alert('削除エラー: ' + e.message); }
    editingStop.value = null;
}

async function insertStopAt(offset = 0) {
    // offset=0: 現在行の上に挿入, offset=1: 下に挿入
    const s = editingStop.value;
    if (!s) return;
    const pos = s.stopOrder + offset;
    try {
        const { data } = await axios.post(`/label-masters/routes/${s.routeId}/stops/insert-at`, { stop_order: pos });
        const idx = routeMaster.value.findIndex(r => r.id === s.routeId);
        if (idx >= 0) routeMaster.value[idx] = { ...data, stops: data.stops };
        // 挿入行を選択
        editingStop.value = { ...editingStop.value, stopOrder: pos, id: null, school_name: '', school_code: '', arrival_time: '', notes: '', color_category: null };
    } catch (e) { alert('挿入エラー: ' + (e.response?.data?.message || e.message)); }
}

async function deleteStopShift() {
    const s = editingStop.value;
    if (!s?.id || !confirm(`停留所 ${s.stopOrder} を削除して以降の停留所を詰めますか？`)) return;
    try {
        const { data } = await axios.delete(`/label-masters/route-stops/${s.id}/shift`);
        const idx = routeMaster.value.findIndex(r => r.id === s.routeId);
        if (idx >= 0) routeMaster.value[idx] = { ...data, stops: data.stops };
    } catch (e) { alert('削除エラー: ' + e.message); }
    editingStop.value = null;
}

async function saveRouteEdit() {
    const r = editingRoute.value;
    if (!r) return;
    try {
        const { data } = await axios.put(`/label-masters/routes/${r.id}`, {
            code: r.code, course: r.course, area: r.area,
            day1: r.day1, day1_start: r.day1_start,
            day2: r.day2, day2_start: r.day2_start,
            sort_order: r.sort_order,
        });
        const idx = routeMaster.value.findIndex(rt => rt.id === r.id);
        if (idx >= 0) {
            const stops = routeMaster.value[idx].stops;
            routeMaster.value[idx] = { ...data, stops };
        }
    } catch (e) { alert('保存エラー: ' + (e.response?.data?.message || e.message)); }
    editingRoute.value = null;
}

// マスタ変更を FALLBACK_ROUTE_MAP に重ねた実効ルートマップ
const effectiveRouteMap = computed(() => {
    const map = { ...FALLBACK_ROUTE_MAP };
    for (const s of schoolMaster.value) {
        if (s.code && s.route) map[s.code] = { route: s.route, stop: Number(s.stopOrder) || 0 };
    }
    return map;
});

// モーダル状態
const showMasterModal   = ref(false);
const masterTab         = ref('testNames');  // 'testNames' | 'schools'
const masterEditingId   = ref(null);
const masterEditingRow  = ref({});
const masterAddingRow   = ref(null);
const masterSchoolSearch = ref('');
let _masterUid = 1000;

const filteredSchoolMaster = computed(() => {
    const q = masterSchoolSearch.value.trim().toLowerCase();
    if (!q) return schoolMaster.value;
    return schoolMaster.value.filter(s =>
        s.code.toLowerCase().includes(q) || (s.name || '').toLowerCase().includes(q)
    );
});

const schoolSortKey = ref('stopOrder');
const schoolSortDir = ref('asc');

function schoolToggleSort(key) {
    if (schoolSortKey.value === key) {
        schoolSortDir.value = schoolSortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        schoolSortKey.value = key;
        schoolSortDir.value = 'asc';
    }
}

const sortedSchoolMaster = computed(() => {
    const arr = [...filteredSchoolMaster.value];
    const dir = schoolSortDir.value === 'asc' ? 1 : -1;
    arr.sort((a, b) => {
        const ak = a[schoolSortKey.value];
        const bk = b[schoolSortKey.value];
        if (ak == null && bk == null) return 0;
        if (ak == null) return 1;
        if (bk == null) return -1;
        if (schoolSortKey.value === 'stopOrder') return (Number(ak) - Number(bk)) * dir;
        return String(ak).localeCompare(String(bk), 'ja') * dir;
    });
    return arr;
});

function openMasterModal(tab = 'testNames') {
    masterTab.value = tab;
    masterEditingId.value = null;
    masterAddingRow.value = null;
    schoolSortKey.value = 'stopOrder';
    schoolSortDir.value = 'asc';
    showMasterModal.value = true;
}
function masterStartEdit(row) {
    masterEditingId.value  = row.id;
    masterEditingRow.value = { ...row };
    masterAddingRow.value  = null;
}
function masterCancelEdit() { masterEditingId.value = null; }
async function masterSaveEdit(tab) {
    const row = masterEditingRow.value;
    try {
        if (tab === 'schools') {
            const { data } = await axios.put(`/label-masters/schools/${row.id}`, {
                code:         normCode(row.code),
                display_name: row.name || '',
                area:         '',
                route:        row.route || null,
                stop_order:   row.stopOrder ? Number(row.stopOrder) : null,
                is_active:    true,
            });
            const idx = schoolMaster.value.findIndex(s => s.id === data.id);
            if (idx >= 0) schoolMaster.value[idx] = mapSchool(data);
        } else if (tab === 'itemTypes') {
            const { data } = await axios.put(`/label-masters/item-types/${row.id}`, {
                name: row.name, sort_order: 0, is_active: row.isActive !== false,
            });
            const idx = itemTypeMaster.value.findIndex(t => t.id === data.id);
            if (idx >= 0) itemTypeMaster.value[idx] = mapItemType(data);
        } else if (tab === 'isshikiDestinations') {
            const { data } = await axios.put(`/label-masters/isshiki-destinations/${row.id}`, {
                name: row.name, sort_order: row.sortOrder ?? 0, is_active: row.isActive !== false,
            });
            const idx = isshikiMaster.value.findIndex(t => t.id === data.id);
            if (idx >= 0) isshikiMaster.value[idx] = mapIsshiki(data);
        } else if (tab === 'areaMasters') {
            const { data } = await axios.put(`/label-masters/area-masters/${row.id}`, {
                name: row.name, sort_order: row.sortOrder ?? 0, is_active: row.isActive !== false,
            });
            const idx = areaMaster.value.findIndex(t => t.id === data.id);
            if (idx >= 0) areaMaster.value[idx] = mapArea(data);
        } else {
            const { data } = await axios.put(`/label-masters/test-names/${row.id}`, {
                name: row.name, sort_order: 0, is_active: row.isActive !== false,
            });
            const idx = testNameMaster.value.findIndex(t => t.id === data.id);
            if (idx >= 0) testNameMaster.value[idx] = mapTestName(data);
        }
    } catch (e) { alert('保存エラー: ' + (e.response?.data?.message || e.message)); }
    masterEditingId.value = null;
}
function masterStartAdd() {
    masterEditingId.value = null;
    masterAddingRow.value = masterTab.value === 'schools'
        ? { _tab: 'schools',    code: '', name: '', route: '', stopOrder: '' }
        : masterTab.value === 'itemTypes'
            ? { _tab: 'itemTypes', name: '' }
            : masterTab.value === 'isshikiDestinations'
                ? { _tab: 'isshikiDestinations', name: '' }
                : masterTab.value === 'areaMasters'
                    ? { _tab: 'areaMasters', name: '' }
                    : { _tab: 'testNames', name: '' };
}
function masterCancelAdd() { masterAddingRow.value = null; }
async function masterSaveAdd() {
    if (!masterAddingRow.value) return;
    const { _tab } = masterAddingRow.value;
    try {
        if (_tab === 'schools') {
            const code = normCode(masterAddingRow.value.code);
            if (!code) return;
            const { data } = await axios.post('/label-masters/schools', {
                code,
                display_name: masterAddingRow.value.name || code,
                area:         '',
                route:        masterAddingRow.value.route || null,
                stop_order:   masterAddingRow.value.stopOrder ? Number(masterAddingRow.value.stopOrder) : null,
                is_active:    true,
            });
            schoolMaster.value.push(mapSchool(data));
        } else if (_tab === 'itemTypes') {
            if (!masterAddingRow.value.name?.trim()) return;
            const { data } = await axios.post('/label-masters/item-types', {
                name: masterAddingRow.value.name.trim(), sort_order: 0, is_active: true,
            });
            itemTypeMaster.value.push(mapItemType(data));
        } else if (_tab === 'isshikiDestinations') {
            if (!masterAddingRow.value.name?.trim()) return;
            const nextOrder = isshikiMaster.value.length > 0
                ? Math.max(...isshikiMaster.value.map(t => t.sortOrder)) + 1 : 1;
            const { data } = await axios.post('/label-masters/isshiki-destinations', {
                name: masterAddingRow.value.name.trim(), sort_order: nextOrder, is_active: true,
            });
            isshikiMaster.value.push(mapIsshiki(data));
        } else if (_tab === 'areaMasters') {
            if (!masterAddingRow.value.name?.trim()) return;
            const nextOrder = areaMaster.value.length > 0
                ? Math.max(...areaMaster.value.map(t => t.sortOrder)) + 1 : 1;
            const { data } = await axios.post('/label-masters/area-masters', {
                name: masterAddingRow.value.name.trim(), sort_order: nextOrder, is_active: true,
            });
            areaMaster.value.push(mapArea(data));
        } else {
            if (!masterAddingRow.value.name?.trim()) return;
            const { data } = await axios.post('/label-masters/test-names', {
                name: masterAddingRow.value.name.trim(), sort_order: 0, is_active: true,
            });
            testNameMaster.value.push(mapTestName(data));
        }
    } catch (e) { alert('追加エラー: ' + (e.response?.data?.message || e.message)); }
    masterAddingRow.value = null;
}
async function masterToggleActive(item, tab = 'testNames') {
    const newVal = !item.isActive;
    try {
        if (tab === 'itemTypes') {
            const { data } = await axios.put(`/label-masters/item-types/${item.id}`, {
                name: item.name, sort_order: 0, is_active: newVal,
            });
            const idx = itemTypeMaster.value.findIndex(t => t.id === item.id);
            if (idx >= 0) itemTypeMaster.value[idx] = mapItemType(data);
        } else if (tab === 'isshikiDestinations') {
            const { data } = await axios.put(`/label-masters/isshiki-destinations/${item.id}`, {
                name: item.name, sort_order: item.sortOrder ?? 0, is_active: newVal,
            });
            const idx = isshikiMaster.value.findIndex(t => t.id === item.id);
            if (idx >= 0) isshikiMaster.value[idx] = mapIsshiki(data);
        } else if (tab === 'areaMasters') {
            const { data } = await axios.put(`/label-masters/area-masters/${item.id}`, {
                name: item.name, sort_order: item.sortOrder ?? 0, is_active: newVal,
            });
            const idx = areaMaster.value.findIndex(t => t.id === item.id);
            if (idx >= 0) areaMaster.value[idx] = mapArea(data);
        } else {
            const { data } = await axios.put(`/label-masters/test-names/${item.id}`, {
                name: item.name, sort_order: 0, is_active: newVal,
            });
            const idx = testNameMaster.value.findIndex(t => t.id === item.id);
            if (idx >= 0) testNameMaster.value[idx] = mapTestName(data);
        }
    } catch (e) { alert('保存エラー: ' + e.message); }
}
async function masterDelete(tab, id) {
    if (!confirm('削除しますか？')) return;
    try {
        if (tab === 'schools') {
            await axios.delete(`/label-masters/schools/${id}`);
            schoolMaster.value = schoolMaster.value.filter(r => r.id !== id);
        } else if (tab === 'itemTypes') {
            await axios.delete(`/label-masters/item-types/${id}`);
            itemTypeMaster.value = itemTypeMaster.value.filter(r => r.id !== id);
        } else if (tab === 'isshikiDestinations') {
            await axios.delete(`/label-masters/isshiki-destinations/${id}`);
            isshikiMaster.value = isshikiMaster.value.filter(r => r.id !== id);
        } else if (tab === 'areaMasters') {
            await axios.delete(`/label-masters/area-masters/${id}`);
            areaMaster.value = areaMaster.value.filter(r => r.id !== id);
        } else {
            await axios.delete(`/label-masters/test-names/${id}`);
            testNameMaster.value = testNameMaster.value.filter(r => r.id !== id);
        }
    } catch (e) { alert('削除エラー: ' + e.message); }
}

function masterListForTab(tab) {
    if (tab === 'testNames')           return testNameMaster;
    if (tab === 'itemTypes')           return itemTypeMaster;
    if (tab === 'isshikiDestinations') return isshikiMaster;
    if (tab === 'areaMasters')         return areaMaster;
    return null;
}

// 並べ替えモード
const reorderMode     = ref(null); // null | 'testNames' | 'itemTypes' | 'isshikiDestinations' | 'areaMasters'
const reorderSnapshot = ref(null); // 元の配列のバックアップ

function masterStartReorder(tab) {
    const list = masterListForTab(tab);
    if (!list) return;
    reorderSnapshot.value = list.value.map(item => ({ ...item }));
    reorderMode.value = tab;
    masterAddingRow.value = null;
}
async function masterSaveReorder(tab) {
    const list = masterListForTab(tab);
    if (!list) return;
    try {
        await axios.post('/label-masters/reorder', {
            type: tab,
            ids: list.value.map(t => t.id),
        });
        list.value.forEach((item, i) => { item.sortOrder = i + 1; });
        reorderMode.value = null;
        reorderSnapshot.value = null;
    } catch (e) { alert('並べ替え保存エラー: ' + e.message); }
}
function masterCancelReorder(tab) {
    const list = masterListForTab(tab);
    if (!list || !reorderSnapshot.value) return;
    list.value = reorderSnapshot.value;
    reorderMode.value = null;
    reorderSnapshot.value = null;
}
function masterMoveItem(tab, index, direction) {
    const list = masterListForTab(tab);
    if (!list) return;
    const arr = list.value;
    const swapIdx = index + direction;
    if (swapIdx < 0 || swapIdx >= arr.length) return;
    const tmp = arr[index];
    arr[index] = arr[swapIdx];
    arr[swapIdx] = tmp;
    list.value = [...arr];
}

// ── 試験項目 ──────────────────────────────────────────────
let _uid = 0;
function newEntry() {
    return { id: ++_uid, date: '', name: '', title: '', titleLocked: false, grades: [], ichishiki: false };
}

const testEntries = ref([newEntry()]);

function addEntry() { testEntries.value.push(newEntry()); }
function removeEntry(idx) { if (testEntries.value.length > 1) testEntries.value.splice(idx, 1); }
function duplicateEntry(idx) {
    const s = testEntries.value[idx];
    testEntries.value.splice(idx + 1, 0, {
        id: ++_uid, date: s.date, name: s.name, title: s.title,
        titleLocked: s.titleLocked, grades: [...s.grades], ichishiki: s.ichishiki,
    });
}
function toggleGrade(idx, g) {
    const arr = testEntries.value[idx].grades;
    const i   = arr.indexOf(g);
    if (i >= 0) arr.splice(i, 1); else arr.push(g);
}
function onNameInput(idx) {
    const e = testEntries.value[idx];
    if (!e.titleLocked) e.title = e.name;
}
function onTitleInput(idx) { testEntries.value[idx].titleLocked = true; }

// ── Excel パース ──────────────────────────────────────────
const excelName         = ref('');
const excelLoaded       = ref(false);
const detectedItems     = ref([]);    // 全シートを通じて見つかったアイテム記号
const detectedGrades    = ref([]);    // 部数データがある学年
const detectedDates     = ref([]);    // シート名から抽出した MMDD
const gradeItemMapRef   = ref({});    // { '3年': ['①','②','③','④'], '4年': [...] }
const ichishikiDetected  = ref(false); // 一式シートまたは黄色セルが存在するか
const isshikiDestCount   = ref(0);    // Excel から検出した一式宛先数
const symDataRef         = ref({});   // { dateCode → { sym → { schools, subject, itemLabel, maxBox } } }
const sheetDataRef       = ref([]);   // [{ name, grades, schools }] — データ照会用シート別データ

function handleExcelUpload(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    excelName.value       = file.name;
    excelLoaded.value     = false;
    detectedItems.value   = [];
    detectedGrades.value  = [];
    detectedDates.value   = [];
    gradeItemMapRef.value = {};
    ichishikiDetected.value = false;
    isshikiDestCount.value  = 0;
    symDataRef.value        = {};
    sheetDataRef.value      = [];
    dataviewSheet.value     = 0;

    const reader = new FileReader();
    reader.onload = ev => {
        const wb = XLSX.read(ev.target.result, { type: 'array', codepage: 932, cellStyles: true });
        parseWorkbook(wb);
        excelLoaded.value = true;
        updateOutputName();
    };
    reader.readAsArrayBuffer(file);
}

function parseWorkbook(wb) {
    const symSet       = new Set();
    const gradeItemMap = new Map();
    const gradeHasData = new Set();
    const dateSet      = new Set();
    let hasIchishiki   = false;
    const newSymData   = {};  // { dateCode → { sym → { schools, subject, itemLabel, maxBox } } }
    const newSheetList = [];  // データ照会用 [{ name, grades, schools }]

    for (const sn of wb.SheetNames) {
        const n  = sn.normalize('NFKC');
        const dc = extractDateCode(sn);
        if (dc) dateSet.add(dc);

        if (/テストサービス|TS宛紙不要|\bTS\b/.test(n)) continue;

        const ws   = wb.Sheets[sn];
        const data = XLSX.utils.sheet_to_json(ws, { header: 1, defval: '' });

        // 一式シート: 黄色セルの宛先と部数を抽出して格納し、通常処理はスキップ
        if (/一式/.test(n)) {
            hasIchishiki = true;
            const gradeCols    = detectGradeCols(data);
            const isshikiDests = parseIsshikiRows(ws, data, gradeCols);
            if (isshikiDests.length > 0) {
                const bucket = dc ?? '__common';
                if (!newSymData[bucket]) newSymData[bucket] = {};
                newSymData[bucket]._isshikiDestinations = isshikiDests;
            }
            continue;
        }

        // ── アイテム記号をヘッダー行から収集 (rows 0-19, cols 6-20) ──
        // ⚠️ normalize('NFKC') は ①→1 に変換するため生文字列で検索する
        const sheetSymbols = new Set();
        for (let r = 0; r <= Math.min(19, data.length - 1); r++) {
            const row = data[r] || [];
            for (let c = 6; c < Math.min(row.length, 20); c++) {
                const raw = String(row[c] || '');
                for (const ch of raw) { if (ITEM_SYMBOLS.includes(ch)) sheetSymbols.add(ch); }
            }
        }

        // ── 学年列を自動検出してデータ行をスキャン ──
        const gradeCols     = detectGradeCols(data);
        const gradesInSheet = new Set();
        for (let r = 7; r < data.length; r++) {
            const row = data[r] || [];
            for (const [colStr, grade] of Object.entries(gradeCols)) {
                const qty = parseFloat(String(row[Number(colStr)] || '').replace(/,/g, ''));
                if (qty > 0) { gradesInSheet.add(grade); gradeHasData.add(grade); }
            }
        }

        // ── 学年別アイテムマップを更新 ──
        if (sheetSymbols.size > 0 && gradesInSheet.size > 0) {
            for (const sym of sheetSymbols) symSet.add(sym);
            for (const grade of gradesInSheet) {
                if (!gradeItemMap.has(grade)) gradeItemMap.set(grade, new Set());
                for (const sym of sheetSymbols) gradeItemMap.get(grade).add(sym);
            }
        }

        // ── 凡例（NFKC-safe）と教室データを抽出して symData に格納 ──
        const legendItems = extractLegendItemsV2(data);
        const schools     = parseSchoolRows(data, gradeCols);

        // データ照会用: シート単位のデータを収集
        newSheetList.push({
            name:    sn,
            grades:  GRADE_ORDER.filter(g => gradesInSheet.has(g)),
            schools,
        });

        const bucket      = dc ?? '__common';
        if (!newSymData[bucket]) newSymData[bucket] = {};
        for (const li of legendItems) {
            // 同じ日付×シンボルが複数シートにある場合は最初のシートを使用
            if (!newSymData[bucket][li.sym]) {
                newSymData[bucket][li.sym] = {
                    schools, subject: li.subject, itemLabel: li.itemLabel, maxBox: li.maxBox,
                };
            }
        }
    }

    const sortSym = arr => arr.sort((a, b) => ITEM_SYMBOLS.indexOf(a) - ITEM_SYMBOLS.indexOf(b));

    detectedItems.value     = sortSym([...symSet]);
    detectedGrades.value    = GRADE_ORDER.filter(g => gradeHasData.has(g));
    detectedDates.value     = [...dateSet].sort();
    ichishikiDetected.value = hasIchishiki;
    symDataRef.value        = newSymData;
    sheetDataRef.value      = newSheetList;
    dataviewSheet.value     = 0;
    dataviewGrade.value     = newSheetList[0]?.grades[0] ?? detectedGrades.value[0] ?? '';
    const allIsshikiDests = Object.values(newSymData).flatMap(b => b._isshikiDestinations ?? []);
    isshikiDestCount.value = allIsshikiDests.length;

    gradeItemMapRef.value = Object.fromEntries(
        [...gradeItemMap.entries()].map(([g, s]) => [g, sortSym([...s])])
    );
}

// ── 出力フォルダ名 ────────────────────────────────────────
const outputName = ref('');

function isoToMMDD(iso) {
    const m = iso.match(/^\d{4}-(\d{2})-(\d{2})$/);
    return m ? m[1] + m[2] : '';
}

function gradeRangeText(grades) {
    const nums = [...new Set(grades)].map(g => parseInt(g)).filter(n => !isNaN(n)).sort((a, b) => a - b);
    if (!nums.length) return '';
    return nums.length === 1 ? `${nums[0]}年` : `${nums[0]}-${nums[nums.length - 1]}年`;
}

function updateOutputName() {
    const e   = testEntries.value;
    const dates  = [...new Set(e.map(x => x.date).filter(Boolean))].sort();
    const titles = [...new Set(e.map(x => x.title || x.name).filter(Boolean))];
    const grades = [...new Set(e.flatMap(x => x.grades))];
    outputName.value = [
        dates.map(d => isoToMMDD(d)).filter(Boolean).join('_'),
        titles[0] || '',
        gradeRangeText(grades),
    ].filter(Boolean).join('');
}

watch(testEntries, updateOutputName, { deep: true });

// ── PDFグループ（表示用）─────────────────────────────────
const pdfGroups = computed(() => {
    const map = new Map();
    for (const entry of testEntries.value) {
        const mmdd  = isoToMMDD(entry.date);
        const title = entry.title || entry.name;
        if (!entry.grades.length || (!mmdd && !title)) continue;

        const key = mmdd || '_';
        if (!map.has(key)) map.set(key, { mmdd, isoDate: entry.date, testName: entry.name, gradeMap: new Map() });
        const grp = map.get(key);

        for (const grade of entry.grades) {
            if (!grp.gradeMap.has(grade)) grp.gradeMap.set(grade, []);
            const files = grp.gradeMap.get(grade);

            // 学年別アイテム → 全体アイテム → フォールバック '①' の優先順
            const gradeItems = gradeItemMapRef.value[grade];
            const items = gradeItems?.length
                ? gradeItems
                : (detectedItems.value.length ? detectedItems.value : ['①']);

            for (const sym of items)
                files.push({ name: `${mmdd}${title}${grade}${sym}.pdf`, isIchishiki: false, sym, title });
            if (entry.ichishiki)
                files.push({ name: `${mmdd}${title}${grade}一式.pdf`, isIchishiki: true, sym: '一式', title });
        }
    }

    return [...map.values()]
        .sort((a, b) => a.mmdd.localeCompare(b.mmdd))
        .map(g => {
            const grades = [...g.gradeMap.entries()]
                .sort((a, b) => parseInt(a[0]) - parseInt(b[0]))
                .map(([grade, files]) => ({ grade, files }));
            return {
                mmdd:       g.mmdd,
                isoDate:    g.isoDate,
                testName:   g.testName,
                grades,
                totalFiles: grades.reduce((s, x) => s + x.files.length, 0),
            };
        });
});

// ── Canvas レンダリング ─────────────────────────────────────
// PW / PH は定数として上部で定義済み（729 × 516 pt）

// V1 drawLabel と同じ実装（座標は原本PDFから実測）
function drawLabel(ctx, label) {
    const s = n => n;  // SCALE=1
    const W = PW, H = PH;
    const F = (size, weight = 'normal') => {
        ctx.font = `${weight} ${s(size)}px "Hiragino Sans","Meiryo","MS Gothic",sans-serif`;
    };

    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, W, H);
    ctx.strokeStyle = '#000';
    ctx.lineWidth = 1;
    ctx.strokeRect(s(4), s(4), W - s(8), H - s(8));

    // メール便下の短い横線
    ctx.strokeStyle = '#000';
    ctx.lineWidth = 2;
    ctx.beginPath(); ctx.moveTo(s(77), s(89)); ctx.lineTo(s(211), s(89)); ctx.stroke();

    ctx.fillStyle = '#000';
    ctx.textBaseline = 'alphabetic';

    // 路線コード
    if (label.routeCode) {
        F(42, '600'); ctx.textAlign = 'left';
        ctx.fillText(label.routeCode, s(148), s(85));
    }
    F(14, 'normal'); ctx.fillText('メール便', s(78), s(84));
    F(18, 'normal'); ctx.textAlign = 'right';
    ctx.fillText(`${label.boxNum} / ${label.boxTotal}`, s(650), s(83));
    ctx.textAlign = 'left';

    // 教室名
    F(35, '600'); ctx.fillText(label.schoolName, s(140), s(152));
    F(22, 'normal'); ctx.fillText(label.schoolCode, s(74), s(153));
    F(35, '600');
    const nameW = ctx.measureText(label.schoolName).width;
    F(22, 'normal'); ctx.fillText('行', s(140) + nameW + s(6), s(153));

    // 実施日
    F(35, 'normal'); ctx.fillText(label.date || '', s(71), s(218));
    F(22, 'normal'); ctx.fillText('実施', s(403), s(219));

    // 学年枠（角丸）
    ctx.fillStyle = '#ffffff';
    ctx.beginPath(); ctx.roundRect(s(43), s(243), s(121), s(97), s(10)); ctx.fill();
    ctx.strokeStyle = '#000000'; ctx.lineWidth = s(3);
    ctx.beginPath(); ctx.roundRect(s(43), s(243), s(121), s(97), s(10)); ctx.stroke();
    ctx.fillStyle = '#000000';
    let gSize = 60; F(gSize, '900');
    while (gSize > 14 && label.grade && ctx.measureText(label.grade).width > s(110)) {
        gSize -= 2; F(gSize, '900');
    }
    ctx.fillText(label.grade || '', s(50), s(311));

    // テスト名
    if (label.testName) {
        const maxRight = label.subject ? s(490) : s(660);
        let tSize = 35; F(tSize, 'normal');
        while (tSize > 14 && ctx.measureText(label.testName).width > maxRight - s(239)) {
            tSize -= 2; F(tSize, 'normal');
        }
        ctx.fillText(label.testName, s(239), s(281));
    }
    if (label.subject) { F(46, '600'); ctx.fillText(label.subject, s(506), s(281)); }

    // アイテム名 + 部数
    F(33, 'normal');
    const iw = ctx.measureText(label.itemLabel).width;
    const itemCenterX = s(364);
    const itemStartX  = itemCenterX - iw / 2;
    ctx.fillText(label.itemLabel, itemStartX, s(330));
    ctx.fillText(`${label.quantity}部`, itemStartX + iw + s(33), s(330));

    // フッター
    F(12, 'normal');
    ctx.fillText(`通番 ${label.serial}`, s(602), s(370));
    ctx.fillText('(株)サンエー印刷', s(553), s(394));
}

// 学校データなし時の代替ラベル（Excelが未読み込みの場合など）
function drawNoDataLabel(ctx, { mmdd, testName, title, grade, sym }) {
    const W = PW, H = PH;
    const F = (size, weight = 'normal') => {
        ctx.font = `${weight} ${size}px "Hiragino Sans","Meiryo","MS Gothic",sans-serif`;
    };
    ctx.fillStyle = '#ffffff'; ctx.fillRect(0, 0, W, H);
    ctx.strokeStyle = '#000'; ctx.lineWidth = 1; ctx.strokeRect(4, 4, W - 8, H - 8);
    ctx.fillStyle = '#000'; ctx.textBaseline = 'alphabetic';
    F(28, '600'); ctx.textAlign = 'left';
    ctx.fillText(title || testName || '', 30, 52);
    if (mmdd?.length === 4) {
        F(20, 'normal'); ctx.textAlign = 'right';
        ctx.fillText(`${parseInt(mmdd.slice(0,2))}月${parseInt(mmdd.slice(2,4))}日実施`, W - 30, 52);
    }
    ctx.textAlign = 'left';
    F(80, '900'); ctx.fillText(grade || '', 40, 300);
    F(110, '900'); ctx.textAlign = 'center';
    ctx.fillText(sym || '', W * 0.68, 340);
    ctx.textAlign = 'left';
    ctx.fillStyle = '#aaa'; F(16, 'normal');
    ctx.fillText('(教室データなし - Excelを読み込んでください)', 30, H - 25);
}

// 一式ラベル（黄色セルの宛先をまとめて1枚に表示）
function drawIsshikiLabel(ctx, { destinations, grade, testName, date, serial, serialTotal }) {
    const W = PW, H = PH;
    const F = (size, weight = 'normal') => {
        ctx.font = `${weight} ${size}px "Hiragino Sans","Meiryo","MS Gothic",sans-serif`;
    };

    ctx.fillStyle = '#ffffff'; ctx.fillRect(0, 0, W, H);
    ctx.strokeStyle = '#000'; ctx.lineWidth = 1;
    ctx.strokeRect(4, 4, W - 8, H - 8);

    ctx.strokeStyle = '#000'; ctx.lineWidth = 2;
    ctx.beginPath(); ctx.moveTo(77, 89); ctx.lineTo(211, 89); ctx.stroke();

    ctx.fillStyle = '#000'; ctx.textBaseline = 'alphabetic';

    F(14, 'normal'); ctx.textAlign = 'left';
    ctx.fillText('メール便', 78, 84);
    F(18, 'normal'); ctx.textAlign = 'right';
    ctx.fillText(`${serial} / ${serialTotal}`, 650, 83);
    ctx.textAlign = 'left';

    // 本部一式 行
    F(42, '600');
    ctx.fillText('本部一式', 140, 152);
    const isshikiTitleW = ctx.measureText('本部一式').width;
    F(22, 'normal');
    ctx.fillText('行', 140 + isshikiTitleW + 6, 153);

    // 実施日
    F(35, 'normal'); ctx.fillText(date || '', 71, 218);
    F(22, 'normal'); ctx.fillText('実施', 403, 219);

    // 学年枠（角丸）
    ctx.fillStyle = '#ffffff';
    ctx.beginPath(); ctx.roundRect(43, 243, 121, 97, 10); ctx.fill();
    ctx.strokeStyle = '#000000'; ctx.lineWidth = 3;
    ctx.beginPath(); ctx.roundRect(43, 243, 121, 97, 10); ctx.stroke();
    ctx.fillStyle = '#000000';
    let gSize = 60; F(gSize, '900');
    while (gSize > 14 && grade && ctx.measureText(grade).width > 110) {
        gSize -= 2; F(gSize, '900');
    }
    ctx.fillText(grade || '', 50, 311);

    // テスト名（小さく）
    const listX = 239;
    if (testName) {
        let tSize = 22; F(tSize, 'normal');
        while (tSize > 12 && ctx.measureText(testName).width > 470) { tSize -= 2; F(tSize, 'normal'); }
        ctx.fillText(testName, listX, 258);
    }

    // 宛先一覧
    if (destinations.length > 0) {
        const listTop    = testName ? 278 : 255;
        const listBottom = 400;
        const available  = listBottom - listTop;
        const rawLineH   = Math.floor(available / Math.max(1, destinations.length));
        const lineH      = Math.max(14, Math.min(22, rawLineH));
        const fontSize   = Math.max(10, lineH - 4);
        F(fontSize, 'normal');
        destinations.forEach((dest, i) => {
            ctx.fillText(`■ ${dest}`, listX, listTop + i * lineH);
        });
    }

    // "一式"（右下）
    F(46, '900'); ctx.textAlign = 'right';
    ctx.fillText('一式', 710, 420);
    ctx.textAlign = 'left';

    // フッター
    F(12, 'normal');
    ctx.fillText(`通番 ${serial}`, 590, 455);
    ctx.fillText('(株)サンエー印刷', 540, 472);
}

// ── PDF生成 ────────────────────────────────────────────────
const currentView   = ref('config');   // 'config' | 'generated'
const isGenerating  = ref(false);
const generatedPdfs = ref([]);         // [{ name, blob, url, isIchishiki, mmdd, grade }]
const genError      = ref('');
const previewTarget = ref(null);       // { name, url } | null

async function generatePdfs() {
    if (!pdfGroups.value.length) return;
    isGenerating.value = true;
    genError.value     = '';
    generatedPdfs.value.forEach(p => URL.revokeObjectURL(p.url));
    generatedPdfs.value = [];

    try {
        const { jsPDF } = await import('jspdf');

        // Canvas は1つ使い回す（V1 と同じ方式: Canvas → JPEG → jsPDF）
        const canvas = document.createElement('canvas');
        canvas.width  = PW;
        canvas.height = PH;
        const ctx = canvas.getContext('2d');

        const results = [];
        for (const group of pdfGroups.value) {
            for (const { grade, files } of group.grades) {
                for (const file of files) {
                    const doc = new jsPDF({ orientation: 'landscape', unit: 'pt', format: [PW, PH] });
                    let pageCount = 1;

                    if (file.isIchishiki) {
                        // 一式ラベル：黄色セル宛先を抽出し、一式マスタの sort_order 順に並べて出力
                        const rawIsshikiDests = symDataRef.value[group.mmdd]?._isshikiDestinations
                            ?? symDataRef.value['__common']?._isshikiDestinations
                            ?? [];
                        // マスタの sort_order で並べ替え（マスタにない宛先は末尾）
                        const masterOrder = isshikiMaster.value;
                        const sortedDests = [...rawIsshikiDests].sort((a, b) => {
                            const ai = masterOrder.findIndex(m => m.name === a.name);
                            const bi = masterOrder.findIndex(m => m.name === b.name);
                            const aOrder = ai >= 0 ? masterOrder[ai].sortOrder : 99999;
                            const bOrder = bi >= 0 ? masterOrder[bi].sortOrder : 99999;
                            return aOrder - bOrder;
                        });
                        const dateDisplay = dateCodeToDisplay(group.mmdd);
                        const isshikiLabels = [];
                        for (const dest of sortedDests) {
                            const qty = dest.grades?.[grade] ?? 0;
                            if (!qty || qty <= 0) continue;
                            isshikiLabels.push({
                                routeCode: '', schoolCode: '',
                                schoolName: dest.name,
                                boxNum: 1, boxTotal: 1,
                                quantity: qty,
                                serial: 0,
                                testName: group.testName,
                                date: dateDisplay,
                                grade,
                                subject: '',
                                itemLabel: '一式',
                                _internalCode: dest.name,
                                _routeOrder: -1, _stopOrder: 9999,
                                _rowIdx: 0, _colIdx: 0,
                            });
                        }
                        isshikiLabels.forEach((l, i) => { l.serial = i + 1; });

                        if (isshikiLabels.length > 0) {
                            for (let li = 0; li < isshikiLabels.length; li++) {
                                if (li > 0) doc.addPage();
                                drawLabel(ctx, isshikiLabels[li]);
                                doc.addImage(canvas.toDataURL('image/jpeg', 0.85), 'JPEG', 0, 0, PW, PH);
                            }
                            pageCount = isshikiLabels.length;
                        } else {
                            drawNoDataLabel(ctx, {
                                mmdd: group.mmdd, testName: group.testName,
                                title: '一式', grade, sym: '一式',
                            });
                            doc.addImage(canvas.toDataURL('image/jpeg', 0.85), 'JPEG', 0, 0, PW, PH);
                            pageCount = 1;
                        }
                    } else {
                        // 通常ラベル：シンボル × 日付に対応する学校データを検索
                        const symEntry = symDataRef.value[group.mmdd]?.[file.sym]
                            ?? symDataRef.value['__common']?.[file.sym];

                        const labels = symEntry ? buildLabelsFromEntry(symEntry, grade, group) : [];

                        if (labels.length > 0) {
                            for (let li = 0; li < labels.length; li++) {
                                if (li > 0) doc.addPage();
                                drawLabel(ctx, labels[li]);
                                doc.addImage(canvas.toDataURL('image/jpeg', 0.85), 'JPEG', 0, 0, PW, PH);
                            }
                            pageCount = labels.length;
                        } else {
                            drawNoDataLabel(ctx, {
                                mmdd: group.mmdd, testName: group.testName,
                                title: file.title, grade, sym: file.sym,
                            });
                            doc.addImage(canvas.toDataURL('image/jpeg', 0.85), 'JPEG', 0, 0, PW, PH);
                            pageCount = 1;
                        }
                    }

                    const blob = doc.output('blob');
                    results.push({
                        name: file.name, blob,
                        url:  URL.createObjectURL(blob),
                        isIchishiki: file.isIchishiki,
                        mmdd: group.mmdd, grade,
                        pageCount,
                    });
                }
            }
        }
        generatedPdfs.value = results;
        currentView.value   = 'generated';
    } catch (err) {
        genError.value = err.message || 'PDF生成に失敗しました。';
    } finally {
        isGenerating.value = false;
    }
}

const generatedMap = computed(() => {
    const m = new Map();
    generatedPdfs.value.forEach(p => m.set(p.name, p));
    return m;
});

function downloadPdf(pdf) {
    const a = document.createElement('a');
    a.href = pdf.url; a.download = pdf.name; a.click();
}

async function saveToFolder() {
    if (!('showDirectoryPicker' in window)) {
        alert('このブラウザはフォルダ保存に対応していません（Chrome または Edge をご利用ください）。');
        return;
    }
    try {
        const dir = await window.showDirectoryPicker({ mode: 'readwrite' });
        for (const pdf of generatedPdfs.value) {
            const fh = await dir.getFileHandle(pdf.name, { create: true });
            const ws = await fh.createWritable();
            await ws.write(pdf.blob); await ws.close();
        }
        alert(`${generatedPdfs.value.length} ファイルを保存しました。`);
    } catch (e) {
        if (e.name !== 'AbortError') alert('フォルダ保存エラー: ' + e.message);
    }
}

function openPreview(pdf)  { previewTarget.value = pdf; }
function closePreview()    { previewTarget.value = null; }
function backToConfig()    { currentView.value = 'config'; }

const canGenerate = computed(() => pdfGroups.value.some(g => g.grades.length > 0));

// ── データ照会 / 部数集計 ─────────────────────────────────────
const showDataviewModal  = ref(false);
const showSummaryModal   = ref(false);
const dataviewGrade      = ref('');
const dataviewSheet      = ref(0);  // sheetDataRef のインデックス

const dataviewRows = computed(() => {
    if (!excelLoaded.value || !sheetDataRef.value.length) return [];
    const sheet = sheetDataRef.value[dataviewSheet.value];
    if (!sheet?.schools) return [];

    const grade = dataviewGrade.value;
    // 教室マスタの順番でソート
    const schoolOrderMap = new Map();
    schoolMaster.value.forEach((s, i) => schoolOrderMap.set(s.code, i));

    const rows = [];
    for (const school of Object.values(sheet.schools)) {
        rows.push({
            code: school.code,
            name: school.name,
            area: school.area || '',
            qty:  grade ? (school.grades?.[grade] ?? 0) : 0,
        });
    }
    rows.sort((a, b) => {
        const ai = schoolOrderMap.has(a.code) ? schoolOrderMap.get(a.code) : 9999;
        const bi = schoolOrderMap.has(b.code) ? schoolOrderMap.get(b.code) : 9999;
        if (ai !== bi) return ai - bi;
        return a.code.localeCompare(b.code);
    });
    return rows;
});

const summaryData = computed(() => {
    const areaOrder = areaMaster.value.map(a => a.name);
    const areaMap = new Map();
    for (const row of dataviewRows.value) {
        const area = row.area || '未分類';
        if (!areaMap.has(area)) areaMap.set(area, 0);
        areaMap.set(area, areaMap.get(area) + (row.qty || 0));
    }
    const result = [...areaMap.entries()].map(([area, qty]) => ({
        area, qty,
        sortOrder: areaOrder.indexOf(area),
    }));
    result.sort((a, b) => {
        const ao = a.sortOrder >= 0 ? a.sortOrder : 9999;
        const bo = b.sortOrder >= 0 ? b.sortOrder : 9999;
        return ao - bo;
    });
    return result;
});

const summaryTotal = computed(() => summaryData.value.reduce((s, r) => s + r.qty, 0));
</script>

<template>
    <div class="space-y-5">

        <!-- ━━ Config: 試験項目・Excel・フォルダ名 ━━━━━━━━━━━━━━━━ -->
        <template v-if="currentView === 'config'">

            <!-- マスタ管理ボタン -->
            <div class="rounded-lg bg-white shadow px-5 py-3 flex items-center gap-3">
                <span class="text-xs font-medium text-gray-400 mr-1">マスタ</span>
                <button @click="openMasterModal('testNames')" type="button"
                    class="px-3 py-1.5 text-xs border border-gray-300 rounded hover:bg-orange-50 hover:border-orange-300 text-gray-600 transition">
                    試験名マスタ（{{ testNameMaster.length }}件）
                </button>
                <button @click="openMasterModal('schools')" type="button"
                    class="px-3 py-1.5 text-xs border border-gray-300 rounded hover:bg-orange-50 hover:border-orange-300 text-gray-600 transition">
                    教室マスタ（{{ schoolMaster.length }}件）
                </button>
                <button @click="openMasterModal('itemTypes')" type="button"
                    class="px-3 py-1.5 text-xs border border-gray-300 rounded hover:bg-orange-50 hover:border-orange-300 text-gray-600 transition">
                    アイテムマスタ（{{ itemTypeMaster.length }}件）
                </button>
                <button @click="openMasterModal('isshikiDestinations')" type="button"
                    class="px-3 py-1.5 text-xs border border-gray-300 rounded hover:bg-orange-50 hover:border-orange-300 text-gray-600 transition">
                    一式マスタ（{{ isshikiMaster.length }}件）
                </button>
                <button @click="openMasterModal('routes')" type="button"
                    class="px-3 py-1.5 text-xs border border-gray-300 rounded hover:bg-blue-50 hover:border-blue-300 text-gray-600 transition">
                    社内便マスタ（{{ routeMaster.length }}ルート）
                </button>
                <button @click="openMasterModal('areaMasters')" type="button"
                    class="px-3 py-1.5 text-xs border border-gray-300 rounded hover:bg-orange-50 hover:border-orange-300 text-gray-600 transition">
                    エリアマスタ（{{ areaMaster.length }}件）
                </button>
            </div>

            <!-- 試験項目 -->
            <div class="rounded-lg bg-white shadow p-5 space-y-3">
                <h3 class="text-sm font-semibold text-gray-800 border-b pb-2">試験項目</h3>

                <div v-for="(entry, idx) in testEntries" :key="entry.id"
                    class="flex flex-wrap items-end gap-3 rounded border border-gray-200 bg-gray-50 px-4 py-3">

                    <div class="flex flex-col gap-1">
                        <label class="text-xs text-gray-500">試験日</label>
                        <input type="date" v-model="testEntries[idx].date"
                            class="rounded border border-gray-300 px-2 py-1.5 text-sm w-36 bg-white" />
                    </div>

                    <div class="flex flex-col gap-1 min-w-48 flex-1">
                        <label class="text-xs text-gray-500">試験名（宛紙に印字）</label>
                        <input type="text" v-model="testEntries[idx].name"
                            @input="onNameInput(idx)" list="test-name-list"
                            placeholder="例: 学習力育成テスト"
                            class="rounded border border-gray-300 px-2 py-1.5 text-sm w-full bg-white" />
                    </div>

                    <div class="flex flex-col gap-1 w-40">
                        <label class="text-xs text-gray-500">PDFタイトル（略称）</label>
                        <input type="text" v-model="testEntries[idx].title"
                            @input="onTitleInput(idx)"
                            placeholder="例: 春期特別"
                            class="rounded border border-gray-300 px-2 py-1.5 text-sm w-full bg-white" />
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-xs text-gray-500">対象学年</label>
                        <div class="flex gap-1">
                            <button v-for="g in GRADE_OPTIONS" :key="g"
                                @click.prevent="toggleGrade(idx, g)" type="button"
                                class="w-10 py-1.5 text-xs rounded border transition font-medium"
                                :class="entry.grades.includes(g)
                                    ? 'bg-orange-500 text-white border-orange-500'
                                    : 'bg-white text-gray-600 border-gray-300 hover:bg-orange-50'">
                                {{ g }}
                            </button>
                        </div>
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-xs text-gray-500">&nbsp;</label>
                        <label class="flex items-center gap-1.5 cursor-pointer text-sm text-gray-700 py-1.5 px-1">
                            <input type="checkbox" v-model="testEntries[idx].ichishiki"
                                class="rounded border-gray-300 text-orange-500 w-4 h-4" />
                            <span>一式</span>
                        </label>
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-xs text-gray-500">&nbsp;</label>
                        <div class="flex gap-1.5">
                            <button @click.prevent="duplicateEntry(idx)" type="button"
                                class="px-3 py-1.5 text-xs rounded border border-gray-300 bg-white text-gray-600 hover:bg-gray-100 transition">複製</button>
                            <button @click.prevent="removeEntry(idx)" type="button"
                                :disabled="testEntries.length <= 1"
                                class="px-3 py-1.5 text-xs rounded border border-red-200 text-red-400 hover:bg-red-50 transition disabled:opacity-30 disabled:cursor-not-allowed">削除</button>
                        </div>
                    </div>
                </div>

                <button @click.prevent="addEntry" type="button"
                    class="px-4 py-1.5 text-sm text-orange-600 border border-orange-300 rounded hover:bg-orange-50 transition">
                    + 追加
                </button>
            </div>

            <!-- Excel -->
            <div class="rounded-lg bg-white shadow p-5 space-y-3">
                <h3 class="text-sm font-semibold text-gray-800 border-b pb-2">発送部数 Excel（s1_*.xls）</h3>
                <input type="file" accept=".xls,.xlsx" @change="handleExcelUpload"
                    class="block text-sm text-gray-700
                        file:mr-3 file:py-1.5 file:px-4 file:rounded file:border-0
                        file:text-sm file:bg-orange-100 file:text-orange-800
                        hover:file:bg-orange-200 cursor-pointer" />
                <div v-if="excelLoaded" class="space-y-1 text-xs">
                    <p class="text-green-700 font-medium">✔ {{ excelName }}</p>
                    <p v-if="detectedDates.length" class="text-gray-600">
                        検出日付（パターンA）:<span class="ml-1 font-medium text-orange-700">{{ detectedDates.join(' / ') }}</span>
                    </p>
                    <p v-if="ichishikiDetected" class="text-gray-600">
                        一式（黄色セル宛先）:<span class="ml-1 font-medium text-orange-700">{{ isshikiDestCount }} 件</span>
                    </p>
                    <!-- 学年別アイテムマップ -->
                    <div v-if="Object.keys(gradeItemMapRef).length" class="mt-1 space-y-0.5">
                        <p class="text-gray-500 font-medium">学年別検出アイテム:</p>
                        <div v-for="(items, grade) in gradeItemMapRef" :key="grade" class="flex items-center gap-2 ml-2">
                            <span class="text-orange-600 font-semibold w-12">{{ grade }}</span>
                            <span class="text-gray-700">{{ items.join(' ') }}</span>
                        </div>
                    </div>
                    <p v-else-if="detectedItems.length" class="text-gray-600">
                        検出アイテム:<span class="ml-1 font-medium text-orange-700">{{ detectedItems.join(' ') }}</span>
                    </p>
                    <p v-if="!detectedDates.length" class="text-yellow-600 mt-1">
                        ※ シート名に日付なし（パターンB）。試験項目の試験日を手動設定してください。
                    </p>
                    <div class="flex gap-2 mt-3">
                        <button @click="showDataviewModal=true" type="button"
                            class="px-4 py-1.5 text-xs bg-blue-600 text-white rounded hover:bg-blue-700 transition">
                            データ照会
                        </button>
                    </div>
                </div>
                <p v-else class="text-xs text-gray-400">Shift-JIS .xls / .xlsx 対応</p>
            </div>

            <!-- 出力フォルダ名 -->
            <div class="rounded-lg bg-white shadow p-5 space-y-2">
                <h3 class="text-sm font-semibold text-gray-800 border-b pb-2">出力フォルダ名</h3>
                <input type="text" v-model="outputName"
                    placeholder="試験日・PDFタイトル・学年から自動生成されます"
                    class="w-full rounded border border-gray-300 px-3 py-1.5 text-sm bg-white" />
            </div>

        </template>

        <!-- ━━ Generated: ヘッダーバー ━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
        <div v-if="currentView === 'generated'"
            class="rounded-lg bg-green-50 border border-green-200 p-4 flex flex-wrap items-center gap-3 justify-between">
            <div>
                <p class="text-sm font-semibold text-green-800">PDF生成完了 — {{ generatedPdfs.length }} ファイル</p>
                <p class="text-xs text-green-600 mt-0.5">{{ outputName }}</p>
            </div>
            <div class="flex gap-2">
                <button @click="saveToFolder"
                    class="px-4 py-2 text-sm rounded-lg bg-blue-600 text-white hover:bg-blue-700 font-medium transition">
                    フォルダに保存
                </button>
                <button @click="backToConfig"
                    class="px-3 py-2 text-sm rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50 transition">
                    ← 設定に戻る
                </button>
            </div>
        </div>

        <!-- ━━ PDFリスト（config・generated 共通）━━━━━━━━━━━━━━━━ -->
        <div v-if="pdfGroups.length > 0" class="space-y-4">

            <div v-for="group in pdfGroups" :key="group.mmdd"
                class="rounded-lg bg-white shadow overflow-hidden">

                <!-- 日付グループヘッダー -->
                <div class="bg-orange-50 border-b border-orange-100 px-5 py-3 flex items-center gap-3">
                    <span class="text-xl font-bold text-orange-700 tracking-wide">{{ group.mmdd }}</span>
                    <span class="text-sm text-gray-600">{{ group.testName }}</span>
                    <span class="ml-auto text-xs text-gray-400">{{ group.totalFiles }} ファイル</span>
                </div>

                <!-- 学年サブグループ -->
                <div v-for="gradeGroup in group.grades" :key="gradeGroup.grade"
                    class="border-b border-gray-100 last:border-0">

                    <!-- 学年ヘッダー -->
                    <div class="bg-gray-50 border-b border-gray-100 px-5 py-2 flex items-center gap-2">
                        <span class="text-sm font-bold text-orange-500">{{ gradeGroup.grade }}</span>
                        <span class="text-xs text-gray-400">{{ gradeGroup.files.length }} ファイル</span>
                    </div>

                    <!-- ファイル一覧（縦並び） -->
                    <div class="divide-y divide-gray-50">
                        <div v-for="file in gradeGroup.files" :key="file.name"
                            class="flex items-center gap-3 px-5 py-2 hover:bg-gray-50 transition">

                            <!-- ファイル名 -->
                            <span class="flex-1 text-sm font-mono"
                                :class="file.isIchishiki
                                    ? 'text-orange-600 font-semibold'
                                    : 'text-gray-700'">
                                {{ file.name }}
                            </span>

                            <!-- 生成後: DL ボタン -->
                            <button v-if="currentView === 'generated' && generatedMap.has(file.name)"
                                @click="downloadPdf(generatedMap.get(file.name))"
                                class="px-3 py-1 text-xs rounded border border-green-300 text-green-600 hover:bg-green-50 transition">
                                DL
                            </button>
                            <span v-else
                                class="px-3 py-1 text-xs rounded border border-gray-200 text-gray-300">DL</span>
                        </div>
                    </div>

                </div>
            </div>

            <p v-if="!detectedItems.length" class="text-xs text-yellow-600 px-1">
                ※ Excelからアイテム記号（①②...）が未検出のため、① のみ表示しています。
            </p>
        </div>

        <!-- ━━ PDF作成ボタン (config のみ) ━━━━━━━━━━━━━━━━━━━━━━ -->
        <div v-if="currentView === 'config'" class="flex items-center justify-end gap-4 pt-1">
            <p v-if="genError" class="flex-1 text-sm text-red-500">{{ genError }}</p>
            <button @click="generatePdfs"
                :disabled="!canGenerate || isGenerating"
                class="px-7 py-2.5 rounded-lg text-sm font-semibold transition
                       bg-orange-500 text-white hover:bg-orange-600 shadow
                       disabled:opacity-40 disabled:cursor-not-allowed">
                <span v-if="isGenerating">生成中...</span>
                <span v-else>PDF を作成</span>
            </button>
        </div>

        <!-- datalist（試験名マスタのアクティブ項目のみ）-->
        <datalist id="test-name-list">
            <option v-for="t in testNameMaster.filter(t => t.isActive)" :key="t.id" :value="t.name" />
        </datalist>

        <!-- ━━ マスタ管理モーダル ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
        <Teleport to="body">
            <div v-if="showMasterModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60"
                @click.self="showMasterModal=false">
                <div class="bg-white rounded-xl shadow-2xl flex flex-col overflow-hidden"
                    :style="masterTab==='routes' ? 'width:98vw;max-width:1500px;height:92vh;' : 'width:92vw;max-width:960px;height:82vh;'">

                    <!-- モーダルヘッダー・タブ -->
                    <div class="flex items-center justify-between px-5 py-3 border-b bg-gray-50 flex-shrink-0">
                        <div class="flex gap-2">
                            <button @click="masterTab='testNames'" type="button"
                                class="px-4 py-1.5 text-sm rounded-full font-medium transition"
                                :class="masterTab==='testNames' ? 'bg-orange-500 text-white' : 'text-gray-600 hover:bg-gray-100'">
                                試験名マスタ
                            </button>
                            <button @click="masterTab='schools'" type="button"
                                class="px-4 py-1.5 text-sm rounded-full font-medium transition"
                                :class="masterTab==='schools' ? 'bg-orange-500 text-white' : 'text-gray-600 hover:bg-gray-100'">
                                教室マスタ
                            </button>
                            <button @click="masterTab='itemTypes'" type="button"
                                class="px-4 py-1.5 text-sm rounded-full font-medium transition"
                                :class="masterTab==='itemTypes' ? 'bg-orange-500 text-white' : 'text-gray-600 hover:bg-gray-100'">
                                アイテムマスタ
                            </button>
                            <button @click="masterTab='isshikiDestinations'" type="button"
                                class="px-4 py-1.5 text-sm rounded-full font-medium transition"
                                :class="masterTab==='isshikiDestinations' ? 'bg-orange-500 text-white' : 'text-gray-600 hover:bg-gray-100'">
                                一式マスタ
                            </button>
                            <button @click="masterTab='routes'; editingStop=null; editingRoute=null" type="button"
                                class="px-4 py-1.5 text-sm rounded-full font-medium transition"
                                :class="masterTab==='routes' ? 'bg-orange-500 text-white' : 'text-gray-600 hover:bg-gray-100'">
                                社内便マスタ
                            </button>
                            <button @click="masterTab='areaMasters'" type="button"
                                class="px-4 py-1.5 text-sm rounded-full font-medium transition"
                                :class="masterTab==='areaMasters' ? 'bg-orange-500 text-white' : 'text-gray-600 hover:bg-gray-100'">
                                エリアマスタ
                            </button>
                        </div>
                        <button @click="showMasterModal=false" type="button"
                            class="text-gray-400 hover:text-gray-700 text-xl leading-none px-2">✕</button>
                    </div>

                    <!-- 試験名マスタ -->
                    <div v-if="masterTab==='testNames'" class="flex-1 overflow-y-auto p-5 space-y-2">
                        <div class="flex justify-end gap-2 mb-1">
                            <template v-if="reorderMode==='testNames'">
                                <button @click="masterSaveReorder('testNames')" type="button"
                                    class="px-3 py-1.5 text-xs bg-green-600 text-white rounded hover:bg-green-700">保存</button>
                                <button @click="masterCancelReorder('testNames')" type="button"
                                    class="px-3 py-1.5 text-xs border rounded text-gray-500 hover:bg-gray-100">キャンセル</button>
                            </template>
                            <template v-else>
                                <button @click="masterStartReorder('testNames')" type="button"
                                    class="px-3 py-1.5 text-xs border border-gray-300 rounded text-gray-600 hover:bg-gray-50">並べ替え</button>
                                <button @click="masterStartAdd" type="button"
                                    class="px-3 py-1.5 text-xs bg-orange-500 text-white rounded hover:bg-orange-600 transition">+ 追加</button>
                            </template>
                        </div>
                        <!-- 追加フォーム -->
                        <div v-if="masterAddingRow?._tab==='testNames'"
                            class="flex gap-2 items-center bg-orange-50 border border-orange-200 rounded p-2">
                            <input v-model="masterAddingRow.name" placeholder="試験名を入力"
                                class="flex-1 text-sm border rounded px-2 py-1 bg-white"
                                @keyup.enter="masterSaveAdd" @keyup.escape="masterCancelAdd" autofocus />
                            <button @click="masterSaveAdd" type="button"
                                class="px-3 py-1 text-xs bg-green-500 text-white rounded">保存</button>
                            <button @click="masterCancelAdd" type="button"
                                class="px-3 py-1 text-xs border rounded text-gray-500">取消</button>
                        </div>
                        <!-- リスト -->
                        <div v-for="(item, idx) in testNameMaster" :key="item.id"
                            class="flex gap-2 items-center py-1.5 border-b border-gray-100 last:border-0">
                            <!-- 並べ替えボタン（並べ替えモードのみ） -->
                            <div v-if="reorderMode==='testNames'" class="flex flex-col gap-0.5 shrink-0">
                                <button @click="masterMoveItem('testNames', idx, -1)" type="button"
                                    :disabled="idx===0"
                                    class="w-5 h-4 text-xs leading-none border rounded text-gray-400 hover:bg-gray-100 disabled:opacity-20 disabled:cursor-not-allowed">▲</button>
                                <button @click="masterMoveItem('testNames', idx, 1)" type="button"
                                    :disabled="idx===testNameMaster.length-1"
                                    class="w-5 h-4 text-xs leading-none border rounded text-gray-400 hover:bg-gray-100 disabled:opacity-20 disabled:cursor-not-allowed">▼</button>
                            </div>
                            <!-- 常時表示：表示チェックボックス -->
                            <label class="flex items-center gap-1 text-xs text-gray-500 whitespace-nowrap cursor-pointer shrink-0"
                                :title="item.isActive ? 'クリックで非表示' : 'クリックで表示'">
                                <input type="checkbox" :checked="item.isActive"
                                    @change="masterToggleActive(item)" class="rounded" />
                                表示
                            </label>
                            <template v-if="masterEditingId===item.id">
                                <input v-model="masterEditingRow.name"
                                    class="flex-1 text-sm border rounded px-2 py-0.5"
                                    @keyup.enter="masterSaveEdit('testNames')" @keyup.escape="masterCancelEdit" />
                                <button @click="masterSaveEdit('testNames')" type="button"
                                    class="px-2 py-0.5 text-xs bg-green-500 text-white rounded">保存</button>
                                <button @click="masterCancelEdit" type="button"
                                    class="px-2 py-0.5 text-xs border rounded text-gray-500">取消</button>
                            </template>
                            <template v-else>
                                <span class="flex-1 text-sm"
                                    :class="item.isActive ? 'text-gray-800' : 'text-gray-400 line-through'">{{ item.name }}</span>
                                <button @click="masterStartEdit(item)" type="button"
                                    class="px-2 py-0.5 text-xs border border-gray-300 rounded text-gray-600 hover:bg-gray-50">編集</button>
                                <button @click="masterDelete('testNames', item.id)" type="button"
                                    class="px-2 py-0.5 text-xs border border-red-200 text-red-400 rounded hover:bg-red-50">削除</button>
                            </template>
                        </div>
                        <p v-if="!testNameMaster.length" class="text-xs text-gray-400 text-center py-4">
                            試験名が登録されていません。「+ 追加」から登録してください。
                        </p>
                    </div>

                    <!-- アイテムマスタ -->
                    <div v-if="masterTab==='itemTypes'" class="flex-1 overflow-y-auto p-5 space-y-2">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-sm font-semibold text-gray-700">アイテムマスタ（{{ itemTypeMaster.length }}件）</h3>
                            <div class="flex gap-2">
                                <template v-if="reorderMode==='itemTypes'">
                                    <button @click="masterSaveReorder('itemTypes')" type="button"
                                        class="px-3 py-1 text-xs bg-green-600 text-white rounded hover:bg-green-700">保存</button>
                                    <button @click="masterCancelReorder('itemTypes')" type="button"
                                        class="px-3 py-1 text-xs border rounded text-gray-500 hover:bg-gray-100">キャンセル</button>
                                </template>
                                <template v-else>
                                    <button @click="masterStartReorder('itemTypes')" type="button"
                                        class="px-3 py-1 text-xs border border-gray-300 rounded text-gray-600 hover:bg-gray-50">並べ替え</button>
                                    <button @click="masterStartAdd" type="button"
                                        class="px-3 py-1 text-xs bg-orange-500 text-white rounded hover:bg-orange-600">+ 追加</button>
                                </template>
                            </div>
                        </div>
                        <div v-if="masterAddingRow?._tab==='itemTypes'"
                            class="flex items-center gap-2 p-2 border border-orange-300 rounded bg-orange-50">
                            <input v-model="masterAddingRow.name" placeholder="アイテム名を入力"
                                class="flex-1 text-sm border rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-orange-400"
                                @keyup.enter="masterSaveAdd" @keyup.escape="masterCancelAdd" autofocus />
                            <button @click="masterSaveAdd" type="button"
                                class="px-2 py-0.5 text-xs bg-orange-500 text-white rounded">追加</button>
                            <button @click="masterCancelAdd" type="button"
                                class="px-2 py-0.5 text-xs border rounded text-gray-500">取消</button>
                        </div>
                        <div v-for="(item, idx) in itemTypeMaster" :key="item.id"
                            class="flex items-center gap-2 px-3 py-2 border rounded hover:bg-gray-50">
                            <!-- 並べ替えボタン（並べ替えモードのみ） -->
                            <div v-if="reorderMode==='itemTypes'" class="flex flex-col gap-0.5 shrink-0">
                                <button @click="masterMoveItem('itemTypes', idx, -1)" type="button"
                                    :disabled="idx===0"
                                    class="w-5 h-4 text-xs leading-none border rounded text-gray-400 hover:bg-gray-100 disabled:opacity-20 disabled:cursor-not-allowed">▲</button>
                                <button @click="masterMoveItem('itemTypes', idx, 1)" type="button"
                                    :disabled="idx===itemTypeMaster.length-1"
                                    class="w-5 h-4 text-xs leading-none border rounded text-gray-400 hover:bg-gray-100 disabled:opacity-20 disabled:cursor-not-allowed">▼</button>
                            </div>
                            <input type="checkbox" :checked="item.isActive"
                                @change="masterToggleActive(item, 'itemTypes')" class="rounded" />
                            <template v-if="masterEditingId===item.id">
                                <input v-model="masterEditingRow.name" class="flex-1 text-sm border rounded px-2 py-0.5 focus:outline-none focus:ring-1 focus:ring-orange-400"
                                    @keyup.enter="masterSaveEdit('itemTypes')" @keyup.escape="masterCancelEdit" />
                                <button @click="masterSaveEdit('itemTypes')" type="button"
                                    class="px-2 py-0.5 text-xs bg-green-500 text-white rounded">保存</button>
                                <button @click="masterCancelEdit" type="button"
                                    class="px-2 py-0.5 text-xs border rounded text-gray-500">取消</button>
                            </template>
                            <template v-else>
                                <span class="flex-1 text-sm"
                                    :class="item.isActive ? 'text-gray-800' : 'text-gray-400 line-through'">{{ item.name }}</span>
                                <button @click="masterStartEdit(item)" type="button"
                                    class="px-2 py-0.5 text-xs border border-gray-300 rounded text-gray-600 hover:bg-gray-50">編集</button>
                                <button @click="masterDelete('itemTypes', item.id)" type="button"
                                    class="px-2 py-0.5 text-xs border border-red-200 text-red-400 rounded hover:bg-red-50">削除</button>
                            </template>
                        </div>
                        <p v-if="!itemTypeMaster.length" class="text-xs text-gray-400 text-center py-4">
                            アイテムが登録されていません。「+ 追加」から登録してください。
                        </p>
                    </div>

                    <!-- 一式マスタ -->
                    <div v-if="masterTab==='isshikiDestinations'" class="flex-1 overflow-y-auto p-5 space-y-2">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-sm font-semibold text-gray-700">一式宛先マスタ（{{ isshikiMaster.length }}件）</h3>
                            <div class="flex gap-2">
                                <template v-if="reorderMode==='isshikiDestinations'">
                                    <button @click="masterSaveReorder('isshikiDestinations')" type="button"
                                        class="px-3 py-1 text-xs bg-green-600 text-white rounded hover:bg-green-700">保存</button>
                                    <button @click="masterCancelReorder('isshikiDestinations')" type="button"
                                        class="px-3 py-1 text-xs border rounded text-gray-500 hover:bg-gray-100">キャンセル</button>
                                </template>
                                <template v-else>
                                    <button @click="masterStartReorder('isshikiDestinations')" type="button"
                                        class="px-3 py-1 text-xs border border-gray-300 rounded text-gray-600 hover:bg-gray-50">並べ替え</button>
                                    <button @click="masterStartAdd" type="button"
                                        class="px-3 py-1 text-xs bg-orange-500 text-white rounded hover:bg-orange-600">+ 追加</button>
                                </template>
                            </div>
                        </div>
                        <p class="text-xs text-gray-400 mb-2">一式PDFの宛先ラベルはこの順番で出力されます。</p>
                        <div v-if="masterAddingRow?._tab==='isshikiDestinations'"
                            class="flex items-center gap-2 p-2 border border-orange-300 rounded bg-orange-50">
                            <input v-model="masterAddingRow.name" placeholder="宛先名を入力"
                                class="flex-1 text-sm border rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-orange-400"
                                @keyup.enter="masterSaveAdd" @keyup.escape="masterCancelAdd" autofocus />
                            <button @click="masterSaveAdd" type="button"
                                class="px-2 py-0.5 text-xs bg-orange-500 text-white rounded">追加</button>
                            <button @click="masterCancelAdd" type="button"
                                class="px-2 py-0.5 text-xs border rounded text-gray-500">取消</button>
                        </div>
                        <div v-for="(item, idx) in isshikiMaster" :key="item.id"
                            class="flex items-center gap-2 px-3 py-2 border rounded hover:bg-gray-50">
                            <!-- 並べ替えボタン（並べ替えモードのみ） -->
                            <div v-if="reorderMode==='isshikiDestinations'" class="flex flex-col gap-0.5 shrink-0">
                                <button @click="masterMoveItem('isshikiDestinations', idx, -1)" type="button"
                                    :disabled="idx===0"
                                    class="w-5 h-4 text-xs leading-none border rounded text-gray-400 hover:bg-gray-100 disabled:opacity-20 disabled:cursor-not-allowed">▲</button>
                                <button @click="masterMoveItem('isshikiDestinations', idx, 1)" type="button"
                                    :disabled="idx===isshikiMaster.length-1"
                                    class="w-5 h-4 text-xs leading-none border rounded text-gray-400 hover:bg-gray-100 disabled:opacity-20 disabled:cursor-not-allowed">▼</button>
                            </div>
                            <input type="checkbox" :checked="item.isActive"
                                @change="masterToggleActive(item, 'isshikiDestinations')" class="rounded" />
                            <template v-if="masterEditingId===item.id">
                                <input v-model="masterEditingRow.name" class="flex-1 text-sm border rounded px-2 py-0.5 focus:outline-none focus:ring-1 focus:ring-orange-400"
                                    @keyup.enter="masterSaveEdit('isshikiDestinations')" @keyup.escape="masterCancelEdit" />
                                <button @click="masterSaveEdit('isshikiDestinations')" type="button"
                                    class="px-2 py-0.5 text-xs bg-green-500 text-white rounded">保存</button>
                                <button @click="masterCancelEdit" type="button"
                                    class="px-2 py-0.5 text-xs border rounded text-gray-500">取消</button>
                            </template>
                            <template v-else>
                                <span class="flex-1 text-sm"
                                    :class="item.isActive ? 'text-gray-800' : 'text-gray-400 line-through'">{{ item.name }}</span>
                                <button @click="masterStartEdit(item)" type="button"
                                    class="px-2 py-0.5 text-xs border border-gray-300 rounded text-gray-600 hover:bg-gray-50">編集</button>
                                <button @click="masterDelete('isshikiDestinations', item.id)" type="button"
                                    class="px-2 py-0.5 text-xs border border-red-200 text-red-400 rounded hover:bg-red-50">削除</button>
                            </template>
                        </div>
                        <p v-if="!isshikiMaster.length" class="text-xs text-gray-400 text-center py-4">
                            宛先が登録されていません。「+ 追加」から登録してください。
                        </p>
                    </div>

                    <!-- エリアマスタ -->
                    <div v-if="masterTab==='areaMasters'" class="flex-1 overflow-y-auto p-5 space-y-2">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-sm font-semibold text-gray-700">エリアマスタ（{{ areaMaster.length }}件）</h3>
                            <div class="flex gap-2">
                                <template v-if="reorderMode==='areaMasters'">
                                    <button @click="masterSaveReorder('areaMasters')" type="button"
                                        class="px-3 py-1 text-xs bg-green-600 text-white rounded hover:bg-green-700">保存</button>
                                    <button @click="masterCancelReorder('areaMasters')" type="button"
                                        class="px-3 py-1 text-xs border rounded text-gray-500 hover:bg-gray-100">キャンセル</button>
                                </template>
                                <template v-else>
                                    <button @click="masterStartReorder('areaMasters')" type="button"
                                        class="px-3 py-1 text-xs border border-gray-300 rounded text-gray-600 hover:bg-gray-50">並べ替え</button>
                                    <button @click="masterStartAdd" type="button"
                                        class="px-3 py-1 text-xs bg-orange-500 text-white rounded hover:bg-orange-600">+ 追加</button>
                                </template>
                            </div>
                        </div>
                        <p class="text-xs text-gray-400 mb-2">部数集計のエリア別集計はこの順番で表示されます。</p>
                        <div v-if="masterAddingRow?._tab==='areaMasters'"
                            class="flex items-center gap-2 p-2 border border-orange-300 rounded bg-orange-50">
                            <input v-model="masterAddingRow.name" placeholder="エリア名を入力"
                                class="flex-1 text-sm border rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-orange-400"
                                @keyup.enter="masterSaveAdd" @keyup.escape="masterCancelAdd" autofocus />
                            <button @click="masterSaveAdd" type="button"
                                class="px-2 py-0.5 text-xs bg-orange-500 text-white rounded">追加</button>
                            <button @click="masterCancelAdd" type="button"
                                class="px-2 py-0.5 text-xs border rounded text-gray-500">取消</button>
                        </div>
                        <div v-for="(item, idx) in areaMaster" :key="item.id"
                            class="flex items-center gap-2 px-3 py-2 border rounded hover:bg-gray-50">
                            <!-- 並べ替えボタン（並べ替えモードのみ） -->
                            <div v-if="reorderMode==='areaMasters'" class="flex flex-col gap-0.5 shrink-0">
                                <button @click="masterMoveItem('areaMasters', idx, -1)" type="button"
                                    :disabled="idx===0"
                                    class="w-5 h-4 text-xs leading-none border rounded text-gray-400 hover:bg-gray-100 disabled:opacity-20 disabled:cursor-not-allowed">▲</button>
                                <button @click="masterMoveItem('areaMasters', idx, 1)" type="button"
                                    :disabled="idx===areaMaster.length-1"
                                    class="w-5 h-4 text-xs leading-none border rounded text-gray-400 hover:bg-gray-100 disabled:opacity-20 disabled:cursor-not-allowed">▼</button>
                            </div>
                            <input type="checkbox" :checked="item.isActive"
                                @change="masterToggleActive(item, 'areaMasters')" class="rounded" />
                            <template v-if="masterEditingId===item.id">
                                <input v-model="masterEditingRow.name" class="flex-1 text-sm border rounded px-2 py-0.5 focus:outline-none focus:ring-1 focus:ring-orange-400"
                                    @keyup.enter="masterSaveEdit('areaMasters')" @keyup.escape="masterCancelEdit" />
                                <button @click="masterSaveEdit('areaMasters')" type="button"
                                    class="px-2 py-0.5 text-xs bg-green-500 text-white rounded">保存</button>
                                <button @click="masterCancelEdit" type="button"
                                    class="px-2 py-0.5 text-xs border rounded text-gray-500">取消</button>
                            </template>
                            <template v-else>
                                <span class="flex-1 text-sm"
                                    :class="item.isActive ? 'text-gray-800' : 'text-gray-400 line-through'">{{ item.name }}</span>
                                <button @click="masterStartEdit(item)" type="button"
                                    class="px-2 py-0.5 text-xs border border-gray-300 rounded text-gray-600 hover:bg-gray-50">編集</button>
                                <button @click="masterDelete('areaMasters', item.id)" type="button"
                                    class="px-2 py-0.5 text-xs border border-red-200 text-red-400 rounded hover:bg-red-50">削除</button>
                            </template>
                        </div>
                        <p v-if="!areaMaster.length" class="text-xs text-gray-400 text-center py-4">
                            エリアが登録されていません。「+ 追加」から登録してください。
                        </p>
                    </div>

                    <!-- 教室マスタ -->
                    <div v-if="masterTab==='schools'" class="flex-1 flex flex-col overflow-hidden p-5">
                        <div class="flex gap-2 mb-3 items-center flex-shrink-0">
                            <input v-model="masterSchoolSearch" placeholder="コード・名前で検索"
                                class="text-sm border rounded px-2 py-1.5 w-44" />
                            <span class="text-xs text-gray-400">{{ filteredSchoolMaster.length }}件</span>
                            <button @click="masterStartAdd" type="button"
                                class="ml-auto px-3 py-1.5 text-xs bg-orange-500 text-white rounded hover:bg-orange-600 transition">
                                + 追加
                            </button>
                        </div>
                        <div class="overflow-y-auto flex-1 space-y-0.5 text-xs">
                            <!-- 追加フォーム -->
                            <div v-if="masterAddingRow?._tab==='schools'"
                                class="flex gap-1.5 items-center bg-orange-50 border border-orange-200 rounded p-2 mb-1">
                                <input v-model="masterAddingRow.code" placeholder="コード" maxlength="3"
                                    class="w-14 shrink-0 border rounded px-1 py-0.5 font-mono uppercase bg-white" />
                                <input v-model="masterAddingRow.name" placeholder="教室名"
                                    class="flex-1 min-w-0 border rounded px-1 py-0.5 bg-white" />
                                <input v-model="masterAddingRow.route" placeholder="A1"
                                    class="w-16 shrink-0 border rounded px-1 py-0.5 font-mono bg-white" />
                                <input v-model="masterAddingRow.stopOrder" placeholder="順" type="number"
                                    class="w-16 shrink-0 border rounded px-1 py-0.5 text-right bg-white" />
                                <div class="w-24 shrink-0 flex gap-1">
                                    <button @click="masterSaveAdd" type="button"
                                        class="px-2 py-0.5 bg-green-500 text-white rounded">保存</button>
                                    <button @click="masterCancelAdd" type="button"
                                        class="px-2 py-0.5 border rounded text-gray-500">取消</button>
                                </div>
                            </div>
                            <!-- ヘッダ行 -->
                            <div class="flex gap-1.5 items-center py-1 border-b border-gray-300 font-semibold text-gray-500 sticky top-0 bg-white select-none">
                                <button @click="schoolToggleSort('code')" type="button"
                                    class="w-14 shrink-0 text-left hover:text-orange-500 transition-colors">
                                    コード<span class="ml-0.5 text-xs">{{ schoolSortKey==='code' ? (schoolSortDir==='asc'?'▲':'▼') : '' }}</span>
                                </button>
                                <button @click="schoolToggleSort('name')" type="button"
                                    class="flex-1 min-w-0 text-left hover:text-orange-500 transition-colors">
                                    教室名<span class="ml-0.5 text-xs">{{ schoolSortKey==='name' ? (schoolSortDir==='asc'?'▲':'▼') : '' }}</span>
                                </button>
                                <button @click="schoolToggleSort('route')" type="button"
                                    class="w-16 shrink-0 text-left hover:text-orange-500 transition-colors">
                                    ルート<span class="ml-0.5 text-xs">{{ schoolSortKey==='route' ? (schoolSortDir==='asc'?'▲':'▼') : '' }}</span>
                                </button>
                                <button @click="schoolToggleSort('stopOrder')" type="button"
                                    class="w-16 shrink-0 text-right hover:text-orange-500 transition-colors">
                                    順<span class="ml-0.5 text-xs">{{ schoolSortKey==='stopOrder' ? (schoolSortDir==='asc'?'▲':'▼') : '' }}</span>
                                </button>
                                <span class="w-24 shrink-0"></span>
                            </div>
                            <!-- リスト -->
                            <div v-for="s in sortedSchoolMaster" :key="s.id"
                                class="flex gap-1.5 items-center py-1 border-b border-gray-50 hover:bg-gray-50">
                                <template v-if="masterEditingId===s.id">
                                    <input v-model="masterEditingRow.code" maxlength="3"
                                        class="w-14 shrink-0 border rounded px-1 py-0.5 font-mono uppercase" />
                                    <input v-model="masterEditingRow.name"
                                        class="flex-1 min-w-0 border rounded px-1 py-0.5" />
                                    <input v-model="masterEditingRow.route"
                                        class="w-16 shrink-0 border rounded px-1 py-0.5 font-mono" />
                                    <input v-model="masterEditingRow.stopOrder" type="number"
                                        class="w-16 shrink-0 border rounded px-1 py-0.5 text-right" />
                                    <div class="w-24 shrink-0 flex gap-1">
                                        <button @click="masterSaveEdit('schools')" type="button"
                                            class="px-2 py-0.5 bg-green-500 text-white rounded">保存</button>
                                        <button @click="masterCancelEdit" type="button"
                                            class="px-2 py-0.5 border rounded text-gray-500">取消</button>
                                    </div>
                                </template>
                                <template v-else>
                                    <span class="w-14 shrink-0 font-mono font-semibold text-orange-600">{{ s.code }}</span>
                                    <span class="flex-1 min-w-0 truncate text-gray-700">{{ s.name || '' }}</span>
                                    <span class="w-16 shrink-0 font-mono text-blue-600">{{ s.route }}</span>
                                    <span class="w-16 shrink-0 text-right text-gray-400">{{ s.stopOrder }}</span>
                                    <div class="w-24 shrink-0 flex gap-1">
                                        <button @click="masterStartEdit(s)" type="button"
                                            class="px-1.5 py-0.5 border rounded hover:bg-gray-100 text-gray-600">編集</button>
                                        <button @click="masterDelete('schools', s.id)" type="button"
                                            class="px-1.5 py-0.5 border border-red-200 text-red-400 rounded hover:bg-red-50">削除</button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- 社内便マスタ -->
                    <div v-if="masterTab==='routes'" class="flex-1 flex flex-col overflow-hidden">
                        <!-- コース切替 + ルート編集フォーム -->
                        <div class="flex items-center gap-3 px-4 py-2 border-b bg-gray-50 flex-shrink-0">
                            <div class="flex gap-1">
                                <button @click="routeCourse=1; editingStop=null; editingRoute=null" type="button"
                                    class="px-3 py-1 text-xs rounded font-medium transition"
                                    :class="routeCourse===1 ? 'bg-blue-500 text-white' : 'border text-gray-600 hover:bg-gray-100'">
                                    コース1（月・木）
                                </button>
                                <button @click="routeCourse=2; editingStop=null; editingRoute=null" type="button"
                                    class="px-3 py-1 text-xs rounded font-medium transition"
                                    :class="routeCourse===2 ? 'bg-blue-500 text-white' : 'border text-gray-600 hover:bg-gray-100'">
                                    コース2（火・金）
                                </button>
                            </div>
                            <span class="text-xs text-gray-400">セルクリックで編集</span>
                        </div>

                        <!-- ルートヘッダー編集パネル -->
                        <div v-if="editingRoute" class="flex gap-2 flex-wrap items-center px-4 py-2 bg-blue-50 border-b flex-shrink-0 text-xs">
                            <span class="font-semibold text-blue-700">{{ editingRoute.code }} を編集:</span>
                            <label class="flex items-center gap-1">コード<input v-model="editingRoute.code" class="w-14 border rounded px-1 py-0.5 font-mono uppercase" maxlength="10" /></label>
                            <label class="flex items-center gap-1">エリア<input v-model="editingRoute.area" class="w-20 border rounded px-1 py-0.5" /></label>
                            <label class="flex items-center gap-1">曜日1<input v-model="editingRoute.day1" class="w-20 border rounded px-1 py-0.5" /></label>
                            <label class="flex items-center gap-1">出発1<input v-model="editingRoute.day1_start" class="w-24 border rounded px-1 py-0.5" /></label>
                            <label class="flex items-center gap-1">曜日2<input v-model="editingRoute.day2" class="w-20 border rounded px-1 py-0.5" /></label>
                            <label class="flex items-center gap-1">出発2<input v-model="editingRoute.day2_start" class="w-24 border rounded px-1 py-0.5" /></label>
                            <button @click="saveRouteEdit" type="button" class="px-3 py-0.5 bg-green-500 text-white rounded">保存</button>
                            <button @click="closeRouteEdit" type="button" class="px-3 py-0.5 border rounded text-gray-500">取消</button>
                        </div>

                        <!-- 停留所編集パネル -->
                        <div v-if="editingStop" class="flex gap-2 flex-wrap items-center px-4 py-2 bg-orange-50 border-b flex-shrink-0 text-xs">
                            <span class="font-semibold text-orange-700">{{ editingStop.routeCode }} 停留所{{ editingStop.stopOrder }}:</span>
                            <label class="flex items-center gap-1">教室名<input v-model="editingStop.school_name" class="w-36 border rounded px-1 py-0.5" placeholder="教室名" /></label>
                            <label class="flex items-center gap-1">コード<input v-model="editingStop.school_code" class="w-14 border rounded px-1 py-0.5 font-mono uppercase" maxlength="3" placeholder="DL" /></label>
                            <label class="flex items-center gap-1">時刻<input v-model="editingStop.arrival_time" class="w-16 border rounded px-1 py-0.5 font-mono" placeholder="11:30" /></label>
                            <label class="flex items-center gap-1">備考<input v-model="editingStop.notes" class="w-32 border rounded px-1 py-0.5" placeholder="鍵情報など" /></label>
                            <label class="flex items-center gap-1 shrink-0">色
                                <select v-model="editingStop.color_category" class="border rounded px-1 py-0.5 text-xs">
                                    <option :value="null">なし</option>
                                    <option v-for="l in LEGEND" :key="l.key" :value="l.key">{{ l.label }}</option>
                                </select>
                            </label>
                            <div class="flex gap-1 border-l pl-2 ml-1">
                                <button @click="insertStopAt(0)" type="button" class="px-2 py-0.5 bg-indigo-500 text-white rounded text-xs">↑挿入</button>
                                <button @click="insertStopAt(1)" type="button" class="px-2 py-0.5 bg-indigo-500 text-white rounded text-xs">↓挿入</button>
                                <button v-if="editingStop.id" @click="deleteStopShift" type="button" class="px-2 py-0.5 border border-red-300 text-red-500 rounded hover:bg-red-50">削除↑詰</button>
                            </div>
                            <button @click="saveStopEdit" type="button" class="px-3 py-0.5 bg-green-500 text-white rounded">保存</button>
                            <button @click="closeStopEdit" type="button" class="px-3 py-0.5 border rounded text-gray-500">取消</button>
                        </div>

                        <!-- 凡例 -->
                        <div class="flex gap-2 items-center px-4 py-1.5 border-b bg-white flex-shrink-0 flex-wrap">
                            <span class="text-xs text-gray-400">凡例:</span>
                            <span v-for="l in LEGEND" :key="l.key"
                                class="text-xs px-2 py-0.5 rounded border border-gray-200"
                                :class="l.cls">{{ l.label }}</span>
                            <span class="text-xs bg-white border border-gray-200 px-2 py-0.5 rounded text-gray-400">（なし）</span>
                        </div>

                        <!-- グリッド -->
                        <div class="flex-1 overflow-auto p-2">
                            <table class="border-collapse text-xs whitespace-nowrap">
                                <thead class="sticky top-0 z-20">
                                    <!-- ルートコード行 -->
                                    <tr>
                                        <th class="w-8 border border-gray-300 bg-gray-200 text-center text-gray-500 py-1 sticky left-0 z-30">停</th>
                                        <th v-for="route in activeRoutes" :key="route.id"
                                            class="border border-gray-300 bg-gray-200 px-2 py-1 text-center min-w-[105px] cursor-pointer hover:bg-blue-100"
                                            @click="openRouteEdit(route)">
                                            <div class="font-bold text-blue-700 text-sm">{{ route.code }}</div>
                                            <div class="text-gray-500 text-[10px]">{{ route.area }}</div>
                                        </th>
                                    </tr>
                                    <!-- 曜日行 -->
                                    <tr>
                                        <td class="border border-gray-200 bg-gray-100 sticky left-0 z-30"></td>
                                        <td v-for="route in activeRoutes" :key="route.id"
                                            class="border border-gray-200 bg-gray-100 px-1 py-0.5 text-center text-gray-600 text-[10px]">
                                            <div>{{ route.day1 }} {{ route.day1_start }}</div>
                                            <div v-if="route.day2">{{ route.day2 }} {{ route.day2_start }}</div>
                                        </td>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="stopNum in activeStopOrders" :key="stopNum">
                                        <td class="border border-gray-200 bg-gray-100 text-center font-semibold text-gray-500 sticky left-0 z-10 px-1 w-8">
                                            {{ stopNum }}
                                        </td>
                                        <td v-for="route in activeRoutes" :key="route.id"
                                            class="border border-gray-200 px-1.5 py-1 cursor-pointer transition-colors align-top"
                                            :class="[
                                                cellColorClass(getStop(route, stopNum)),
                                                editingStop?.routeId===route.id && editingStop?.stopOrder===stopNum
                                                    ? 'ring-2 ring-orange-400 ring-inset'
                                                    : 'hover:brightness-95'
                                            ]"
                                            @click="openStopEdit(route, stopNum)">
                                            <template v-for="stop in [getStop(route, stopNum)].filter(Boolean)" :key="stop.id">
                                                <div class="font-medium text-gray-800 leading-tight">{{ stop.school_name }}</div>
                                                <div class="flex items-center gap-1 mt-0.5">
                                                    <span v-if="stop.school_code" class="text-orange-600 font-mono font-bold text-[10px]">{{ stop.school_code }}</span>
                                                    <span v-if="stop.arrival_time" class="text-blue-600 text-[10px] ml-auto">{{ stop.arrival_time }}</span>
                                                </div>
                                                <div v-if="stop.notes" class="text-gray-400 text-[10px] truncate max-w-[100px]">{{ stop.notes }}</div>
                                            </template>
                                            <span v-if="!getStop(route, stopNum)" class="text-gray-200 select-none text-[10px]">—</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </Teleport>

        <!-- ━━ データ照会モーダル ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
        <Teleport to="body">
            <div v-if="showDataviewModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60"
                @click.self="showDataviewModal=false">
                <div class="bg-white rounded-xl shadow-2xl flex flex-col overflow-hidden"
                    style="width:92vw; max-width:900px; height:88vh;">
                    <!-- ヘッダー -->
                    <div class="flex flex-col px-5 pt-3 pb-0 border-b bg-gray-50 flex-shrink-0">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-semibold text-gray-800">データ照会</span>
                                <span class="text-xs text-gray-400">{{ dataviewRows.length }}件</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <button @click="showSummaryModal=true" type="button"
                                    class="px-4 py-1.5 text-xs bg-green-600 text-white rounded hover:bg-green-700 transition font-medium">
                                    部数集計
                                </button>
                                <button @click="showDataviewModal=false" type="button"
                                    class="text-gray-400 hover:text-gray-700 text-xl leading-none px-2">✕</button>
                            </div>
                        </div>
                        <!-- シート選択 -->
                        <div class="flex gap-1 flex-wrap pb-2">
                            <button v-for="(sheet, idx) in sheetDataRef" :key="idx"
                                @click="dataviewSheet=idx; dataviewGrade=sheet.grades[0]??''" type="button"
                                class="px-3 py-1 text-xs rounded-full font-medium border transition"
                                :class="dataviewSheet===idx ? 'bg-blue-600 text-white border-blue-600' : 'text-gray-600 border-gray-300 hover:bg-gray-100'">
                                {{ sheet.name }}
                            </button>
                        </div>
                        <!-- 学年ボタン -->
                        <div class="flex gap-1 flex-wrap pb-2">
                            <button v-for="g in (sheetDataRef[dataviewSheet]?.grades ?? [])" :key="g"
                                @click="dataviewGrade=g" type="button"
                                class="px-3 py-1 text-xs rounded-full font-medium border transition"
                                :class="dataviewGrade===g ? 'bg-orange-500 text-white border-orange-500' : 'text-gray-600 border-gray-300 hover:bg-gray-100'">
                                {{ g }}
                            </button>
                        </div>
                    </div>
                    <!-- テーブル -->
                    <div class="flex-1 overflow-y-auto">
                        <table class="w-full text-xs border-collapse">
                            <thead class="sticky top-0 bg-gray-100 z-10">
                                <tr>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-600 border-b w-16">コード</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-600 border-b">教室名印刷</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-600 border-b w-28">エリア</th>
                                    <th class="px-3 py-2 text-right font-semibold text-gray-600 border-b w-16">部数</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in dataviewRows" :key="row.code"
                                    class="border-b border-gray-100 hover:bg-gray-50">
                                    <td class="px-3 py-1.5 font-mono text-gray-700">{{ row.code }}</td>
                                    <td class="px-3 py-1.5 text-gray-800">{{ row.name }}</td>
                                    <td class="px-3 py-1.5 text-gray-500">{{ row.area }}</td>
                                    <td class="px-3 py-1.5 text-right font-medium"
                                        :class="row.qty === 0 ? 'bg-red-100 text-red-600' : 'text-gray-800'">
                                        {{ row.qty === 0 ? '' : row.qty }}
                                    </td>
                                </tr>
                                <tr v-if="!dataviewRows.length">
                                    <td colspan="4" class="px-3 py-8 text-center text-gray-400">データがありません</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ━━ 部数集計モーダル ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
        <Teleport to="body">
            <div v-if="showSummaryModal"
                class="fixed inset-0 z-[60] flex items-center justify-center bg-black/60"
                @click.self="showSummaryModal=false">
                <div class="bg-white rounded-xl shadow-2xl flex flex-col overflow-hidden"
                    style="width:400px; max-width:95vw; max-height:80vh;">
                    <!-- ヘッダー -->
                    <div class="flex items-center justify-between px-5 py-3 border-b bg-gray-50 flex-shrink-0">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-semibold text-gray-800">部数集計</span>
                            <span class="text-xs text-gray-500">（{{ dataviewGrade }}）</span>
                        </div>
                        <button @click="showSummaryModal=false" type="button"
                            class="text-gray-400 hover:text-gray-700 text-xl leading-none px-2">✕</button>
                    </div>
                    <!-- テーブル -->
                    <div class="flex-1 overflow-y-auto">
                        <table class="w-full text-sm border-collapse">
                            <thead class="sticky top-0 bg-gray-100 z-10">
                                <tr>
                                    <th class="px-4 py-2 text-left font-semibold text-gray-600 border-b">エリア</th>
                                    <th class="px-4 py-2 text-right font-semibold text-gray-600 border-b w-20">部数</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in summaryData" :key="row.area"
                                    class="border-b border-gray-100 hover:bg-gray-50">
                                    <td class="px-4 py-2 text-gray-800">{{ row.area }}</td>
                                    <td class="px-4 py-2 text-right font-medium text-gray-800">{{ row.qty }}</td>
                                </tr>
                                <tr v-if="!summaryData.length">
                                    <td colspan="2" class="px-4 py-6 text-center text-gray-400">データがありません</td>
                                </tr>
                            </tbody>
                            <tfoot class="sticky bottom-0 bg-gray-50">
                                <tr class="border-t-2 border-gray-300">
                                    <td class="px-4 py-2 font-bold text-gray-800">総計</td>
                                    <td class="px-4 py-2 text-right font-bold text-gray-800">{{ summaryTotal }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ━━ プレビューモーダル ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
        <Teleport to="body">
            <div v-if="previewTarget"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60"
                @click.self="closePreview">
                <div class="bg-white rounded-xl shadow-2xl flex flex-col overflow-hidden"
                    style="width:90vw; max-width:960px; height:88vh;">

                    <!-- モーダルヘッダー -->
                    <div class="flex items-center justify-between px-5 py-3 border-b bg-gray-50 flex-shrink-0">
                        <span class="text-sm font-medium text-gray-800 font-mono">{{ previewTarget.name }}</span>
                        <div class="flex gap-2">
                            <button @click="downloadPdf(previewTarget)"
                                class="px-4 py-1.5 text-xs rounded bg-green-500 text-white hover:bg-green-600 transition font-medium">
                                DL
                            </button>
                            <button @click="closePreview"
                                class="px-3 py-1.5 text-xs rounded border border-gray-300 text-gray-600 hover:bg-gray-100 transition">
                                閉じる
                            </button>
                        </div>
                    </div>

                    <!-- PDF iframe -->
                    <iframe :src="previewTarget.url"
                        class="flex-1 w-full border-0"
                        title="PDF プレビュー" />
                </div>
            </div>
        </Teleport>

    </div>
</template>
