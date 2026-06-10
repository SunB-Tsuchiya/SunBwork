<script setup>
import { ref, computed, onMounted } from 'vue';
import * as XLSX from 'xlsx';
import { jsPDF } from 'jspdf';
import axios from 'axios';

defineProps({ script: { type: Object, required: true } });

// ============================================================
// 定数
// ============================================================

const ROUTE_ORDER = ['A1','B1','C1','D1','E1','F1','G1','H1','I1','A2','B2','C2','D2','E2','F2','G2','H2','I2'];

// DBロード失敗時のフォールバック用（通常はDBから取得）
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

const NO_SUFFIX_KEYWORDS = ['コバ', '向学館', '関東物流', 'NTS', '別館', '関東本部', '職員', '本部', '受付', 'ロジ', '研究', '調査', '情報', '人材', '業務', '法人', '学力'];

// 特殊行のキーワード → 合成コードのマッピング（col 2 で検出）
const SPECIAL_ENTRY_KEYWORDS = {
    '東海本部': '$tokai',
    'ユリウス': '$julius',
    'アトラス': '$julius',
    '予備':    '$yobi',
};

// 特殊行の並び順（関東I2=17001の後・T*=50118〜50157の次・四国P*=50169の前）
const SPECIAL_SORT = {
    $tokai:  50158,  // T*直後
    $yobi:   50160,  // 予備
    $julius: 50162,  // ユリウス・アトラス分
};

const CIRCLED_NUMS = '①②③④⑤⑥⑦⑧⑨⑩⑪⑫⑬⑭⑮';

const PRESETS = {
    A: {
        label: 'A: 学習力育成テスト（4〜8科目型）',
        items: [
            { num: '①', subject: '国算', itemLabel: '解説',        maxBox: 100, sheetKey: 'kokusan' },
            { num: '②', subject: '社理', itemLabel: '解説',        maxBox: 100, sheetKey: 'shashiri' },
            { num: '③', subject: '国算', itemLabel: '問題用紙',    maxBox:  50, sheetKey: 'kokusan' },
            { num: '④', subject: '社理', itemLabel: '問題用紙',    maxBox:  50, sheetKey: 'shashiri' },
            { num: '⑤', subject: '国語', itemLabel: 'DI答案用紙',  maxBox: 250, sheetKey: 'kokusan_di' },
            { num: '⑥', subject: '算数', itemLabel: 'DI答案用紙',  maxBox: 250, sheetKey: 'kokusan_di' },
            { num: '⑦', subject: '社会', itemLabel: 'DI答案用紙',  maxBox: 250, sheetKey: 'shashiri_di' },
            { num: '⑧', subject: '理科', itemLabel: 'DI答案用紙',  maxBox: 250, sheetKey: 'shashiri_di' },
        ],
    },
    B: {
        label: 'B: 日能研全国テスト（2〜4科目型）',
        items: [
            { num: '①', subject: '国算', itemLabel: '解説',        maxBox: 100, sheetKey: 'kokusan_di' },
            { num: '②', subject: '国算', itemLabel: '問題用紙',    maxBox:  50, sheetKey: 'kokusan_di' },
            { num: '③', subject: '国語', itemLabel: 'DI答案用紙',  maxBox: 250, sheetKey: 'kokusan_di' },
            { num: '④', subject: '算数', itemLabel: 'DI答案用紙',  maxBox: 250, sheetKey: 'kokusan_di' },
        ],
    },
    C: {
        label: 'C: 全国公開模試（4・5年）',
        items: [
            { num: '①', subject: '国算', itemLabel: '解説',               maxBox: 100, sheetKey: 'kokusan_kaitou' },
            { num: '②', subject: '社理', itemLabel: '解説',               maxBox: 100, sheetKey: 'shashiri_kaitou' },
            { num: '③', subject: '4科',  itemLabel: '問題用紙＋DI答案',   maxBox:  50, sheetKey: 'yonka' },
            { num: '④', subject: '2科',  itemLabel: '問題用紙＋DI答案',   maxBox:  50, sheetKey: 'nika' },
        ],
    },
    D: {
        label: 'D: 夏期特別テスト（1日実施型）',
        items: [
            { num: '①', subject: '国算', itemLabel: '解答',        maxBox: 100, sheetKey: 'summer_main_di' },
            { num: '②', subject: '国算', itemLabel: '問題用紙',    maxBox:  50, sheetKey: 'summer_main_di' },
            { num: '③', subject: '国語', itemLabel: 'DI答案用紙',  maxBox: 250, sheetKey: 'kokusan_di' },
            { num: '④', subject: '算数', itemLabel: 'DI答案用紙',  maxBox: 250, sheetKey: 'kokusan_di' },
            { num: '⑤', subject: '社会', itemLabel: 'DI答案用紙',  maxBox: 250, sheetKey: 'shashiri_di' },
            { num: '⑥', subject: '理科', itemLabel: 'DI答案用紙',  maxBox: 250, sheetKey: 'shashiri_di' },
        ],
    },
    E: {
        label: 'E: 公立中高一貫校対策',
        items: [
            { num: '①', subject: '', itemLabel: '問題・答案セットと解説（教室分）', maxBox:  30, sheetKey: 'main' },
            { num: '②', subject: '', itemLabel: '問題・答案セット',                maxBox:  50, sheetKey: 'main' },
            { num: '③', subject: '', itemLabel: '解答・解説',                      maxBox: 100, sheetKey: 'main' },
        ],
    },
    G: {
        label: 'G: 夏期3年思考テスト（前中期・後期）',
        items: [
            { num: '①', subject: '', itemLabel: '解説',                maxBox: 100, sheetKey: 'main' },
            { num: '②', subject: '', itemLabel: '問題用紙',            maxBox:  50, sheetKey: 'main' },
            { num: '③', subject: '', itemLabel: '前・中期 DI答案用紙', maxBox: 250, sheetKey: 'kokusan_di' },
            { num: '④', subject: '', itemLabel: '後期 DI答案用紙',     maxBox: 250, sheetKey: 'shashiri_di' },
        ],
    },
    H: {
        label: 'H: 夏期4〜6年テスト（前中期・後期）',
        items: [
            { num: '①',  subject: '国算', itemLabel: '前・中期 解説',     maxBox: 100, sheetKey: 'summer_early' },
            { num: '②',  subject: '社理', itemLabel: '前・中期 解説',     maxBox: 100, sheetKey: 'summer_early' },
            { num: '③',  subject: '国算', itemLabel: '前・中期 問題用紙', maxBox:  50, sheetKey: 'summer_early' },
            { num: '④',  subject: '社理', itemLabel: '前・中期 問題用紙', maxBox:  50, sheetKey: 'summer_early' },
            { num: '⑤',  subject: '国算', itemLabel: '前・中期 DI答案',   maxBox: 250, sheetKey: 'kokusan_di' },
            { num: '⑥',  subject: '社理', itemLabel: '前・中期 DI答案',   maxBox: 250, sheetKey: 'shashiri_di' },
            { num: '⑦',  subject: '国算', itemLabel: '後期 解説',         maxBox: 100, sheetKey: 'summer_late' },
            { num: '⑧',  subject: '社理', itemLabel: '後期 解説',         maxBox: 100, sheetKey: 'summer_late' },
            { num: '⑨',  subject: '国算', itemLabel: '後期 問題用紙',     maxBox:  50, sheetKey: 'summer_late' },
            { num: '⑩',  subject: '社理', itemLabel: '後期 問題用紙',     maxBox:  50, sheetKey: 'summer_late' },
            { num: '⑪',  subject: '国算', itemLabel: '後期 DI答案',       maxBox: 250, sheetKey: 'kokusan_di' },
            { num: '⑫',  subject: '社理', itemLabel: '後期 DI答案',       maxBox: 250, sheetKey: 'shashiri_di' },
        ],
    },
};

const AREAS = [
    { key: 'kanto',       label: '関東（教室）' },
    { key: 'kansai',      label: '関西' },
    { key: 'kyushu',      label: '九州' },
    { key: 'tokai_honbu', label: '東海本部' },
    { key: 'honbu',       label: '本部' },
    { key: 'busho',       label: '本部部署分（一式）' },
    { key: 'kanto_staff', label: '関東スタッフ' },
    { key: 'honbu_staff', label: '本部職員' },
    { key: 'tokai_staff', label: '東海本部職員' },
];

// sheetKey ごとのフォールバック順（DI系が見つからない場合に非DI版を試す）
const SHEET_FALLBACKS = {
    kokusan_di:      ['kokusan',  'main'],
    shashiri_di:     ['shashiri', 'main'],
    kokusan_kaitou:  ['kokusan',  'main'],
    shashiri_kaitou: ['shashiri', 'main'],
    summer_main_di:  ['main'],
    summer_early:    ['main'],
    summer_late:     ['main'],
    yonka:           ['main'],
    nika:            ['main'],
};

// ============================================================
// アイテム凡例ヘルパー
// ============================================================

function inferMaxBox(label) {
    const n = label.normalize('NFKC');
    if (/DI答案|答案用紙/.test(n)) return 250;
    if (/解答|解説/.test(n)) return 100;
    if (/問題/.test(n)) return 50;
    return 100;
}

function parseItemLabel(fullText) {
    const text = fullText.normalize('NFKC').replace(/^[①-⑮]\s*/, '').replace(/　/g, ' ').trim();
    const parts = text.split(/\s+/).filter(Boolean);
    if (parts.length === 0) return { subject: '', itemLabel: text };
    const CONTENT_RE = /解答|解説|問題|答案|DI/;
    let splitIdx = parts.length;
    for (let i = 0; i < parts.length; i++) {
        if (CONTENT_RE.test(parts[i])) { splitIdx = i; break; }
    }
    return {
        subject:   parts.slice(0, splitIdx).join(''),
        itemLabel: parts.slice(splitIdx).join(' '),
    };
}

function extractLegendItems(rows) {
    const CIRCLED_RE = /^[①-⑮]/;
    const found = [];
    for (let r = 0; r < Math.min(rows.length, 20); r++) {
        const row = rows[r] || [];
        for (let c = 7; c < Math.min(row.length, 18); c++) {
            const v = String(row[c] || '').normalize('NFKC').trim();
            if (CIRCLED_RE.test(v) && v.length > 1) {
                const num = v[0];
                const { subject, itemLabel } = parseItemLabel(v);
                if (itemLabel) {
                    found.push({ num, subject, itemLabel, maxBox: inferMaxBox(v) });
                }
            }
        }
    }
    return found;
}

// ============================================================
// シート分類
// ============================================================

function detectSheetKey(sheetName) {
    const n = sheetName.normalize('NFKC').trim();

    if (/テストサービス|\bTS\b/.test(n)) return 'exclude';
    if (/一式/.test(n)) return 'ichishiki';

    if (/DI/.test(n)) {
        if (/国算|国語|算数/.test(n)) return 'kokusan_di';
        if (/社理|社会|理科|社答案|理答案/.test(n)) return 'shashiri_di';
        if (/前.*中期|前期.*中期|前中期/.test(n)) return 'kokusan_di';
        if (/後期/.test(n)) return 'shashiri_di';
        if (/答案/.test(n)) return 'shashiri_di';
        return 'summer_main_di';
    }

    if (/国算/.test(n)) return 'kokusan';
    if (/社理/.test(n)) return 'shashiri';
    if (/社会/.test(n)) return 'shashiri';
    if (/理科/.test(n)) return 'shashiri';

    if (/四科テスト|4科テスト|四科$|4科$/.test(n)) return 'yonka';
    if (/二科テスト|2科テスト|二科$|2科$/.test(n)) return 'nika';
    if (/国算解答|国算解説/.test(n)) return 'kokusan_kaitou';
    if (/社理解答|社理解説/.test(n)) return 'shashiri_kaitou';

    if (/公立中高|適性検査/.test(n)) return 'kouritsu';

    if (/前.*中期|前中期/.test(n)) return 'summer_early';
    if (/後期/.test(n)) return 'summer_late';

    return 'main';
}

// シート名から MMDD を抽出（「日」抜けtypoも対応）
function extractDateCode(sheetName) {
    const n = sheetName.normalize('NFKC');
    const m = n.match(/(\d+)月(\d+)日?(?=[^月]|$)/);
    if (!m) return null;
    return m[1].padStart(2, '0') + m[2].padStart(2, '0');
}

// MMDD → 表示用文字列（"0330" → "2026年3月30日"）
function dateCodeToDisplay(code) {
    if (!code || code === '__common') return '';
    const m = code.match(/^(\d{2})(\d{2})$/);
    if (!m) return code;
    return `${new Date().getFullYear()}年${parseInt(m[1])}月${parseInt(m[2])}日`;
}

// 日本語日付文字列 → MMDD（ファイル名プレフィックス用）
// "2026年3月21日" → "0321" / "2026年3月21・22日" → "0321"（最初の日）
function dateValToMMDD(dateStr) {
    if (!dateStr) return '';
    const m = dateStr.match(/(\d+)月(\d+)/);
    if (!m) return '';
    return m[1].padStart(2, '0') + m[2].padStart(2, '0');
}

// ============================================================
// State
// ============================================================

const step          = ref(1);
const showRules     = ref(false);
const errorMsg      = ref('');
const progressPct   = ref(0);
const progressMsg   = ref('');

const excelName     = ref('');
const routeName     = ref('');
const saveDirHandle   = ref(null);
const saveDirName     = ref('');
const excelFileHandle = ref(null);
const hasFSA = typeof window !== 'undefined' && 'showOpenFilePicker' in window;
const sheetsData    = ref({});
const detectedDates = ref([]);
const routeMap      = ref({});   // DBから初期化（onMounted）
const excludedCodes = ref(new Set());
const sheetLog      = ref([]);

const testNameVal    = ref('');
const testDateVal    = ref('');  // 実施日（ラベル印字・ファイル名用）例: "2026年3月21日"
const shortNameVal   = ref('');
const gradeOptions   = ref([]);
const selectedGrade  = ref('');
const selectedPreset        = ref('A');
const detectedItems         = ref([]);
const confirmedItems        = ref([]);   // OCR確認済みアイテム（最優先）
const confirmedTests        = ref([]);   // OCR確認済みテスト一覧
const ichishikiFlag         = ref(false);
const itemPdfName           = ref('');
const ocrStep               = ref('idle');   // 'idle' | 'uploading' | 'done'
const showOcrModal          = ref(false);
const ocrRawText            = ref('');
const modalTests            = ref([]);
const modalItems            = ref([]);
const modalIchishiki        = ref(false);
const ocrError              = ref('');
const pdfInputRef           = ref(null);
const gradeLabelOverrides   = ref({});
const gradeTestNameOverrides = ref({});
const gradeDateOverrides     = ref({});
const showItemEditor        = ref(false);
const selectedAreas       = ref(AREAS.map(a => a.key));
const outputFiles    = ref([]);

// ============================================================
// マスタデータ（DB）
// ============================================================
const masterSchools   = ref([]);
const masterTestNames = ref([]);
const masterSubjects  = ref([]);
const masterItemTypes = ref([]);
const masterLoading   = ref(false);

// メインタブ: 'tool' | 'master'
const activeTab      = ref('tool');
// マスタ管理サブタブ: 'schools' | 'testNames' | 'subjects' | 'itemTypes'
const masterTab      = ref('schools');
// インライン編集状態
const editingId      = ref(null);   // 編集中レコードのid
const editingRow     = ref({});     // 編集中フォームデータ
const addingRow      = ref(null);   // 新規追加フォームデータ（nullなら非表示）

// 教室コード → DB レコードの高速参照マップ
const schoolMap = computed(() => {
    const map = {};
    for (const s of masterSchools.value) { if (s.is_active) map[s.code] = s; }
    return map;
});

// ============================================================
// ユーティリティ
// ============================================================

// ラベル並び順キー: SS→関東ルート順(A1→I2)→東海本部/予備/ユリウス→四国→九州→PA最後
function labelSortKey(label) {
    const icode = label._internalCode ?? label.schoolCode;
    if (icode === 'SS') return -10000;
    if (icode === 'PA') return 999999;
    if (SPECIAL_SORT[icode] !== undefined) return SPECIAL_SORT[icode];
    if (label._routeOrder >= 0) {
        return label._routeOrder * 1000 + label._stopOrder;
    }
    // 非関東: 行順（同行は左列→右列）。I2キー最大≈17999より大きい50000+を基底
    return 50000 + label._rowIdx * 2 + label._colIdx;
}

function normCode(s) {
    return String(s || '').trim().normalize('NFKC').toUpperCase().replace(/\s/g, '');
}

function needsSchoolSuffix(name) {
    return !NO_SUFFIX_KEYWORDS.some(kw => name.includes(kw));
}

function buildRouteMapFromDB() {
    const map = {};
    for (const s of masterSchools.value) {
        if (s.route && s.is_active) map[s.code] = { route: s.route, stop: s.stop_order || 0 };
    }
    return Object.keys(map).length > 5 ? map : { ...FALLBACK_ROUTE_MAP };
}

// ============================================================
// マスタ DB ロード / CRUD
// ============================================================

onMounted(async () => {
    masterLoading.value = true;
    try {
        const [sch, tn, su, it] = await Promise.all([
            axios.get('/label-masters/schools'),
            axios.get('/label-masters/test-names'),
            axios.get('/label-masters/subjects'),
            axios.get('/label-masters/item-types'),
        ]);
        masterSchools.value   = sch.data;
        masterTestNames.value = tn.data;
        masterSubjects.value  = su.data;
        masterItemTypes.value = it.data;
        routeMap.value = buildRouteMapFromDB();
    } catch (e) {
        console.error('マスタ読み込みエラー:', e);
        routeMap.value = { ...FALLBACK_ROUTE_MAP };
    } finally {
        masterLoading.value = false;
    }
});

function masterArray(tab) {
    return { schools: masterSchools, testNames: masterTestNames, subjects: masterSubjects, itemTypes: masterItemTypes }[tab];
}

function masterApiPath(tab) {
    return { schools: 'schools', testNames: 'test-names', subjects: 'subjects', itemTypes: 'item-types' }[tab];
}

function startEdit(tab, record) {
    editingId.value  = record.id;
    editingRow.value = { ...record };
    masterTab.value  = tab;
}

function cancelEdit() {
    editingId.value  = null;
    editingRow.value = {};
}

async function saveEdit(tab) {
    try {
        const { data } = await axios.put(`/label-masters/${masterApiPath(tab)}/${editingId.value}`, editingRow.value);
        const arr = masterArray(tab);
        const idx = arr.value.findIndex(r => r.id === data.id);
        if (idx >= 0) arr.value[idx] = data;
        if (tab === 'schools') routeMap.value = buildRouteMapFromDB();
        cancelEdit();
    } catch (e) {
        errorMsg.value = `保存エラー: ${e.response?.data?.message || e.message}`;
    }
}

function startAdd(tab) {
    const defaults = tab === 'schools'
        ? { code: '', display_name: '', area: '関東', route: '', stop_order: '', is_active: true, notes: '' }
        : { name: '', sort_order: 0, is_active: true };
    addingRow.value = { ...defaults, _tab: tab };
}

function cancelAdd() { addingRow.value = null; }

async function saveAdd() {
    const tab = addingRow.value._tab;
    const payload = { ...addingRow.value };
    delete payload._tab;
    try {
        const { data } = await axios.post(`/label-masters/${masterApiPath(tab)}`, payload);
        masterArray(tab).value.push(data);
        if (tab === 'schools') routeMap.value = buildRouteMapFromDB();
        cancelAdd();
    } catch (e) {
        errorMsg.value = `追加エラー: ${e.response?.data?.message || e.message}`;
    }
}

async function deleteMasterRecord(tab, id) {
    if (!confirm('削除しますか？')) return;
    try {
        await axios.delete(`/label-masters/${masterApiPath(tab)}/${id}`);
        const arr = masterArray(tab);
        arr.value = arr.value.filter(r => r.id !== id);
        if (tab === 'schools') routeMap.value = buildRouteMapFromDB();
    } catch (e) {
        errorMsg.value = `削除エラー: ${e.message}`;
    }
}

function splitBoxes(qty, maxBox) {
    const boxes = [];
    let rem = qty;
    while (rem > 0) { boxes.push(Math.min(rem, maxBox)); rem -= maxBox; }
    return boxes;
}

function getAreaKey(code) {
    if (routeMap.value[code]) return 'kanto';
    if (/^T[A-Z]/.test(code)) return 'tokai_honbu';
    if (/^[K-M][A-Z]/.test(code)) return 'kansai';
    if (/^R[A-Z]/.test(code)) return 'kyushu';
    return 'other';
}

const AREA_ORDER = { other: 0, kanto: 1, kanto_staff: 2, tokai_honbu: 3, kansai: 4, kyushu: 5, busho: 6, honbu: 7, honbu_staff: 8, tokai_staff: 9 };

// ============================================================
// Excel パース
// ============================================================

function detectDataStartRow(rows) {
    for (let r = 3; r < Math.min(rows.length, 20); r++) {
        const row = rows[r] || [];
        for (const col of [0, 1, 6]) {
            const code = normCode(String(row[col] || ''));
            if (!/^[A-Z]{2}$/.test(code)) continue;
            const name = String(row[col + 1] || '').trim();
            if (name && !/^\d+$/.test(name)) return r;
        }
    }
    return 7;
}

function computePresetKey(sheetKeys) {
    const keys = new Set(sheetKeys);
    if (keys.has('kokusan') && keys.has('shashiri')) return 'A';
    if (keys.has('kokusan_kaitou') || keys.has('yonka') || keys.has('nika')) return 'C';
    if (keys.has('kokusan_di') && !keys.has('kokusan') && !keys.has('summer_main_di')) return 'B';
    if (keys.has('summer_main_di') && keys.has('kokusan_di')) return 'D';
    if (keys.has('summer_early') || keys.has('summer_late')) return 'H';
    if (keys.has('kokusan_di') && keys.has('shashiri_di') && keys.has('main')) return 'G';
    if (keys.has('kouritsu')) return 'E';
    return null;
}

// showOpenFilePicker 対応ブラウザ向け（Excelファイルを選択）
async function pickExcelFile() {
    try {
        const [fh] = await window.showOpenFilePicker({
            types: [{ description: 'Excel', accept: { 'application/vnd.ms-excel': ['.xls', '.xlsx'] } }],
            multiple: false,
        });
        excelFileHandle.value = fh;
        saveDirHandle.value = null;  // 新しいExcelを開いたら保存先をリセット
        saveDirName.value = '';
        await processExcelFile(await fh.getFile());
    } catch (e) {
        if (e.name !== 'AbortError') errorMsg.value = `ファイル読み込みエラー: ${e.message}`;
    }
}

// <input type="file"> 経由（フォールバック）
async function handleExcelUpload(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    excelFileHandle.value = null;
    saveDirHandle.value = null;
    saveDirName.value = '';
    await processExcelFile(file);
}

async function processExcelFile(file) {
    excelName.value = file.name;
    errorMsg.value = '';
    sheetsData.value = {};
    detectedDates.value = [];
    sheetLog.value = [];
    gradeOptions.value = [];
    excludedCodes.value = new Set();
    testNameVal.value = '';
    testDateVal.value = '';
    shortNameVal.value = '';
    selectedGrade.value = '';
    gradeTestNameOverrides.value = {};
    gradeDateOverrides.value = {};
    try {
        const ab = await file.arrayBuffer();
        const wb = XLSX.read(ab, { type: 'array', codepage: 932 });
        parseWorkbook(wb);
    } catch (e) {
        errorMsg.value = `Excelファイル読み込みエラー: ${e.message}`;
    }
}

async function pickSaveDir() {
    if (!hasFSA) return;
    try {
        // excelFileHandle があればそのフォルダを最初から開く
        const h = await window.showDirectoryPicker({
            mode: 'readwrite',
            ...(excelFileHandle.value ? { startIn: excelFileHandle.value } : {}),
        });
        saveDirHandle.value = h;
        saveDirName.value = h.name;
    } catch (e) {
        if (e.name !== 'AbortError') errorMsg.value = `フォルダ選択エラー: ${e.message}`;
    }
}

function parseWorkbook(wb) {
    // sheetsData は日付バケット構造: { '__common': {key:schools}, '0330': {key:schools}, ... }
    const newSheets = { '__common': {} };
    const log = [];
    const allDates = new Set();
    let detectedGrades = [];
    let detectedName = '';
    const legendByNum = {};  // 丸数字 → 最初に見つかったアイテム情報
    let detectedDate = '';

    for (const sheetName of wb.SheetNames) {
        const ws = wb.Sheets[sheetName];
        const key = detectSheetKey(sheetName);
        const dateCode = extractDateCode(sheetName);
        const bucket = dateCode ?? '__common';

        if (!newSheets[bucket]) newSheets[bucket] = {};
        if (dateCode) allDates.add(dateCode);

        if (key === 'exclude') {
            const rows = XLSX.utils.sheet_to_json(ws, { header: 1, raw: false, defval: '' });
            const excl = new Set(excludedCodes.value);
            const startRow = detectDataStartRow(rows);
            const seen = new Set();
            let cnt = 0;
            for (let r = startRow; r < rows.length; r++) {
                const row = rows[r] || [];
                for (const col of [0, 6]) {
                    const c = normCode(row[col]);
                    if (/^[A-Z]{2}$/.test(c) && !seen.has(c)) {
                        excl.add(c); seen.add(c); cnt++;
                    }
                }
            }
            excludedCodes.value = excl;
            log.push({ originalName: sheetName, key: 'exclude', schoolCount: cnt, isFirst: true, diag: null, dateCode });
            continue;
        }

        if (key === 'ichishiki') {
            // 一式シートは存在マーカーのみ記録（Phase2で内容実装）
            if (!newSheets[bucket]['ichishiki']) newSheets[bucket]['ichishiki'] = true;
            log.push({ originalName: sheetName, key: 'ichishiki', schoolCount: 0, isFirst: true, diag: null, dateCode });
            continue;
        }

        const { schools, gradeLabels, testInfo, diag, legendItems } = parseSheet(ws);
        const schoolCount = Object.keys(schools).length;
        const isFirst = !newSheets[bucket][key];
        if (isFirst) newSheets[bucket][key] = schools;
        log.push({ originalName: sheetName, key, schoolCount, isFirst, diag, dateCode });

        if (detectedGrades.length === 0 && gradeLabels.length > 0) detectedGrades = gradeLabels;
        if (!detectedName && testInfo.name) detectedName = testInfo.name;
        if (!detectedDate && testInfo.date) detectedDate = testInfo.date;

        // 凡例アイテムを収集（同番は最初のシートを優先）
        for (const li of legendItems) {
            if (!legendByNum[li.num]) {
                legendByNum[li.num] = { ...li, sheetKey: key };
            }
        }
    }

    sheetsData.value = newSheets;
    sheetLog.value = log;
    gradeOptions.value = detectedGrades;
    detectedDates.value = [...allDates].sort();

    // 凡例アイテムを番号順にセット
    detectedItems.value = Object.values(legendByNum).sort(
        (a, b) => CIRCLED_NUMS.indexOf(a.num) - CIRCLED_NUMS.indexOf(b.num)
    );
    gradeLabelOverrides.value = {};
    gradeTestNameOverrides.value = {};
    gradeDateOverrides.value = {};

    if (detectedGrades.length > 0) selectedGrade.value = detectedGrades[0];
    if (detectedName) testNameVal.value = detectedName;
    if (detectedDate) testDateVal.value = detectedDate;
    // 略称の自動生成（テスト名から日付・余分な語を除去・8文字以内）
    if (detectedName && !shortNameVal.value) {
        const auto = detectedName
            .replace(/\d{4}年\d+月\d+[・〜～\-]\d+日?|\d{4}年\d+月\d+日|\d+月\d+[・〜～\-]\d+日?|\d+月\d+日?/g, '')
            .replace(/部数一覧表|実施|テスト名?/g, '')
            .replace(/[・\s　]+/g, '')
            .trim()
            .slice(0, 8);
        if (auto) shortNameVal.value = auto;
    }

    // 全バケットのsheetKeyを集めてプリセット推定
    const allKeys = new Set(
        Object.values(newSheets).flatMap(b => Object.keys(b)).filter(k => k !== 'ichishiki')
    );
    const presetKey = computePresetKey([...allKeys]);
    if (presetKey) selectedPreset.value = presetKey;
}

function parseSheet(ws) {
    const rows = XLSX.utils.sheet_to_json(ws, { header: 1, raw: false, defval: '' });
    const testInfo = { name: '', date: '' };

    const rawHeader = String(rows[0]?.[0] || rows[0]?.[1] || '').normalize('NFKC').trim();
    const mDate      = rawHeader.match(/^(\d+\/\d+)実施(.+)/);
    const mDateRange = rawHeader.match(/\d{4}年\d+月\d+[・〜～\-]\d+日/);
    const mDate2     = rawHeader.match(/\d{4}年(\d+)月(\d+)日/);
    if (mDate) { testInfo.date = mDate[1]; testInfo.name = mDate[2].trim(); }
    else if (mDateRange) {
        testInfo.date = mDateRange[0];  // "2026年3月21・22日" そのまま保存
        testInfo.name = rawHeader
            .replace(/\d{4}年\d+月\d+[・〜～\-]\d+日[^\S\r\n]*/g, '')
            .replace(/部数一覧表/g, '')
            .replace(/^[・\s　]+/, '')
            .trim();
    }
    else if (mDate2) {
        testInfo.date = mDate2[0];  // "2026年3月21日" フル形式で保存
        testInfo.name = rawHeader
            .replace(/\d{4}年\d+月\d+日[^\S\r\n]*/g, '')
            .replace(/・?\d+月\d+日?[^\S\r\n]*/g, '')
            .replace(/部数一覧表/g, '')
            .replace(/^[・\s　]+/, '')
            .trim();
    }
    else if (rawHeader) testInfo.name = rawHeader;

    const gradesByCol = {};
    for (let r = 0; r <= 7; r++) {
        const row = rows[r] || [];
        for (let c = 0; c < Math.min(row.length, 14); c++) {
            const cell = String(row[c] || '').normalize('NFKC').trim();
            const gm = cell.match(/^(\d年)生?$/);
            if (gm) gradesByCol[c] = gm[1];
        }
    }
    if (Object.keys(gradesByCol).length === 0) {
        Object.assign(gradesByCol, { 2: '3年', 3: '4年', 4: '5年', 5: '6年' });
    }
    const leftGradeEntries = Object.entries(gradesByCol)
        .filter(([c]) => Number(c) < 6)
        .map(([c, label]) => ({ relPos: Number(c), label }));

    const seenLabels = new Set();
    const gradeLabels = Object.entries(gradesByCol)
        .sort((a, b) => Number(a[0]) - Number(b[0]))
        .map(([, v]) => v)
        .filter(v => seenLabels.has(v) ? false : (seenLabels.add(v), true));

    const SKIP = /小計|合計|本部計|総合計/;
    const schools = {};
    const dataStart = detectDataStartRow(rows);
    let rowsFound = 0;

    for (let r = dataStart; r < rows.length; r++) {
        const row = rows[r];
        if (!row) continue;

        const seenCodes = new Set();
        for (const codeCol of [0, 1, 6, 7]) {
            if (codeCol >= row.length) continue;
            const code = normCode(row[codeCol]);
            if (!/^[A-Z]{2}$/.test(code) || seenCodes.has(code)) continue;
            const name = String(row[codeCol + 1] || '').trim();
            if (!name || SKIP.test(name) || /^\d+$/.test(name)) continue;
            seenCodes.add(code);

            // 同コードが複数行にある場合（例: AS=渋谷・表参道）は別エントリとして追加
            const schoolKey = schools[code] ? `${code}_${r}` : code;
            if (!schools[schoolKey]) {
                schools[schoolKey] = { code, name, grades: {}, rowIdx: r, colIdx: (codeCol <= 1) ? 0 : 1 };
                rowsFound++;
            }
            const school = schools[schoolKey];

            let gradesAdded = 0;
            for (const [absColStr, label] of Object.entries(gradesByCol)) {
                const absCol = Number(absColStr);
                const relPos = absCol - codeCol;
                if (relPos < 1 || relPos > 6) continue;
                const raw = String(row[absCol] || '').replace(/,/g, '').trim();
                const qty = parseInt(raw, 10);
                if (!isNaN(qty) && qty > 0) { school.grades[label] = qty; gradesAdded++; }
            }
            if (gradesAdded === 0 && codeCol >= 6) {
                for (const { relPos, label } of leftGradeEntries) {
                    const raw = String(row[codeCol + relPos] || '').replace(/,/g, '').trim();
                    const qty = parseInt(raw, 10);
                    if (!isNaN(qty) && qty > 0) { school.grades[label] = qty; gradesAdded++; }
                }
            }
        }
    }

    // 特殊行スキャン（東海本部・ユリウス・アトラス・予備）
    // コードなし行の col2 にキーワードが出現 → 合成コードでエントリ追加
    for (let r = dataStart; r < rows.length; r++) {
        const row = rows[r] || [];
        const cellName = String(row[2] || '').normalize('NFKC').trim();
        for (const [kw, code] of Object.entries(SPECIAL_ENTRY_KEYWORDS)) {
            if (cellName.includes(kw) && !schools[code]) {
                const grades = {};
                for (const [absColStr, gradeLabel] of Object.entries(gradesByCol)) {
                    const absCol = Number(absColStr);
                    if (absCol < 3 || absCol > 6) continue; // 左列グレード列のみ
                    const raw = String(row[absCol] || '').replace(/,/g, '').trim();
                    const qty = parseInt(raw, 10);
                    if (!isNaN(qty) && qty > 0) grades[gradeLabel] = qty;
                }
                if (Object.keys(grades).length > 0) {
                    schools[code] = { code, name: cellName, grades, rowIdx: r, colIdx: 0 };
                    rowsFound++;
                }
                break;
            }
        }
    }

    return {
        schools,
        gradeLabels: gradeLabels.length ? gradeLabels : ['3年', '4年', '5年', '6年'],
        testInfo,
        diag: { rowsTotal: rows.length, dataStart, rowsFound },
        legendItems: extractLegendItems(rows),
    };
}

// ============================================================
// ルートファイルパース
// ============================================================

async function handleRouteUpload(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    routeName.value = file.name;
    errorMsg.value = '';
    try {
        const ab = await file.arrayBuffer();
        const wb = XLSX.read(ab, { type: 'array' });
        const ws = wb.Sheets[wb.SheetNames[0]];
        const rows = XLSX.utils.sheet_to_json(ws, { header: 1, raw: false, defval: '' });
        const newMap = {};

        for (const headerRowIdx of [4, 27]) {
            const headerRow = rows[headerRowIdx] || [];
            for (let c = 0; c < headerRow.length; c++) {
                const cell = String(headerRow[c] || '').normalize('NFKC').trim();
                const m = cell.match(/^([A-I])-([12])$/);
                if (!m) continue;
                const routeCode = m[1] + m[2];
                let stop = 1;
                for (let r = headerRowIdx + 1; r < rows.length; r++) {
                    const row = rows[r] || [];
                    const nextHeader = String(row[c] || '').normalize('NFKC').trim();
                    if (nextHeader.match(/^[A-I]-[12]$/)) break;
                    const code = normCode(row[c + 1] || '');
                    if (/^[A-Z]{2}$/.test(code)) { newMap[code] = { route: routeCode, stop }; stop++; }
                }
            }
        }

        if (Object.keys(newMap).length > 5) {
            routeMap.value = newMap;
        } else {
            errorMsg.value = 'ルートファイルのパースに失敗しました（デフォルトを使用）';
            routeMap.value = buildRouteMapFromDB();
        }
    } catch (e) {
        errorMsg.value = `ルートファイルエラー: ${e.message}（デフォルトを使用）`;
        routeMap.value = buildRouteMapFromDB();
    }
}

// ============================================================
// シート解決（日付バケット + フォールバック）
// ============================================================

function resolveSheet(dateCode, sheetKey) {
    const bucket = sheetsData.value[dateCode] ?? {};
    const common = sheetsData.value['__common'] ?? {};

    const tryKeys = [sheetKey, ...(SHEET_FALLBACKS[sheetKey] ?? [])];
    for (const k of tryKeys) {
        const result = bucket[k];
        if (result && typeof result === 'object' && !Array.isArray(result)) return result;
    }
    for (const k of tryKeys) {
        const result = common[k];
        if (result && typeof result === 'object' && !Array.isArray(result)) return result;
    }
    return null;
}

// ============================================================
// ラベルデータ生成（日付・学年指定）
// ============================================================

function buildLabels(dateCode, grade) {
    const items = activeItems.value;
    if (!items.length || !grade) return [];
    const gradeDisplay  = gradeLabelOverrides.value[grade]    || grade;
    const gradeTestName = gradeTestNameOverrides.value[grade] || testNameVal.value;
    const gradeDate = detectedDates.value.length > 0
        ? (dateCode === '__common' ? testDateVal.value : dateCodeToDisplay(dateCode))
        : (gradeDateOverrides.value[grade] || testDateVal.value);
    const allLabels = [];

    for (const item of items) {
        const sheetSchools = resolveSheet(dateCode, item.sheetKey);
        if (!sheetSchools) continue;

        const itemLabels = [];
        for (const school of Object.values(sheetSchools)) {
            const qty = school.grades[grade];
            if (!qty || qty <= 0) continue;
            if (excludedCodes.value.has(school.code)) continue;

            const areaKey = getAreaKey(school.code);
            if (!selectedAreas.value.includes(areaKey) && areaKey !== 'other') continue;

            // DB マスタから表示名取得（AS重複は AS_1/AS_2 で登録済み）
            const dbSchool = schoolMap.value[school.code]
                ?? schoolMap.value[school.code.replace(/_\d+$/, '')]; // AS_10 → AS フォールバック
            const displayName = dbSchool?.display_name
                ?? (needsSchoolSuffix(school.name) ? school.name + '校' : school.name);
            const boxes = splitBoxes(qty, item.maxBox);
            const ri = routeMap.value[school.code];

            boxes.forEach((boxQty, bi) => {
                itemLabels.push({
                    itemKey:    `${item.num}_${item.sheetKey}_${item.itemLabel}`,
                    itemNum:    item.num,
                    subject:    item.subject,
                    itemLabel:  item.itemLabel,
                    routeCode:  ri?.route ?? '',
                    schoolCode: school.code.startsWith('$') ? '' : school.code,
                    schoolName: displayName,
                    _internalCode: school.code,
                    boxNum:     bi + 1,
                    boxTotal:   boxes.length,
                    quantity:   boxQty,
                    serial:     0,
                    testName:   gradeTestName,
                    date:       gradeDate,
                    grade:      gradeDisplay,
                    _routeOrder: ri ? ROUTE_ORDER.indexOf(ri.route) : -1,
                    _stopOrder:  ri?.stop ?? 9999,
                    _areaOrder:  AREA_ORDER[areaKey] ?? 0,
                    _rowIdx:     school.rowIdx,
                    _colIdx:     school.colIdx ?? 0,
                });
            });
        }

        // 並び順：SS(北海道)→関東ルート順(A1→I2)→非関東Excel行順→PA(徳島)最後
        itemLabels.sort((a, b) => {
            const aKey = labelSortKey(a);
            const bKey = labelSortKey(b);
            return aKey - bKey;
        });
        itemLabels.forEach((l, i) => { l.serial = i + 1; });
        allLabels.push(...itemLabels);
    }

    return allLabels;
}

// ============================================================
// Canvas レンダリング
// ============================================================

const SCALE = 1;
const PW = 729;
const PH = 516;

function drawLabel(ctx, label) {
    // 座標は原本PDFから実測（pdfplumber）。スケール1倍。
    // 原本フォント: W3=regular / W6=semibold(600) / W8=extrabold(900)
    // 横区切り線なし（メール便下の短い線のみ）、学年枠=角丸枠
    const s = n => n * SCALE;
    const W = PW * SCALE, H = PH * SCALE;

    // weight: 'normal'|'600'|'900'
    const F = (size, weight = 'normal') => {
        ctx.font = `${weight} ${s(size)}px "Hiragino Sans","Meiryo","MS Gothic",sans-serif`;
    };

    // 背景 + 外枠
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, W, H);
    ctx.strokeStyle = '#000';
    ctx.lineWidth = SCALE;
    ctx.strokeRect(s(4), s(4), W - s(8), H - s(8));

    // メール便下の短い横線（y=89, x=77-211, lw=2）
    ctx.strokeStyle = '#000';
    ctx.lineWidth = SCALE * 2;
    ctx.beginPath();
    ctx.moveTo(s(77), s(89));
    ctx.lineTo(s(211), s(89));
    ctx.stroke();

    ctx.fillStyle = '#000';
    ctx.textBaseline = 'alphabetic';

    // ── Section 1: 路線コード / メール便 / 箱番号 ──
    // 路線コード (A1〜I2): x=148, top=49, size=42, W6 → baseline≈85
    if (label.routeCode) {
        F(42, '600');
        ctx.textAlign = 'left';
        ctx.fillText(label.routeCode, s(148), s(85));
    }
    // メール便: x=78, top=72, size=14 → baseline≈84
    F(14, 'normal');
    ctx.fillText('メール便', s(78), s(84));
    // 箱番号: right-align, top=68, size=18 → baseline≈83
    F(18, 'normal');
    ctx.textAlign = 'right';
    ctx.fillText(`${label.boxNum} / ${label.boxTotal}`, s(650), s(83));
    ctx.textAlign = 'left';

    // ── Section 2: 教室名 / 教室コード行 ──
    // 教室名: x=140, top=122, size=35, W6 → baseline≈152
    F(35, '600');
    ctx.fillText(label.schoolName, s(140), s(152));
    // 教室コード (左に小さく): x=74, top=134, size=22, W3 → baseline≈153
    F(22, 'normal');
    ctx.fillText(label.schoolCode, s(74), s(153));
    // 「行」(教室名の右): 教室名幅を計測してから同じbaseline
    F(35, '600');
    const nameW = ctx.measureText(label.schoolName).width;
    F(22, 'normal');
    ctx.fillText('行', s(140) + nameW + s(6), s(153));

    // ── Section 3: 実施日 ──
    // 実施日: x=71, top=188, size=35, W3 → baseline≈218
    F(35, 'normal');
    ctx.fillText(label.date || '', s(71), s(218));
    // 「実施」: x=403, top=200, size=22 → baseline≈219
    F(22, 'normal');
    ctx.fillText('実施', s(403), s(219));

    // ── Section 4: 学年枠 / テスト名 / 科目 / 部数 ──
    // 学年枠: 角丸の黒枠（白背景）
    ctx.fillStyle = '#ffffff';
    ctx.beginPath();
    ctx.roundRect(s(43), s(243), s(121), s(97), s(10));
    ctx.fill();
    ctx.strokeStyle = '#000000';
    ctx.lineWidth = s(3);
    ctx.beginPath();
    ctx.roundRect(s(43), s(243), s(121), s(97), s(10));
    ctx.stroke();
    // 学年テキスト: x=50, top=260, size=60, W8 → baseline≈311（長い場合は自動縮小）
    ctx.fillStyle = '#000000';
    let gSize = 60;
    F(gSize, '900');
    const maxGradeW = s(110);
    while (gSize > 14 && label.grade && ctx.measureText(label.grade).width > maxGradeW) {
        gSize -= 2;
        F(gSize, '900');
    }
    ctx.fillText(label.grade || '', s(50), s(311));

    // テスト名: x=239, top=251, size=35, W3 → baseline≈281
    // 科目がある場合はx=490まで（"国算"手前）、ない場合はx=660まで
    if (label.testName) {
        const maxNameRight = label.subject ? s(490) : s(660);
        const maxNameW = maxNameRight - s(239);
        let tSize = 35;
        F(tSize, 'normal');
        while (tSize > 14 && ctx.measureText(label.testName).width > maxNameW) {
            tSize -= 2;
            F(tSize, 'normal');
        }
        ctx.fillText(label.testName, s(239), s(281));
    }
    // 科目 (右寄り): x=506, top=242, size=46, W6 → baseline≈281
    if (label.subject) {
        F(46, '600');
        ctx.fillText(label.subject, s(506), s(281));
    }

    // アイテム名: top=302, size=33, W3 → baseline≈330
    // アイテム名はx=364を中心に配置（原本測定値から）
    F(33, 'normal');
    const iw = ctx.measureText(label.itemLabel).width;
    const itemCenterX = s(364);
    const itemStartX = itemCenterX - iw / 2;
    ctx.fillText(label.itemLabel, itemStartX, s(330));
    // 数量+部: アイテム名右端 + 1em gap
    const qtyText = `${label.quantity}部`;
    ctx.fillText(qtyText, itemStartX + iw + s(33), s(330));

    // ── Footer ──
    F(12, 'normal');
    ctx.fillText(`通番 ${label.serial}`, s(602), s(370));
    ctx.fillText('(株)サンエー印刷', s(553), s(394));
}

// ============================================================
// PDF 生成（日付 × 学年 × アイテム）
// ============================================================

async function generatePDFs() {
    step.value = 3;
    progressPct.value = 0;
    progressMsg.value = 'ラベルデータを構築中...';
    outputFiles.value = [];
    errorMsg.value = '';

    await tick();

    const items = activeItems.value;
    if (!items.length) { errorMsg.value = 'アイテムが設定されていません。'; step.value = 1; return; }

    const dates  = detectedDates.value.length > 0 ? detectedDates.value : ['__common'];
    const grades = gradeOptions.value.length > 0 ? gradeOptions.value : (selectedGrade.value ? [selectedGrade.value] : []);
    const shortName = (shortNameVal.value || testNameVal.value || 'テスト').replace(/[\\/:*?"<>|]/g, '_');

    if (grades.length === 0) {
        errorMsg.value = '学年が検出されていません。対象学年を入力してください。';
        step.value = 1;
        return;
    }

    // 実施日が全学年で未設定かつシート名日付もない場合は確認ダイアログ
    if (detectedDates.value.length === 0 && !testDateVal.value && !Object.values(gradeDateOverrides.value).some(v => v)) {
        const ok = confirm('実施日が未入力です。ラベルの実施日欄が空白になり、ファイル名のMMDDも省略されます。このまま出力しますか？');
        if (!ok) { step.value = 1; return; }
    }

    const canvas = document.createElement('canvas');
    canvas.width  = PW * SCALE;
    canvas.height = PH * SCALE;
    const ctx = canvas.getContext('2d');
    const files = [];

    // 合計ファイル数を事前算出（進捗表示用）
    let totalPlan = dates.length * grades.length * items.length;
    let done = 0;

    for (const dateCode of dates) {
        for (const grade of grades) {
            const gradeDisplay = gradeLabelOverrides.value[grade] || grade;
            // 学年別実施日 → ファイル名MMDD（パターンB対応）
            const gradeDate = gradeDateOverrides.value[grade] || testDateVal.value;
            const datePart  = dateCode === '__common' ? dateValToMMDD(gradeDate) : dateCode;
            // この日付×学年の全アイテムラベルを一括生成
            const allLabels = buildLabels(dateCode, grade);

            for (const item of items) {
                done++;
                progressPct.value = Math.round((done / totalPlan) * 88);
                progressMsg.value = `${datePart || ''}${gradeDisplay} ${item.num}${item.subject}${item.itemLabel} を生成中...`;
                await tick();

                const itemKey = `${item.num}_${item.sheetKey}_${item.itemLabel}`;
                const groupLabels = allLabels.filter(l => l.itemKey === itemKey);
                if (groupLabels.length === 0) continue; // この学年×アイテムは0件→スキップ

                const doc = new jsPDF({ orientation: 'landscape', unit: 'pt', format: [PW, PH] });
                for (let li = 0; li < groupLabels.length; li++) {
                    if (li > 0) doc.addPage();
                    drawLabel(ctx, groupLabels[li]);
                    doc.addImage(canvas.toDataURL('image/jpeg', 0.65), 'JPEG', 0, 0, PW, PH);
                }

                // ファイル名: MMDD略称学年番号.pdf
                const fileName = `${datePart}${shortName}${gradeDisplay}${item.num}.pdf`;
                files.push({
                    name:  fileName,
                    blob:  doc.output('blob'),
                    count: groupLabels.length,
                    label: `${datePart} ${gradeDisplay} ${item.num} ${item.subject}${item.itemLabel}`.trim(),
                });
            }
        }
    }

    if (files.length === 0) {
        errorMsg.value = 'ラベルが生成されませんでした。Excelファイルと設定を確認してください。';
        step.value = 1;
        return;
    }

    outputFiles.value = files;
    const totalSheets = files.reduce((s, f) => s + f.count, 0);
    progressPct.value = 100;
    progressMsg.value = `完了！合計 ${totalSheets} 枚 / ${files.length} ファイル`;
    step.value = 4;
}

function tick() { return new Promise(r => setTimeout(r, 0)); }

async function saveAllFiles() {
    const dirHandle = saveDirHandle.value;
    if (dirHandle) {
        try {
            for (const f of outputFiles.value) {
                const fh = await dirHandle.getFileHandle(f.name, { create: true });
                const w  = await fh.createWritable();
                await w.write(f.blob);
                await w.close();
            }
            alert(`${outputFiles.value.length} ファイルを「${dirHandle.name}」に保存しました。`);
        } catch (e) {
            if (e.name !== 'AbortError') errorMsg.value = `保存エラー: ${e.message}`;
        }
    } else if (hasFSA) {
        await pickSaveDir();
        if (saveDirHandle.value) await saveAllFiles();
    } else {
        for (const f of outputFiles.value) {
            downloadFile(f);
            await new Promise(r => setTimeout(r, 400));
        }
    }
}

function downloadFile(f) {
    const url = URL.createObjectURL(f.blob);
    const a   = document.createElement('a');
    a.href = url; a.download = f.name; a.click();
    URL.revokeObjectURL(url);
}

// ============================================================
// Computed
// ============================================================

const presetItems = computed(() => PRESETS[selectedPreset.value]?.items ?? []);

// OCR確認済み > Excel検出 > PRESET の優先順
const activeItems = computed(() =>
    confirmedItems.value.length > 0
        ? confirmedItems.value
        : (detectedItems.value.length > 0
            ? detectedItems.value
            : (PRESETS[selectedPreset.value]?.items ?? []))
);

// アイテム編集エディタが操作するリスト（confirmedItems 優先、なければ detectedItems）
const activeEditItems = computed(() =>
    confirmedItems.value.length > 0 ? confirmedItems.value : detectedItems.value
);

const canGenerate = computed(() =>
    excelName.value &&
    gradeOptions.value.length > 0 &&
    testNameVal.value &&
    shortNameVal.value &&
    activeItems.value.length > 0
);

// プレビュー用：最初の日付 + selectedGrade でアイテム別の教室数を計算
const labelCountPerItem = computed(() => {
    if (!excelName.value || !selectedGrade.value) return [];
    const previewDate = detectedDates.value[0] ?? '__common';
    const grade = selectedGrade.value;
    return activeItems.value.map(item => {
        const sh = resolveSheet(previewDate, item.sheetKey);
        if (!sh) return { ...item, count: 0, warn: `シート未検出 (${item.sheetKey})` };
        const count = Object.values(sh).filter(sc =>
            (sc.grades[grade] || 0) > 0 && !excludedCodes.value.has(sc.code)
        ).length;
        return { ...item, count, warn: '' };
    });
});

const suggestedPreset = computed(() => {
    const allKeys = new Set(
        Object.values(sheetsData.value).flatMap(b => Object.keys(b)).filter(k => k !== 'ichishiki')
    );
    const key = computePresetKey([...allKeys]);
    if (!key) return '';
    return `${key}（${PRESETS[key]?.label?.replace(/^[A-Z]: /, '') ?? ''}）`;
});

// 検出された実施日の表示用
const detectedDatesDisplay = computed(() =>
    detectedDates.value.map(d => {
        const m = d.match(/^(\d{2})(\d{2})$/);
        return m ? `${parseInt(m[1])}/${parseInt(m[2])}` : d;
    }).join('、')
);

// 一式が存在する日付
const datesWithIchishiki = computed(() =>
    detectedDates.value.filter(d => sheetsData.value[d]?.['ichishiki'])
        .concat(sheetsData.value['__common']?.['ichishiki'] ? ['（共通）'] : [])
);

// シートログ表示用（「原本」シートと TS除外シートは非表示）
const visibleSheetLog = computed(() =>
    sheetLog.value.filter(s =>
        !s.originalName.normalize('NFKC').includes('原本') && s.key !== 'exclude'
    )
);

function toggleArea(key) {
    const arr = [...selectedAreas.value];
    const idx = arr.indexOf(key);
    if (idx >= 0) arr.splice(idx, 1); else arr.push(key);
    selectedAreas.value = arr;
}
function selectAllAreas() { selectedAreas.value = AREAS.map(a => a.key); }
function clearAllAreas()   { selectedAreas.value = []; }

// ============================================================
// アイテム・学年オーバーライド ヘルパー
// ============================================================

function gradeDisplayLabel(g) { return gradeLabelOverrides.value[g] || g; }
function addItemRow() {
    const target = confirmedItems.value.length > 0 ? confirmedItems : detectedItems;
    target.value.push({ num: '', subject: '', itemLabel: '', maxBox: 100, sheetKey: 'kokusan' });
}
function removeItemRow(idx) {
    const target = confirmedItems.value.length > 0 ? confirmedItems : detectedItems;
    target.value.splice(idx, 1);
}

// ============================================================
// アイテムPDF OCR
// ============================================================

function inferSheetKeyFromItem(text) {
    const n = (text || '').normalize('NFKC');
    if (/DI答案|答案用紙/.test(n)) {
        return /社会|社理|理科/.test(n) ? 'shashiri_di' : 'kokusan_di';
    }
    if (/国算/.test(n)) return 'kokusan';
    if (/社理|社会|理科/.test(n)) return 'shashiri';
    return 'main';
}

async function handlePdfDrop(e) {
    const file = e.dataTransfer?.files?.[0];
    if (file) await uploadItemPdf(file);
}

async function handlePdfSelect(e) {
    const file = e.target?.files?.[0];
    if (!file) return;
    e.target.value = '';
    await uploadItemPdf(file);
}

async function uploadItemPdf(file) {
    itemPdfName.value = file.name;
    ocrStep.value     = 'uploading';
    ocrError.value    = '';

    const fd = new FormData();
    fd.append('file', file);

    try {
        const { data } = await axios.post('/label-ocr/analyze', fd);
        ocrRawText.value     = data.ocr_text || '';
        modalTests.value     = (data.tests  || []).map(t => ({ ...t }));
        modalItems.value     = (data.items  || []).map(item => ({
            ...item,
            sheetKey: inferSheetKeyFromItem(item.text_raw || ''),
        }));
        modalIchishiki.value = data.ichishiki || false;
        showOcrModal.value   = true;
    } catch (e) {
        ocrError.value    = `OCRエラー: ${e.response?.data?.error || e.message}`;
        ocrStep.value     = 'idle';
        itemPdfName.value = '';
    }
}

function confirmOcrResult() {
    confirmedItems.value = modalItems.value.map(item => {
        const text = (item.text_raw || '').normalize('NFKC');
        const { subject, itemLabel } = parseItemLabel('①' + text);
        return {
            num:       item.num,
            subject,
            itemLabel: itemLabel || text,
            maxBox:    item.max_box,
            sheetKey:  item.sheetKey,
        };
    });
    confirmedTests.value = modalTests.value.map(t => ({ ...t }));
    ichishikiFlag.value  = modalIchishiki.value;

    if (!testNameVal.value && modalTests.value.length > 0) {
        testNameVal.value = modalTests.value[0].name_raw;
    }

    showOcrModal.value = false;
    ocrStep.value      = 'done';
}

function selectModalTestName(idx, name) {
    modalTests.value[idx] = { ...modalTests.value[idx], name_raw: name };
}

function addModalItem() {
    modalItems.value.push({ num: '', text_raw: '', max_box: 100, sheetKey: 'kokusan' });
}
function removeModalItem(idx) { modalItems.value.splice(idx, 1); }

function clearOcr() {
    confirmedItems.value = [];
    confirmedTests.value = [];
    ichishikiFlag.value  = false;
    ocrStep.value        = 'idle';
    itemPdfName.value    = '';
    ocrRawText.value     = '';
}
</script>

<template>
    <div class="space-y-4">

        <!-- メインタブ -->
        <div class="flex gap-1 border-b border-gray-200">
            <button @click="activeTab = 'tool'"
                class="px-5 py-2 text-sm font-medium border-b-2 transition"
                :class="activeTab === 'tool' ? 'border-orange-500 text-orange-700' : 'border-transparent text-gray-500 hover:text-gray-700'">
                ラベル生成
            </button>
            <button @click="activeTab = 'master'"
                class="px-5 py-2 text-sm font-medium border-b-2 transition"
                :class="activeTab === 'master' ? 'border-orange-500 text-orange-700' : 'border-transparent text-gray-500 hover:text-gray-700'">
                マスタ管理
                <span v-if="masterLoading" class="ml-1 text-xs text-gray-400">読込中…</span>
            </button>
        </div>

        <!-- ============ マスタ管理タブ ============ -->
        <div v-if="activeTab === 'master'" class="space-y-3">

            <!-- サブタブ -->
            <div class="flex gap-1 bg-gray-100 rounded-lg p-1 w-fit">
                <button v-for="[key, label] in [['schools','教室'],['testNames','テスト名'],['subjects','科目'],['itemTypes','内容']]"
                    :key="key" @click="masterTab = key; cancelEdit(); cancelAdd();"
                    class="px-4 py-1.5 text-xs font-medium rounded-md transition"
                    :class="masterTab === key ? 'bg-white shadow text-orange-700' : 'text-gray-600 hover:text-gray-800'">
                    {{ label }}
                    <span class="ml-1 text-gray-400">{{ masterArray(key).value.length }}</span>
                </button>
            </div>

            <!-- エラー -->
            <div v-if="errorMsg" class="rounded bg-red-50 border border-red-300 px-4 py-2 text-sm text-red-700">
                {{ errorMsg }} <button @click="errorMsg=''" class="ml-2 text-xs underline">閉じる</button>
            </div>

            <!-- ── 教室マスタ ── -->
            <div v-if="masterTab === 'schools'" class="rounded bg-white shadow overflow-x-auto">
                <table class="min-w-full text-xs divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">コード</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">表示名</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">エリア</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">ルート</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">順</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">有効</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">備考</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <!-- 既存行 -->
                        <tr v-for="s in masterSchools" :key="s.id"
                            :class="{ 'bg-gray-50 opacity-60': !s.is_active }">
                            <template v-if="editingId === s.id">
                                <td class="px-2 py-1"><input v-model="editingRow.code" class="w-16 border rounded px-1 py-0.5" /></td>
                                <td class="px-2 py-1"><input v-model="editingRow.display_name" class="w-32 border rounded px-1 py-0.5" /></td>
                                <td class="px-2 py-1">
                                    <select v-model="editingRow.area" class="border rounded px-1 py-0.5 text-xs">
                                        <option v-for="a in ['関東','東海','関西','中国','四国','九州・沖縄','北海道']" :key="a">{{ a }}</option>
                                    </select>
                                </td>
                                <td class="px-2 py-1"><input v-model="editingRow.route" class="w-12 border rounded px-1 py-0.5" placeholder="A1" /></td>
                                <td class="px-2 py-1"><input v-model.number="editingRow.stop_order" type="number" min="1" max="99" class="w-10 border rounded px-1 py-0.5" /></td>
                                <td class="px-2 py-1 text-center"><input type="checkbox" v-model="editingRow.is_active" /></td>
                                <td class="px-2 py-1"><input v-model="editingRow.notes" class="w-40 border rounded px-1 py-0.5" /></td>
                                <td class="px-2 py-1 whitespace-nowrap">
                                    <button @click="saveEdit('schools')" class="text-green-600 hover:underline mr-2">保存</button>
                                    <button @click="cancelEdit" class="text-gray-400 hover:underline">取消</button>
                                </td>
                            </template>
                            <template v-else>
                                <td class="px-3 py-1.5 font-mono font-medium text-gray-800">{{ s.code }}</td>
                                <td class="px-3 py-1.5 text-gray-700">{{ s.display_name }}</td>
                                <td class="px-3 py-1.5 text-gray-500">{{ s.area }}</td>
                                <td class="px-3 py-1.5 text-gray-500">{{ s.route || '—' }}</td>
                                <td class="px-3 py-1.5 text-gray-500">{{ s.stop_order || '—' }}</td>
                                <td class="px-3 py-1.5 text-center">
                                    <span :class="s.is_active ? 'text-green-600' : 'text-gray-400'">{{ s.is_active ? '✔' : '✗' }}</span>
                                </td>
                                <td class="px-3 py-1.5 text-gray-400 text-xs max-w-[200px] truncate">{{ s.notes }}</td>
                                <td class="px-3 py-1.5 whitespace-nowrap">
                                    <button @click="startEdit('schools', s)" class="text-blue-600 hover:underline mr-2">編集</button>
                                    <button @click="deleteMasterRecord('schools', s.id)" class="text-red-400 hover:underline">削除</button>
                                </td>
                            </template>
                        </tr>
                        <!-- 新規追加行 -->
                        <tr v-if="addingRow && addingRow._tab === 'schools'" class="bg-orange-50">
                            <td class="px-2 py-1"><input v-model="addingRow.code" class="w-16 border border-orange-300 rounded px-1 py-0.5" placeholder="AA" /></td>
                            <td class="px-2 py-1"><input v-model="addingRow.display_name" class="w-32 border border-orange-300 rounded px-1 py-0.5" placeholder="表示名" /></td>
                            <td class="px-2 py-1">
                                <select v-model="addingRow.area" class="border border-orange-300 rounded px-1 py-0.5 text-xs">
                                    <option v-for="a in ['関東','東海','関西','中国','四国','九州・沖縄','北海道']" :key="a">{{ a }}</option>
                                </select>
                            </td>
                            <td class="px-2 py-1"><input v-model="addingRow.route" class="w-12 border border-orange-300 rounded px-1 py-0.5" placeholder="A1" /></td>
                            <td class="px-2 py-1"><input v-model.number="addingRow.stop_order" type="number" min="1" max="99" class="w-10 border border-orange-300 rounded px-1 py-0.5" /></td>
                            <td class="px-2 py-1 text-center"><input type="checkbox" v-model="addingRow.is_active" /></td>
                            <td class="px-2 py-1"><input v-model="addingRow.notes" class="w-40 border border-orange-300 rounded px-1 py-0.5" placeholder="備考" /></td>
                            <td class="px-2 py-1 whitespace-nowrap">
                                <button @click="saveAdd" class="text-green-600 hover:underline mr-2">追加</button>
                                <button @click="cancelAdd" class="text-gray-400 hover:underline">取消</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div class="px-4 py-2 border-t bg-gray-50">
                    <button v-if="!addingRow" @click="startAdd('schools')"
                        class="text-sm text-orange-600 hover:text-orange-800 font-medium">+ 教室を追加</button>
                </div>
            </div>

            <!-- ── テスト名 / 科目 / 内容（共通テーブル） ── -->
            <template v-for="[tab, label, arr] in [
                ['testNames', 'テスト名', masterTestNames],
                ['subjects',  '科目',     masterSubjects],
                ['itemTypes', '内容',     masterItemTypes],
            ]" :key="tab">
                <div v-if="masterTab === tab" class="rounded bg-white shadow overflow-x-auto">
                    <table class="min-w-full text-xs divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">名前</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600 w-16">順序</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600 w-12">有効</th>
                                <th class="px-3 py-2 w-24"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="r in arr" :key="r.id" :class="{ 'bg-gray-50 opacity-60': !r.is_active }">
                                <template v-if="editingId === r.id">
                                    <td class="px-2 py-1"><input v-model="editingRow.name" class="w-full border rounded px-1 py-0.5" /></td>
                                    <td class="px-2 py-1"><input v-model.number="editingRow.sort_order" type="number" class="w-14 border rounded px-1 py-0.5" /></td>
                                    <td class="px-2 py-1 text-center"><input type="checkbox" v-model="editingRow.is_active" /></td>
                                    <td class="px-2 py-1 whitespace-nowrap">
                                        <button @click="saveEdit(tab)" class="text-green-600 hover:underline mr-2">保存</button>
                                        <button @click="cancelEdit" class="text-gray-400 hover:underline">取消</button>
                                    </td>
                                </template>
                                <template v-else>
                                    <td class="px-3 py-1.5 text-gray-700">{{ r.name }}</td>
                                    <td class="px-3 py-1.5 text-gray-500">{{ r.sort_order }}</td>
                                    <td class="px-3 py-1.5 text-center">
                                        <span :class="r.is_active ? 'text-green-600' : 'text-gray-400'">{{ r.is_active ? '✔' : '✗' }}</span>
                                    </td>
                                    <td class="px-3 py-1.5 whitespace-nowrap">
                                        <button @click="startEdit(tab, r)" class="text-blue-600 hover:underline mr-2">編集</button>
                                        <button @click="deleteMasterRecord(tab, r.id)" class="text-red-400 hover:underline">削除</button>
                                    </td>
                                </template>
                            </tr>
                            <!-- 新規追加行 -->
                            <tr v-if="addingRow && addingRow._tab === tab" class="bg-orange-50">
                                <td class="px-2 py-1"><input v-model="addingRow.name" class="w-full border border-orange-300 rounded px-1 py-0.5" :placeholder="label + 'を入力'" /></td>
                                <td class="px-2 py-1"><input v-model.number="addingRow.sort_order" type="number" class="w-14 border border-orange-300 rounded px-1 py-0.5" /></td>
                                <td class="px-2 py-1 text-center"><input type="checkbox" v-model="addingRow.is_active" /></td>
                                <td class="px-2 py-1 whitespace-nowrap">
                                    <button @click="saveAdd" class="text-green-600 hover:underline mr-2">追加</button>
                                    <button @click="cancelAdd" class="text-gray-400 hover:underline">取消</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="px-4 py-2 border-t bg-gray-50">
                        <button v-if="!addingRow" @click="startAdd(tab)"
                            class="text-sm text-orange-600 hover:text-orange-800 font-medium">+ {{ label }}を追加</button>
                    </div>
                </div>
            </template>
        </div>

        <!-- ============ ツールタブ ============ -->
        <div v-if="activeTab === 'tool'" class="space-y-4">

        <!-- ルール説明パネル -->
        <div class="rounded bg-white shadow">
            <button
                class="flex w-full items-center justify-between px-5 py-3 text-left text-sm font-medium text-gray-700 hover:bg-gray-50"
                @click="showRules = !showRules"
            >
                <span>📋 ルール説明・確認事項（作業担当者向け）</span>
                <span class="text-gray-400 text-xs">{{ showRules ? '▲ 閉じる' : '▼ 開く' }}</span>
            </button>
            <div v-if="showRules" class="border-t px-5 py-4 text-sm text-gray-700 space-y-4">
                <div class="bg-yellow-50 border border-yellow-300 rounded p-3 text-xs">
                    <strong>⚠️ 以下の項目は担当者との確認が取れていません。動作を確認し、必要に応じて調整してください。</strong>
                </div>
                <div class="space-y-3 text-xs">
                    <details open>
                        <summary class="font-semibold cursor-pointer">【Q1】「校」を付ける・付けない判定</summary>
                        <p class="mt-1 ml-3">教室名に「本部・受付・ロジ・研究・コバ・別館・職員」等が含まれる場合は「校」を付けない。一般教室には「校」を付ける。</p>
                    </details>
                    <details>
                        <summary class="font-semibold cursor-pointer">【Q2】仕分けコードのない教室の扱い</summary>
                        <p class="mt-1 ml-3">社内便ルート一覧にないコードは仕分けコード欄が空白のラベルを出力します。</p>
                    </details>
                    <details>
                        <summary class="font-semibold cursor-pointer">【Q3】一式ラベルの対象（Phase2未実装）</summary>
                        <p class="mt-1 ml-3">「一式」シートに記載の本部内部部署のみ一式ラベル。コバ・予備・関東物流は個別アイテムラベル。現在：一式シートは読み込み対象外（Phase 2 対応予定）。</p>
                    </details>
                    <details>
                        <summary class="font-semibold cursor-pointer">【Q4】TS宛紙不要シートの扱い</summary>
                        <p class="mt-1 ml-3">「テストサービス」「TS」を含むシートのコードはラベル出力をスキップします。</p>
                    </details>
                    <details>
                        <summary class="font-semibold cursor-pointer">【Q5】学年ごとに実施日が異なるケース（パターンB）</summary>
                        <p class="mt-1 ml-3">シート名に日付がない場合（例: 学習力育成テスト）は全学年が同じ「実施日」フィールドを使います。学年ごとに日付が異なる場合は手動修正してください。</p>
                    </details>
                </div>
                <div class="border-t pt-3 space-y-2 text-xs">
                    <p class="font-semibold">処理ルール（自動化済み）</p>
                    <ul class="ml-3 list-disc space-y-1">
                        <li>出力ファイル名: MMDD略称学年番号.pdf（例: 0330春期特別3年①.pdf）</li>
                        <li>複数実施日: シート名から自動検出し、日付ごとに別フォルダ相当で生成</li>
                        <li>仕分けコード = 社内便ルートコード（A1, B1...I2）</li>
                        <li>出力順：ルート外 → A1→I1 → A2→I2 → 本部部署</li>
                        <li>箱分割：解説=100部 / 問題用紙=50部 / DI答案=250部</li>
                        <li>通番：PDFファイルごとに1から連番</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- エラーメッセージ -->
        <div v-if="errorMsg" class="rounded bg-red-50 border border-red-300 px-4 py-2 text-sm text-red-700">
            {{ errorMsg }}
        </div>

        <!-- ============ Step 1: 設定コンソール ============ -->
        <div v-if="step === 1" class="rounded-lg bg-orange-50 border border-orange-200 shadow p-6 space-y-5">
            <h3 class="text-base font-semibold text-orange-900">宛先ラベル出力設定</h3>

            <!-- ── アイテムPDF OCR ── -->
            <div class="space-y-2">
                <label class="block text-xs font-medium text-gray-600">
                    アイテムPDF <span class="text-gray-400">（テスト名・アイテムをOCRで自動検出）</span>
                    <span class="ml-1 text-gray-400">省略可 — 省略時はExcel凡例またはプリセットを使用</span>
                </label>

                <!-- 確認済み -->
                <div v-if="ocrStep === 'done'" class="flex flex-wrap items-center gap-2 rounded border border-green-200 bg-green-50 px-3 py-2 text-xs">
                    <span class="text-green-700 font-medium">✔ {{ itemPdfName }}</span>
                    <span class="text-green-600">— {{ confirmedItems.length }} 件のアイテムを確認済み</span>
                    <button @click="showOcrModal = true" type="button"
                        class="ml-auto text-xs text-blue-600 hover:underline">再編集</button>
                    <button @click="clearOcr" type="button"
                        class="text-xs text-gray-400 hover:text-red-500">× クリア</button>
                </div>

                <!-- アップロード中 -->
                <div v-else-if="ocrStep === 'uploading'"
                    class="flex items-center gap-3 rounded border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-700">
                    <svg class="animate-spin h-4 w-4 text-orange-500 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    OCR解析中... （{{ itemPdfName }}）
                </div>

                <!-- ドロップゾーン -->
                <div v-else
                    class="relative flex flex-col items-center justify-center rounded-lg border-2 border-dashed border-orange-300 bg-white px-6 py-5 transition hover:border-orange-400 hover:bg-orange-50 cursor-pointer"
                    @dragover.prevent
                    @drop.prevent="handlePdfDrop"
                    @click="pdfInputRef?.click()">
                    <svg class="h-7 w-7 text-orange-300 mb-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                    </svg>
                    <p class="text-sm text-orange-700 font-medium">アイテムPDFをドロップ</p>
                    <p class="text-xs text-gray-400 mt-0.5">またはクリックしてファイルを選択（PDF / スキャン画像）</p>
                    <input ref="pdfInputRef" type="file" accept=".pdf,image/*" class="hidden"
                        @change="handlePdfSelect" />
                </div>

                <p v-if="ocrError" class="text-xs text-red-600">{{ ocrError }}</p>
            </div>

            <!-- ファイル選択 -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">発送部数 Excel（s1_*.xls）</label>
                    <!-- showOpenFilePicker 対応ブラウザ（Chrome/Edge）-->
                    <button v-if="hasFSA" @click="pickExcelFile" type="button"
                        class="w-full text-left px-3 py-1.5 rounded border border-gray-300 bg-white text-sm text-gray-700 hover:bg-orange-50 hover:border-orange-400 transition">
                        <span class="text-orange-700 font-medium">Excel を選択…</span>
                        <span v-if="excelName" class="ml-2 text-xs text-green-700">✔ {{ excelName }}</span>
                    </button>
                    <!-- フォールバック（Firefox等）-->
                    <input v-else type="file" accept=".xls,.xlsx" @change="handleExcelUpload"
                        class="block w-full text-sm text-gray-700 file:mr-2 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:bg-orange-200 file:text-orange-800 hover:file:bg-orange-300 cursor-pointer" />
                    <p v-if="!hasFSA && excelName" class="mt-1 text-xs text-green-700">✔ {{ excelName }}</p>
                    <p class="mt-1 text-xs text-gray-400">Shift-JIS .xls / .xlsx 対応</p>
                    <!-- 保存先フォルダ（FSA対応時のみ） -->
                    <div v-if="hasFSA && excelName" class="mt-2 flex items-center gap-2">
                        <span class="text-xs text-gray-600">保存先:</span>
                        <span v-if="saveDirName" class="text-xs text-blue-700 font-medium">📁 {{ saveDirName }}</span>
                        <span v-else class="text-xs text-gray-400">保存時にExcelと同フォルダが開きます</span>
                        <button v-if="saveDirName" @click="pickSaveDir" type="button"
                            class="text-xs px-2 py-0.5 rounded bg-gray-100 hover:bg-gray-200 text-gray-700 border border-gray-300">変更</button>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">社内便ルート一覧.xlsx（省略可）</label>
                    <input type="file" accept=".xlsx,.xls" @change="handleRouteUpload"
                        class="block w-full text-sm text-gray-700 file:mr-2 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:bg-orange-200 file:text-orange-800 hover:file:bg-orange-300 cursor-pointer" />
                    <p v-if="routeName" class="mt-1 text-xs text-green-700">✔ {{ routeName }}</p>
                    <p v-else class="mt-1 text-xs text-gray-400">省略時は 2025年版をデフォルト使用</p>
                </div>
            </div>

            <!-- 自動検出ステータス -->
            <div v-if="excelName && sheetLog.length > 0"
                class="rounded border border-green-200 bg-green-50 px-4 py-3 space-y-2">
                <p class="text-xs font-semibold text-green-800">✔ Excelを読み込みました — 以下の設定を自動適用しました</p>
                <div class="grid grid-cols-2 gap-x-6 gap-y-1 text-xs text-green-900">
                    <div v-if="confirmedItems.length > 0">
                        アイテム: <strong class="text-green-700">{{ confirmedItems.length }}件（アイテムPDF確認済み）</strong>
                    </div>
                    <div v-else-if="detectedItems.length > 0">
                        アイテム: <strong class="text-green-700">{{ detectedItems.length }}件（Excel凡例から検出）</strong>
                    </div>
                    <div v-else>プリセット: <strong>{{ PRESETS[selectedPreset]?.label ?? '—' }}</strong></div>
                    <div>対象学年: <strong>{{ gradeOptions.join('、') || '未検出（下で入力）' }}</strong></div>
                    <div>テスト名: <strong class="truncate">{{ testNameVal || '未検出（下で入力）' }}</strong></div>
                    <div v-if="detectedDates.length > 0">
                        複数実施日（シートから検出）: <strong>{{ detectedDatesDisplay }}</strong>
                    </div>
                    <div v-else>
                        実施日: <strong v-if="testDateVal" class="text-green-700">{{ testDateVal }}</strong>
                        <strong v-else class="text-yellow-700">未検出（下で入力）</strong>
                    </div>
                </div>
                <p v-if="datesWithIchishiki.length > 0" class="text-xs text-green-700">
                    一式シートあり（{{ datesWithIchishiki.join('、') }}）— Phase2で出力対応予定
                </p>
                <p class="text-xs text-green-700">内容が違う場合は下の項目で修正してください。</p>
            </div>

            <!-- 学年別設定（テスト名・実施日・印字ラベル名） -->
            <div v-if="gradeOptions.length > 0" class="rounded border border-orange-200 bg-white px-4 py-3 space-y-2">
                <p class="text-xs font-medium text-gray-700">学年別設定 <span class="text-gray-400 font-normal">（空欄 = 上部の設定をそのまま使用）</span></p>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="text-gray-400 text-left">
                                <th class="pb-1 pr-2 w-10">学年</th>
                                <th class="pb-1 px-2">印字テスト名</th>
                                <th v-if="detectedDates.length === 0" class="pb-1 px-2">実施日</th>
                                <th class="pb-1 px-2">印字ラベル名</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="g in gradeOptions" :key="g">
                                <td class="py-1 pr-2 text-gray-500">{{ g }}</td>
                                <td class="py-1 px-2">
                                    <input v-model="gradeTestNameOverrides[g]" type="text"
                                        :placeholder="testNameVal || '（上部と同じ）'"
                                        class="w-full rounded border border-gray-300 bg-white px-1.5 py-0.5 focus:outline-none focus:ring-1 focus:ring-orange-400" />
                                </td>
                                <td v-if="detectedDates.length === 0" class="py-1 px-2">
                                    <input v-model="gradeDateOverrides[g]" type="text"
                                        :placeholder="testDateVal || '例: 2026年3月21日'"
                                        class="w-full rounded border border-gray-300 bg-white px-1.5 py-0.5 focus:outline-none focus:ring-1 focus:ring-orange-400" />
                                </td>
                                <td class="py-1 px-2">
                                    <input v-model="gradeLabelOverrides[g]" type="text"
                                        :placeholder="g"
                                        class="w-full rounded border border-gray-300 bg-white px-1.5 py-0.5 focus:outline-none focus:ring-1 focus:ring-orange-400" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="text-xs text-gray-400">空欄はデフォルト値を使用。印字ラベル名例: 3年 → マイファースト / 実施日例: 6年のみ3月22日の場合は「2026年3月22日」</p>
            </div>

            <!-- アイテムリスト編集パネル -->
            <div v-if="activeItems.length > 0" class="space-y-1">
                <div class="flex items-center justify-between">
                    <label class="text-xs font-medium text-gray-600">
                        出力アイテム
                        <span v-if="confirmedItems.length > 0" class="ml-1 text-green-700 font-normal">（アイテムPDF確認済み）</span>
                        <span v-else-if="detectedItems.length > 0" class="ml-1 text-green-700 font-normal">（Excel凡例から検出）</span>
                        <span v-else class="ml-1 text-orange-600 font-normal">（プリセット: {{ PRESETS[selectedPreset]?.label ?? '—' }}）</span>
                    </label>
                    <button @click="showItemEditor = !showItemEditor" type="button"
                        class="text-xs text-blue-600 hover:underline">
                        {{ showItemEditor ? '▲ 閉じる' : '▼ 編集する' }}
                    </button>
                </div>

                <!-- 編集モード -->
                <div v-if="showItemEditor" class="rounded border border-blue-200 bg-blue-50 p-3 space-y-2">
                    <div v-if="activeEditItems.length === 0" class="text-xs text-gray-400 italic">
                        アイテムがありません。「+ アイテムを追加」で手動入力できます。
                    </div>
                    <div v-for="(item, idx) in activeEditItems" :key="idx"
                        class="flex items-center gap-1.5 text-xs">
                        <input v-model="activeEditItems[idx].num" type="text" placeholder="①"
                            class="w-8 rounded border border-gray-300 px-1 py-0.5 text-center" />
                        <input v-model="activeEditItems[idx].subject" type="text" placeholder="科目"
                            class="w-16 rounded border border-gray-300 px-1 py-0.5" />
                        <input v-model="activeEditItems[idx].itemLabel" type="text" placeholder="内容"
                            class="flex-1 rounded border border-gray-300 px-1 py-0.5" />
                        <select v-model="activeEditItems[idx].sheetKey"
                            class="w-28 rounded border border-gray-300 px-1 py-0.5 text-xs">
                            <option value="kokusan">kokusan</option>
                            <option value="shashiri">shashiri</option>
                            <option value="kokusan_di">kokusan_di</option>
                            <option value="shashiri_di">shashiri_di</option>
                            <option value="kokusan_kaitou">kokusan_kaitou</option>
                            <option value="yonka">yonka</option>
                            <option value="nika">nika</option>
                            <option value="main">main</option>
                        </select>
                        <input v-model.number="activeEditItems[idx].maxBox" type="number" min="1"
                            class="w-14 rounded border border-gray-300 px-1 py-0.5" />
                        <button @click="removeItemRow(idx)" type="button"
                            class="text-red-400 hover:text-red-600 px-1">✕</button>
                    </div>
                    <button @click="addItemRow" type="button"
                        class="text-xs text-orange-600 hover:underline">+ アイテムを追加</button>
                    <p class="text-xs text-gray-400">編集後は自動反映されます。sheetKey=Excelシートの分類、最大部数=箱分割の上限。</p>
                </div>

                <!-- 表示モード（プレビュー） -->
                <div v-if="!showItemEditor && selectedGrade"
                    class="rounded border border-orange-200 bg-white divide-y divide-orange-100">
                    <div v-for="item in labelCountPerItem" :key="item.num"
                        class="flex items-center justify-between px-3 py-1.5 text-xs">
                        <span class="font-medium text-gray-800">{{ item.num }} {{ item.subject }} {{ item.itemLabel }}</span>
                        <span class="flex items-center gap-2">
                            <span class="text-gray-400">最大{{ item.maxBox }}部/箱</span>
                            <span v-if="!excelName" class="text-gray-300">—</span>
                            <span v-else-if="item.count > 0 && item.warn" class="font-semibold text-green-700">{{ item.count }} 教室 <span class="text-yellow-600 text-xs font-normal">{{ item.warn }}</span></span>
                            <span v-else-if="item.count > 0" class="font-semibold text-green-700">{{ item.count }} 教室</span>
                            <span v-else-if="item.warn" class="text-gray-400 text-xs">{{ item.warn }}</span>
                            <span v-else class="text-gray-400 text-xs">0 教室（この学年はスキップ）</span>
                        </span>
                    </div>
                </div>
                <p v-if="!showItemEditor" class="text-xs text-gray-400">0教室のアイテムは出力時にスキップされます</p>
            </div>

            <!-- テスト名 / 実施日 -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">テスト名 <span class="text-gray-400">（ラベルに印字）</span></label>
                    <input v-model="testNameVal" type="text" list="testNameList" placeholder="例: 学習力育成テスト"
                        class="w-full rounded border border-orange-300 bg-white px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-orange-400"
                        :style="excelName && !testNameVal ? 'border-color: #f87171; background-color: #fef2f2;' : ''" />
                    <p v-if="excelName && !testNameVal" class="mt-0.5 text-xs text-red-500">⚠ 自動取得できませんでした。手動で入力してください。</p>
                    <datalist id="testNameList">
                        <option v-for="t in masterTestNames.filter(t => t.is_active)" :key="t.id" :value="t.name" />
                    </datalist>
                </div>
                <!-- 実施日: 複数日付テスト（シート名から検出済み）では非表示 -->
                <div v-if="detectedDates.length === 0">
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        実施日 <span class="text-gray-400">（ラベルに印字・ファイル名のMMDDに使用）</span>
                    </label>
                    <input v-model="testDateVal" type="text" placeholder="例: 2026年3月21日"
                        class="w-full rounded border border-orange-300 bg-white px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-orange-400"
                        :style="excelName && !testDateVal ? 'border-color: #f87171; background-color: #fef2f2;' : ''" />
                    <p v-if="excelName && !testDateVal" class="mt-0.5 text-xs text-red-500">⚠ 自動取得できませんでした。手動入力または学年別設定で指定してください。</p>
                    <p v-else class="mt-0.5 text-xs text-gray-400">Excelヘッダーから自動取得。変更可。</p>
                </div>
                <div v-else>
                    <label class="block text-xs font-medium text-gray-600 mb-1">実施日</label>
                    <p class="text-sm text-gray-600 pt-1.5">{{ detectedDatesDisplay }}
                        <span class="text-xs text-gray-400 ml-1">（シート名から自動検出）</span>
                    </p>
                </div>
            </div>

            <!-- ファイル名略称 -->
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">
                    ファイル名略称 <span class="text-gray-400">（PDF ファイル名に使用）</span>
                    <span class="ml-1 text-orange-600 font-semibold">※必須</span>
                </label>
                <input v-model="shortNameVal" type="text" placeholder="例: 学習力育成テ"
                    class="w-full rounded border border-orange-300 bg-white px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-orange-400"
                    :style="excelName && !shortNameVal ? 'border-color: #f87171; background-color: #fef2f2;' : ''" />
                <p class="mt-0.5 text-xs text-gray-400">
                    出力例:
                    {{ detectedDates.length > 0 ? (detectedDates[0] || '0330') : (dateValToMMDD(testDateVal) || 'MMDD') }}{{ shortNameVal || '略称' }}{{ gradeOptions[0] || '3年' }}①.pdf
                </p>
            </div>

            <!-- プレビュー学年 / プリセット -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">プレビュー学年 <span class="text-gray-400">（出力は全学年）</span></label>
                    <select v-if="gradeOptions.length > 0" v-model="selectedGrade"
                        class="w-full rounded border border-orange-300 bg-white px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-orange-400">
                        <option v-for="g in gradeOptions" :key="g" :value="g">{{ gradeDisplayLabel(g) }} ({{ g }})</option>
                    </select>
                    <input v-else v-model="selectedGrade" type="text" placeholder="例: 5年"
                        class="w-full rounded border border-orange-300 bg-white px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-orange-400" />
                    <p v-if="gradeOptions.length > 1" class="mt-0.5 text-xs text-gray-400">
                        出力時は全学年（{{ gradeOptions.join('・') }}）を生成します
                    </p>
                </div>
                <div v-if="confirmedItems.length === 0 && detectedItems.length === 0">
                    <label class="block text-xs font-medium text-gray-600 mb-1">テスト種別 <span class="text-gray-400">（OCR / Excel未使用の場合のフォールバック）</span></label>
                    <select v-model="selectedPreset"
                        class="w-full rounded border border-orange-300 bg-white px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-orange-400">
                        <option v-for="(preset, key) in PRESETS" :key="key" :value="key">{{ preset.label }}</option>
                    </select>
                </div>
            </div>

            <!-- 検出シート詳細 -->
            <details v-if="sheetLog.length > 0" class="text-xs">
                <summary class="cursor-pointer text-xs font-medium text-gray-500 hover:text-gray-700">
                    検出シート詳細（{{ visibleSheetLog.length }}枚表示 / 合計{{ sheetLog.length }}枚）
                    <span v-if="excludedCodes.size > 0" class="ml-2 text-red-500">TS除外: {{ excludedCodes.size }} 件</span>
                </summary>
                <div class="mt-1 rounded border border-orange-100 bg-white divide-y divide-gray-50">
                    <div v-for="s in visibleSheetLog" :key="s.originalName"
                        class="flex items-start gap-2 px-3 py-1.5"
                        :class="{ 'opacity-50': !s.isFirst && s.key !== 'exclude' && s.key !== 'ichishiki' }">
                        <span class="font-medium text-gray-800 truncate max-w-[200px] flex-shrink-0">{{ s.originalName }}</span>
                        <span class="text-gray-300">→</span>
                        <span class="font-mono px-1.5 py-0.5 rounded flex-shrink-0 text-xs"
                            :class="{
                                'bg-red-100 text-red-700':   s.key === 'exclude',
                                'bg-blue-100 text-blue-700': s.key === 'ichishiki',
                                'bg-green-100 text-green-700': s.isFirst && s.key !== 'exclude' && s.key !== 'ichishiki',
                                'bg-gray-100 text-gray-500':  !s.isFirst,
                            }">{{ s.key }}</span>
                        <span v-if="s.dateCode" class="text-orange-600 text-xs flex-shrink-0">{{ s.dateCode }}</span>
                        <span v-if="s.key === 'exclude'" class="text-red-500">{{ s.schoolCount }}件 除外</span>
                        <span v-else-if="s.key === 'ichishiki'" class="text-blue-500">一式（Phase2）</span>
                        <span v-else-if="!s.isFirst" class="text-gray-400">※同キー衝突・スキップ</span>
                        <span v-else-if="s.schoolCount > 0" class="text-green-600">{{ s.schoolCount }} 教室</span>
                        <span v-else class="text-orange-600">
                            0 教室
                            <template v-if="s.diag">（{{ s.diag.rowsTotal }}行スキャン、開始:row{{ s.diag.dataStart + 1 }}）</template>
                        </span>
                    </div>
                </div>
            </details>

            <!-- 詳細設定 -->
            <details class="text-xs">
                <summary class="cursor-pointer text-xs font-medium text-gray-500 hover:text-gray-700">詳細設定・出力エリア</summary>
                <div class="mt-2 space-y-3">
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="text-xs font-medium text-gray-600">出力エリア</label>
                            <span class="space-x-2">
                                <button @click="selectAllAreas" class="text-orange-600 hover:underline">全選択</button>
                                <button @click="clearAllAreas"  class="text-gray-400 hover:underline">全解除</button>
                            </span>
                        </div>
                        <div class="grid grid-cols-2 gap-x-4 gap-y-1 sm:grid-cols-3 rounded border border-orange-200 bg-white px-3 py-2">
                            <label v-for="area in AREAS" :key="area.key"
                                class="flex items-center gap-1.5 cursor-pointer">
                                <input type="checkbox"
                                    :checked="selectedAreas.includes(area.key)"
                                    @change="toggleArea(area.key)"
                                    class="rounded border-gray-300 text-orange-500" />
                                {{ area.label }}
                            </label>
                        </div>
                    </div>
                </div>
            </details>

            <!-- ボタン -->
            <div class="flex gap-3 pt-2 border-t border-orange-200">
                <button
                    :disabled="!canGenerate"
                    @click="generatePDFs"
                    class="px-6 py-2.5 rounded bg-orange-500 text-white text-sm font-semibold hover:bg-orange-600 disabled:opacity-40 disabled:cursor-not-allowed shadow"
                >
                    宛紙出力
                    <span v-if="detectedDates.length > 0" class="ml-1 text-xs font-normal opacity-80">
                        （{{ detectedDates.length }}日付 × {{ gradeOptions.length }}学年）
                    </span>
                </button>
                <button
                    :disabled="!canGenerate"
                    @click="step = 2"
                    class="px-5 py-2 rounded border border-orange-400 text-orange-700 text-sm hover:bg-orange-100 disabled:opacity-40 disabled:cursor-not-allowed"
                >
                    確認出力（プレビュー）
                </button>
            </div>
            <p v-if="!shortNameVal && excelName" class="text-xs text-orange-600">⚠ ファイル名略称を入力してください（必須）</p>
        </div>

        <!-- ============ Step 2: プレビュー ============ -->
        <div v-if="step === 2" class="rounded bg-white shadow p-5 space-y-4">
            <div class="flex items-center gap-3">
                <button @click="step = 1" class="text-sm text-gray-500 hover:text-gray-700">← 設定に戻る</button>
                <h3 class="font-semibold text-gray-800">確認出力（ラベルプレビュー）</h3>
            </div>
            <p class="text-xs text-gray-500">
                出力: {{ detectedDates.length > 0 ? detectedDates.map(d => { const m=d.match(/^(\d{2})(\d{2})$/); return m?`${parseInt(m[1])}/${parseInt(m[2])}`:d }).join('・') : '—' }}
                × {{ gradeOptions.join('・') }}
                × {{ activeItems.length }}アイテム（0教室はスキップ）
            </p>
            <div class="space-y-2">
                <div v-for="item in labelCountPerItem" :key="item.num"
                    class="flex items-center justify-between rounded border border-gray-200 px-4 py-2 text-sm">
                    <span class="font-medium">{{ item.num }} {{ item.subject }} {{ item.itemLabel }}</span>
                    <span v-if="item.warn" class="text-yellow-600 text-xs">{{ item.warn }}</span>
                    <span v-else-if="item.count > 0" class="text-green-700">{{ selectedGrade }}: {{ item.count }} 教室</span>
                    <span v-else class="text-gray-400 text-xs">{{ selectedGrade }}: スキップ</span>
                </div>
            </div>
            <div class="flex gap-3 pt-2 border-t">
                <button @click="generatePDFs"
                    class="px-5 py-2 rounded bg-orange-500 text-white text-sm font-semibold hover:bg-orange-600">
                    宛紙出力
                </button>
                <button @click="step = 1"
                    class="px-5 py-2 rounded border border-gray-300 text-gray-600 text-sm hover:bg-gray-50">
                    戻る
                </button>
            </div>
        </div>

        <!-- ============ Step 3: 生成中 ============ -->
        <div v-if="step === 3" class="rounded bg-white shadow p-6 space-y-4">
            <h3 class="font-semibold text-gray-800">PDF 生成中...</h3>
            <p class="text-sm text-gray-600">{{ progressMsg }}</p>
            <div class="w-full bg-gray-200 rounded-full h-3">
                <div class="bg-orange-500 h-3 rounded-full transition-all duration-300"
                    :style="{ width: progressPct + '%' }"></div>
            </div>
            <p class="text-xs text-gray-400">ブラウザ内で処理中です。しばらくお待ちください。</p>
        </div>

        <!-- ============ Step 4: 完了 ============ -->
        <div v-if="step === 4" class="rounded bg-white shadow p-6 space-y-4">
            <div class="flex items-center gap-2">
                <span class="text-green-600 text-xl">✔</span>
                <h3 class="font-semibold text-gray-800">生成完了</h3>
            </div>
            <p class="text-sm text-gray-600">{{ progressMsg }}</p>

            <div class="space-y-2">
                <div v-for="f in outputFiles" :key="f.name"
                    class="flex items-center justify-between rounded border border-gray-200 px-4 py-2 text-sm">
                    <span class="text-gray-800 font-medium font-mono text-xs">{{ f.name }}</span>
                    <span class="flex items-center gap-3">
                        <span class="text-gray-400 text-xs">{{ f.count }} 枚</span>
                        <button @click="downloadFile(f)"
                            class="text-xs text-blue-600 hover:underline">DL</button>
                    </span>
                </div>
            </div>

            <div class="flex gap-3 pt-2 border-t">
                <button @click="saveAllFiles"
                    class="px-5 py-2 rounded bg-green-600 text-white text-sm font-semibold hover:bg-green-700">
                    フォルダに一括保存
                </button>
                <button @click="step = 1"
                    class="px-5 py-2 rounded border border-gray-300 text-gray-600 text-sm hover:bg-gray-50">
                    設定に戻る
                </button>
            </div>
            <p class="text-xs text-gray-400">
                「フォルダに一括保存」: ブラウザのフォルダ選択ダイアログが開きます（Chrome/Edge 推奨）。
            </p>
        </div>

        </div><!-- /activeTab === 'tool' -->

        <!-- ============ OCR 確認モーダル ============ -->
        <Teleport to="body">
            <div v-if="showOcrModal"
                class="fixed inset-0 z-50 flex items-start justify-center bg-black/50 p-4 overflow-y-auto"
                @click.self="showOcrModal = false">
                <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl my-8">
                    <!-- ヘッダー -->
                    <div class="flex items-center justify-between px-6 pt-5 pb-3 border-b">
                        <h3 class="text-base font-semibold text-gray-900">アイテムPDF解析結果</h3>
                        <span class="text-xs text-gray-400 truncate max-w-xs">{{ itemPdfName }}</span>
                    </div>

                    <div class="px-6 py-4 space-y-4 max-h-[70vh] overflow-y-auto">
                        <!-- OCR 生テキスト（折りたたみ） -->
                        <details class="text-xs">
                            <summary class="cursor-pointer text-gray-500 hover:text-gray-700">OCR生テキスト（確認用）</summary>
                            <pre class="mt-1 bg-gray-50 border rounded p-2 whitespace-pre-wrap max-h-36 overflow-y-auto text-gray-600">{{ ocrRawText || '（テキストなし）' }}</pre>
                        </details>

                        <!-- テスト一覧 -->
                        <div>
                            <p class="text-xs font-medium text-gray-700 mb-2">検出されたテスト</p>
                            <div v-if="modalTests.length === 0"
                                class="text-xs text-yellow-700 bg-yellow-50 border border-yellow-200 rounded p-2">
                                テストを検出できませんでした。OCR生テキストを確認してください。
                            </div>
                            <div v-for="(test, ti) in modalTests" :key="ti"
                                class="rounded border border-gray-200 bg-gray-50 p-3 space-y-2 mb-2">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-gray-500 w-14 flex-shrink-0">実施日</span>
                                    <input v-model="modalTests[ti].date_raw" type="text" placeholder="3/21"
                                        class="w-24 rounded border border-gray-300 px-1.5 py-0.5 text-xs" />
                                    <span v-if="test.grade_raw" class="text-xs text-gray-500 ml-2">学年</span>
                                    <input v-if="test.grade_raw" v-model="modalTests[ti].grade_raw" type="text"
                                        class="w-20 rounded border border-gray-300 px-1.5 py-0.5 text-xs" />
                                </div>
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs text-gray-500 w-14 flex-shrink-0">テスト名</span>
                                        <input v-model="modalTests[ti].name_raw" type="text"
                                            class="flex-1 rounded border border-gray-300 px-1.5 py-0.5 text-xs" />
                                    </div>
                                    <!-- DB候補ボタン -->
                                    <div v-if="test.matched_test_names && test.matched_test_names.length > 0"
                                        class="flex flex-wrap gap-1 ml-16">
                                        <button v-for="m in test.matched_test_names" :key="m.id"
                                            @click.prevent="selectModalTestName(ti, m.name)"
                                            type="button"
                                            class="px-2 py-0.5 text-xs rounded-full bg-orange-100 text-orange-700 hover:bg-orange-200 border border-orange-200">
                                            {{ m.name }} <span class="text-orange-400 text-xs">{{ m.score }}%</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- アイテム一覧 -->
                        <div>
                            <p class="text-xs font-medium text-gray-700 mb-2">検出されたアイテム</p>
                            <div v-if="modalItems.length === 0"
                                class="text-xs text-yellow-700 bg-yellow-50 border border-yellow-200 rounded p-2 mb-2">
                                アイテムを検出できませんでした。「+ 追加」で手動入力してください。
                            </div>
                            <div v-for="(item, ii) in modalItems" :key="ii"
                                class="flex items-center gap-1.5 text-xs mb-1">
                                <input v-model="modalItems[ii].num" type="text" placeholder="①"
                                    class="w-8 rounded border border-gray-300 px-1 py-0.5 text-center flex-shrink-0" />
                                <input v-model="modalItems[ii].text_raw" type="text" placeholder="国算 解答"
                                    class="flex-1 rounded border border-gray-300 px-1 py-0.5 min-w-0" />
                                <select v-model="modalItems[ii].sheetKey"
                                    class="w-28 rounded border border-gray-300 px-1 py-0.5 text-xs flex-shrink-0">
                                    <option value="kokusan">kokusan</option>
                                    <option value="shashiri">shashiri</option>
                                    <option value="kokusan_di">kokusan_di</option>
                                    <option value="shashiri_di">shashiri_di</option>
                                    <option value="kokusan_kaitou">kokusan_kaitou</option>
                                    <option value="yonka">yonka</option>
                                    <option value="nika">nika</option>
                                    <option value="main">main</option>
                                </select>
                                <input v-model.number="modalItems[ii].max_box" type="number" min="1"
                                    class="w-14 rounded border border-gray-300 px-1 py-0.5 flex-shrink-0" placeholder="100" />
                                <button @click="removeModalItem(ii)" type="button"
                                    class="text-red-400 hover:text-red-600 px-1 flex-shrink-0">✕</button>
                            </div>
                            <button @click="addModalItem" type="button"
                                class="text-xs text-orange-600 hover:underline mt-1">+ アイテムを追加</button>
                            <p class="text-xs text-gray-400 mt-1">sheetKey: Excelシートの分類 / 最大部数: 箱分割の上限（解答100・問題50・DI答案250）</p>
                        </div>

                        <!-- 一式フラグ -->
                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                            <input type="checkbox" v-model="modalIchishiki"
                                class="rounded border-gray-300 text-orange-500" />
                            <span class="text-gray-700">部署分で一式ラベルあり</span>
                        </label>
                    </div>

                    <!-- フッター -->
                    <div class="flex justify-end gap-3 px-6 py-4 border-t bg-gray-50 rounded-b-xl">
                        <button @click="showOcrModal = false; ocrStep = 'idle'; itemPdfName = '';" type="button"
                            class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-100">
                            キャンセル
                        </button>
                        <button @click="confirmOcrResult" type="button"
                            class="px-6 py-2 text-sm font-semibold text-white bg-orange-500 rounded-lg hover:bg-orange-600 shadow">
                            確定してExcelを読み込む
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

    </div>
</template>
