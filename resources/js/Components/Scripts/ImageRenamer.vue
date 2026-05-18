<script setup>
import { computed, ref } from 'vue';
import Papa from 'papaparse';
import * as XLSX from 'xlsx';
import { usePage } from '@inertiajs/vue3';

defineProps({
    script: { type: Object, required: true },
});

// ===== 定数 =====
const IMAGE_EXTS   = new Set(['.jpg', '.jpeg', '.png', '.tif', '.tiff', '.webp', '.gif', '.bmp']);
const MAX_BYTES    = 240;
const ID_KEYS      = ['id', 'no', '番号', '№'];
const TITLE_KEYS   = ['title', 'タイトル', '名前', 'name', '名称'];

// ===== 状態 =====
const step        = ref(1); // 1:CSV読込 2:フォルダ選択 3:プレビュー 4:進行中 5:完了
const csvRecords  = ref([]); // { id, title }
const csvFileName = ref('');
const dirHandle   = ref(null);
const dirName     = ref('');
const candidates  = ref([]); // { originalName, newName, status, warning, handle }
const results     = ref([]);
const progress    = ref(0);
const errorMsg    = ref('');
const showConfirm = ref(false);
const showGuide   = ref(false);

const page       = usePage();
const authName   = computed(() => page.props.auth?.user?.name ?? '');

// ===== ブラウザ判定 =====
const isSupported = computed(() => typeof window !== 'undefined' && 'showDirectoryPicker' in window);

// ===== ユーティリティ =====
function sanitizeTitle(s) {
    return s
        .normalize('NFC')
        .replace(/[\\/:*?"<>|]/g, '_')
        .replace(/[\r\n\t]/g, '')
        .replace(/_+/g, '_')
        .replace(/^[_\s]+|[_\s]+$/g, '')
        .replace(/\.+$/g, '')
        .trim();
}

function buildNewName(id, title, ext) {
    const safe = sanitizeTitle(title);
    if (!safe) return null;
    const suffix   = ext.toLowerCase();
    const prefix   = `${id}_`;
    const fullName = `${prefix}${safe}${suffix}`;
    const fullBytes = new TextEncoder().encode(fullName).length;
    if (fullBytes <= MAX_BYTES) return fullName;
    // タイトル部分を切り詰め
    const budget = MAX_BYTES - new TextEncoder().encode(prefix + suffix).length;
    const titleBytes = new TextEncoder().encode(safe);
    const truncated  = new TextDecoder().decode(titleBytes.slice(0, Math.max(budget, 1)));
    return `${prefix}${truncated}${suffix}`;
}

function findField(fields, keys) {
    return fields.find(f => keys.some(k => f.trim().toLowerCase() === k.toLowerCase())) ?? null;
}

function findColIndex(headers, keys) {
    for (const k of keys) {
        const i = headers.findIndex(h => h.trim().toLowerCase() === k.toLowerCase());
        if (i !== -1) return i;
    }
    return -1;
}

// ===== CSV / Excel 読込 =====
async function onFileChange(event) {
    errorMsg.value = '';
    const file = event.target.files[0];
    if (!file) return;
    csvFileName.value = file.name;
    csvRecords.value  = [];

    try {
        const ext = file.name.split('.').pop().toLowerCase();
        if (ext === 'xlsx' || ext === 'xls') {
            await parseExcel(file);
        } else {
            await parseCsv(file);
        }
        if (csvRecords.value.length === 0) throw new Error('レコードが見つかりませんでした。');
        step.value = 2;
    } catch (e) {
        errorMsg.value = e.message || 'ファイルの読み込みに失敗しました。';
    }
}

async function parseCsv(file) {
    const buffer = await file.arrayBuffer();
    const bytes  = new Uint8Array(buffer.slice(0, 300));

    // BOM-UTF8 は PapaParse が自動処理。Shift-JIS 判定
    const hasShiftJIS = Array.from(bytes).some(
        b => (b >= 0x81 && b <= 0x9F) || (b >= 0xE0 && b <= 0xEF),
    );
    const encoding = hasShiftJIS ? 'Shift_JIS' : 'UTF-8';
    const text = new TextDecoder(encoding, { ignoreBOM: false }).decode(buffer);

    await new Promise((resolve, reject) => {
        Papa.parse(text, {
            header: true,
            skipEmptyLines: true,
            complete(result) {
                const fields = result.meta.fields ?? [];
                const idField    = findField(fields, ID_KEYS);
                const titleField = findField(fields, TITLE_KEYS);
                if (!idField || !titleField) {
                    reject(new Error(`ID列またはタイトル列が見つかりません。\n検出されたヘッダー: ${fields.join(', ')}`));
                    return;
                }
                csvRecords.value = result.data
                    .filter(row => String(row[idField] ?? '').trim() !== '')
                    .map(row => ({
                        id:    String(row[idField]).trim(),
                        title: String(row[titleField] ?? '').trim(),
                    }));
                resolve();
            },
            error: (err) => reject(new Error(err.message)),
        });
    });
}

async function parseExcel(file) {
    const buffer = await file.arrayBuffer();
    const wb     = XLSX.read(buffer, { type: 'array', cellText: true, cellNF: false });
    const ws     = wb.Sheets[wb.SheetNames[0]];
    const rows   = XLSX.utils.sheet_to_json(ws, { header: 1, raw: false, defval: '' });

    if (rows.length < 2) throw new Error('シートにデータがありません。');
    const headers = rows[0].map(h => String(h).trim());
    const idIdx    = findColIndex(headers, ID_KEYS);
    const titleIdx = findColIndex(headers, TITLE_KEYS);
    if (idIdx === -1 || titleIdx === -1) {
        throw new Error(`ID列またはタイトル列が見つかりません。\n検出されたヘッダー: ${headers.join(', ')}`);
    }
    csvRecords.value = rows.slice(1)
        .filter(row => String(row[idIdx] ?? '').trim() !== '')
        .map(row => ({
            id:    String(row[idIdx]).trim(),
            title: String(row[titleIdx] ?? '').trim(),
        }));
}

// ===== フォルダ選択 =====
async function selectFolder() {
    errorMsg.value = '';
    try {
        const handle = await window.showDirectoryPicker({ mode: 'readwrite' });
        dirHandle.value = handle;
        dirName.value   = handle.name;
        await buildCandidates();
        step.value = 3;
    } catch (e) {
        if (e.name !== 'AbortError') {
            errorMsg.value = 'フォルダへのアクセスが拒否されました。フォルダを選択し直してください。';
        }
    }
}

// ===== 照合・プレビュー生成 =====
async function buildCandidates() {
    const files = [];
    for await (const [name, handle] of dirHandle.value.entries()) {
        if (handle.kind !== 'file') continue;
        if (name.startsWith('.')) continue;
        const dotIdx = name.lastIndexOf('.');
        if (dotIdx === -1) continue;
        const ext  = name.slice(dotIdx).toLowerCase();
        if (!IMAGE_EXTS.has(ext)) continue;
        files.push({ name, handle, ext, baseName: name.slice(0, dotIdx).toLowerCase() });
    }

    // 重複IDチェック
    const idCount = {};
    for (const r of csvRecords.value) {
        const key = r.id.toLowerCase();
        idCount[key] = (idCount[key] || 0) + 1;
    }
    const dupIds = new Set(Object.keys(idCount).filter(k => idCount[k] > 1));

    // CSV をIDマップ化（重複時は最後が勝ち）
    const csvMap = {};
    for (const r of csvRecords.value) {
        csvMap[r.id.toLowerCase()] = r;
    }

    // リネーム後の名前セット（衝突チェック用）
    const existingLower = new Set(files.map(f => f.name.toLowerCase()));

    const list = [];
    for (const f of files) {
        const rec = csvMap[f.baseName];
        if (!rec) {
            list.push({ originalName: f.name, newName: null, status: 'skip', warning: 'CSVにIDが見つかりません', handle: f.handle });
            continue;
        }
        if (dupIds.has(f.baseName)) {
            list.push({ originalName: f.name, newName: null, status: 'warn', warning: `ID「${rec.id}」がCSVに重複しています`, handle: f.handle });
            continue;
        }
        if (!rec.title) {
            list.push({ originalName: f.name, newName: null, status: 'skip', warning: 'タイトルが空欄です', handle: f.handle });
            continue;
        }

        const newName = buildNewName(rec.id, rec.title, f.ext);
        if (!newName) {
            list.push({ originalName: f.name, newName: null, status: 'skip', warning: 'ファイル名を生成できません', handle: f.handle });
            continue;
        }
        // 既リネーム済み
        if (f.name === newName) {
            list.push({ originalName: f.name, newName, status: 'skip', warning: 'すでにリネーム済みです', handle: f.handle });
            continue;
        }
        // 衝突チェック（自分自身を除く）
        if (existingLower.has(newName.toLowerCase()) && f.name.toLowerCase() !== newName.toLowerCase()) {
            list.push({ originalName: f.name, newName, status: 'warn', warning: `同名ファイルが既に存在します`, handle: f.handle });
            continue;
        }

        list.push({ originalName: f.name, newName, status: 'ok', warning: null, handle: f.handle });
    }

    // ソート: ok → warn → skip
    const order = { ok: 0, warn: 1, skip: 2 };
    list.sort((a, b) => order[a.status] - order[b.status]);
    candidates.value = list;
}

// ===== サマリー =====
const previewSummary = computed(() => ({
    ok:   candidates.value.filter(c => c.status === 'ok').length,
    warn: candidates.value.filter(c => c.status === 'warn').length,
    skip: candidates.value.filter(c => c.status === 'skip').length,
}));

const resultSummary = computed(() => ({
    success: results.value.filter(r => r.status === 'success').length,
    error:   results.value.filter(r => r.status === 'error').length,
    skipped: results.value.filter(r => r.status === 'skipped').length,
}));

// ===== 実行 =====
async function executeRename() {
    showConfirm.value = false;
    step.value = 4;
    results.value = [];
    progress.value = 0;
    errorMsg.value = '';

    const targets = candidates.value.filter(c => c.status === 'ok');
    for (let i = 0; i < targets.length; i++) {
        const c = targets[i];
        try {
            await renameFileHandle(c.handle, c.originalName, c.newName);
            results.value.push({ original: c.originalName, renamed: c.newName, status: 'success' });
        } catch (e) {
            results.value.push({ original: c.originalName, renamed: c.newName, status: 'error', detail: e.message });
        }
        progress.value = Math.round(((i + 1) / targets.length) * 100);
    }

    // スキップ / 警告も記録
    for (const c of candidates.value.filter(c => c.status !== 'ok')) {
        results.value.push({ original: c.originalName, renamed: c.newName, status: 'skipped', detail: c.warning });
    }

    step.value = 5;
}

async function renameFileHandle(handle, oldName, newName) {
    if (typeof handle.move === 'function') {
        await handle.move(newName);
    } else {
        // フォールバック: コピー & 削除
        const file      = await handle.getFile();
        const newHandle = await dirHandle.value.getFileHandle(newName, { create: true });
        const writable  = await newHandle.createWritable();
        await writable.write(await file.arrayBuffer());
        await writable.close();
        await dirHandle.value.removeEntry(oldName);
    }
}

// ===== Undo マニフェスト =====
function downloadManifest() {
    const manifest = {
        executed_at: new Date().toISOString(),
        executed_by: authName.value,
        source_file: csvFileName.value,
        folder:      dirName.value,
        summary:     resultSummary.value,
        results:     results.value,
    };
    const blob = new Blob([JSON.stringify(manifest, null, 2)], { type: 'application/json' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = `rename_manifest_${new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19)}.json`;
    a.click();
    URL.revokeObjectURL(url);
}

// ===== サンプルCSVダウンロード =====
function downloadSampleCsv() {
    const rows = [
        'id,title',
        '001,北信越の特急あさま_車窓から',
        '002,飯山線の雪景色',
        '003,長野駅ホーム',
        '004,善光寺参道の朝霧',
        '005,松本城と北アルプス',
    ];
    const bom  = '\uFEFF'; // BOM付きUTF-8（Excelで文字化けしないように）
    const blob = new Blob([bom + rows.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = 'sample_image_rename.csv';
    a.click();
    URL.revokeObjectURL(url);
}

// ===== リセット =====
function reset() {
    step.value        = 1;
    csvRecords.value  = [];
    csvFileName.value = '';
    dirHandle.value   = null;
    dirName.value     = '';
    candidates.value  = [];
    results.value     = [];
    progress.value    = 0;
    errorMsg.value    = '';
    showConfirm.value = false;
}
</script>

<template>
    <!-- ブラウザ非対応 -->
    <div v-if="!isSupported" class="rounded bg-amber-50 border border-amber-300 p-8 shadow text-center">
        <div class="mb-3 text-4xl">⚠️</div>
        <h2 class="mb-2 text-lg font-bold text-amber-800">このツールはChrome / Edgeでのみ動作します</h2>
        <p class="text-sm text-amber-700">
            ローカルフォルダへのアクセスに File System Access API を使用しています。<br>
            Chrome 123 以上または Edge 123 以上でアクセスしてください。
        </p>
    </div>

    <template v-else>
        <!-- ステップインジケーター + ガイドトグル -->
        <div class="mb-4 rounded bg-white p-4 shadow">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <template v-for="(label, i) in ['CSVを読込', 'フォルダを選択', 'プレビュー', '実行', '完了']" :key="i">
                        <div
                            class="flex items-center gap-1 rounded-full px-3 py-1 font-medium"
                            :class="{
                                'bg-indigo-600 text-white': step === i + 1,
                                'bg-green-100 text-green-700': step > i + 1,
                                'bg-gray-100 text-gray-400': step < i + 1,
                            }"
                        >
                            <span v-if="step > i + 1" class="text-xs">✓</span>
                            {{ label }}
                        </div>
                        <span v-if="i < 4" class="text-gray-300">→</span>
                    </template>
                </div>
                <button
                    type="button"
                    class="flex items-center gap-1.5 rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-100"
                    @click="showGuide = !showGuide"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                    </svg>
                    {{ showGuide ? '使い方を閉じる' : '使い方を見る' }}
                </button>
            </div>
        </div>

        <!-- 使い方ガイドパネル -->
        <div v-if="showGuide" class="mb-4 rounded-lg border border-indigo-100 bg-gradient-to-br from-indigo-50 to-blue-50 overflow-hidden shadow-sm">
            <div class="border-b border-indigo-100 px-5 py-3 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                </svg>
                <h3 class="text-sm font-semibold text-indigo-800">使い方ガイド — 画像ファイル一括リネーム</h3>
            </div>
            <div class="grid gap-5 px-5 py-4 md:grid-cols-2">

                <!-- 左カラム -->
                <div class="space-y-4">
                    <!-- このツールでできること -->
                    <div>
                        <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-indigo-600">このツールでできること</p>
                        <p class="text-sm text-gray-700 leading-relaxed">
                            CSVまたはExcelに記載された「ID」と「タイトル」をもとに、<br>
                            ローカルPC上の画像フォルダ内のファイルを一括でリネームします。
                        </p>
                        <div class="mt-2 rounded bg-white/70 px-3 py-2 text-xs font-mono text-gray-600">
                            <p>変換例:</p>
                            <p class="text-red-500 line-through">001.jpg</p>
                            <p class="text-green-600">001_北信越の特急あさま_車窓から.jpg</p>
                        </div>
                    </div>

                    <!-- 必要なもの -->
                    <div>
                        <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-indigo-600">必要なもの</p>
                        <ul class="space-y-1 text-sm text-gray-700">
                            <li class="flex items-start gap-1.5">
                                <span class="mt-0.5 text-indigo-400 font-bold">›</span>
                                <span><strong>Chrome 123 以上</strong>または Edge 123 以上</span>
                            </li>
                            <li class="flex items-start gap-1.5">
                                <span class="mt-0.5 text-indigo-400 font-bold">›</span>
                                <span>ID とタイトルが入った <strong>CSV または Excel</strong> ファイル</span>
                            </li>
                            <li class="flex items-start gap-1.5">
                                <span class="mt-0.5 text-indigo-400 font-bold">›</span>
                                <span>リネーム対象の画像が入った<strong>ローカルフォルダ</strong></span>
                            </li>
                        </ul>
                    </div>

                    <!-- CSVの書き方 -->
                    <div>
                        <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-indigo-600">CSVの書き方（例）</p>
                        <div class="rounded bg-white/70 px-3 py-2 text-xs font-mono text-gray-700 space-y-0.5">
                            <p class="text-gray-400"># ヘッダー行が必須</p>
                            <p>id,title</p>
                            <p>001,北信越の特急あさま_車窓から</p>
                            <p>002,飯山線の雪景色</p>
                            <p>003,長野駅ホーム</p>
                        </div>
                        <ul class="mt-1.5 space-y-0.5 text-xs text-gray-500">
                            <li>ID列: <code class="bg-white/70 px-1 rounded">id / ID / 番号 / no</code></li>
                            <li>タイトル列: <code class="bg-white/70 px-1 rounded">title / タイトル / 名前 / name</code></li>
                            <li>区切り: カンマ（,）またはセミコロン（;）</li>
                            <li>文字コード: UTF-8 / Shift-JIS 自動判定</li>
                        </ul>
                    </div>
                </div>

                <!-- 右カラム -->
                <div class="space-y-4">
                    <!-- 操作手順 -->
                    <div>
                        <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-indigo-600">操作手順</p>
                        <ol class="space-y-2 text-sm text-gray-700">
                            <li class="flex items-start gap-2">
                                <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-[10px] font-bold text-white">1</span>
                                <span>「ファイルを選択」でCSVまたはExcelを読み込む</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-[10px] font-bold text-white">2</span>
                                <span>「フォルダを選択」で画像が入ったフォルダを選ぶ<br><span class="text-xs text-gray-400">※ ブラウザのアクセス許可ダイアログが出ます</span></span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-[10px] font-bold text-white">3</span>
                                <span>プレビューでリネーム内容を確認する</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-[10px] font-bold text-white">4</span>
                                <span>「リネーム実行」ボタンを押す</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-green-600 text-[10px] font-bold text-white">5</span>
                                <span>完了後に<strong>Undoマニフェストをダウンロード</strong>して保管する</span>
                            </li>
                        </ol>
                    </div>

                    <!-- プレビューの見方 -->
                    <div>
                        <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-indigo-600">プレビューの見方</p>
                        <ul class="space-y-1 text-sm text-gray-700">
                            <li class="flex items-center gap-2">
                                <span class="text-green-500 font-bold">✓</span>
                                <span>リネーム実行予定</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="text-amber-500 font-bold">⚠</span>
                                <span>警告あり（重複ID・同名衝突）—スキップされます</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="text-gray-300 font-bold">−</span>
                                <span>スキップ（CSVにIDなし・タイトル空欄・リネーム済み）</span>
                            </li>
                        </ul>
                    </div>

                    <!-- 注意事項 -->
                    <div class="rounded-lg bg-amber-50 border border-amber-200 px-3 py-2.5">
                        <p class="mb-1 text-xs font-semibold text-amber-700">⚠ 注意事項</p>
                        <ul class="space-y-0.5 text-xs text-amber-800">
                            <li>ファイル名は<strong>ローカルPC上で直接変更</strong>されます</li>
                            <li>実行前に必ずバックアップを取ることを推奨します</li>
                            <li>サブフォルダ内のファイルは対象外です</li>
                            <li>Undoマニフェストは自動復元ツールではありません<br>（変更前後の対応表として保管するものです）</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- エラーメッセージ -->
        <div v-if="errorMsg" class="mb-4 rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 whitespace-pre-line">
            {{ errorMsg }}
        </div>

        <!-- STEP 1: CSV / Excel 読込 -->
        <div v-if="step === 1" class="rounded bg-white p-6 shadow">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-base font-semibold text-gray-800">Step 1 — CSVまたはExcelファイルを選択</h3>
                <button
                    type="button"
                    class="flex items-center gap-1.5 rounded-full border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-100"
                    @click="downloadSampleCsv"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    サンプルCSVをダウンロード
                </button>
            </div>

            <div class="mb-4 rounded-lg border-2 border-dashed border-gray-300 p-8 text-center hover:border-indigo-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto mb-3 h-10 w-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m6.75 12l-3-3m0 0l-3 3m3-3v6m-1.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                </svg>
                <p class="mb-3 text-sm text-gray-500">CSVまたはExcel（.xlsx）ファイルをクリックで選択</p>
                <label class="cursor-pointer rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    ファイルを選択
                    <input type="file" accept=".csv,.xlsx,.xls" class="hidden" @change="onFileChange" />
                </label>
            </div>

            <div class="mt-4 rounded-lg bg-gray-50 p-4 text-xs text-gray-500">
                <p class="mb-1 font-medium text-gray-600">ファイル要件</p>
                <ul class="space-y-0.5 list-disc list-inside">
                    <li>ID列: <code>id / ID / 番号 / no</code> のいずれか</li>
                    <li>タイトル列: <code>title / タイトル / 名前 / name</code> のいずれか</li>
                    <li>文字コード: UTF-8 / BOM付きUTF-8 / Shift-JIS（自動判定）</li>
                    <li>区切り文字: カンマ・セミコロン（自動判定）</li>
                </ul>
            </div>
        </div>

        <!-- STEP 2: フォルダ選択 -->
        <div v-if="step === 2" class="rounded bg-white p-6 shadow">
            <h3 class="mb-1 text-base font-semibold text-gray-800">Step 2 — 画像フォルダを選択</h3>
            <p class="mb-4 text-sm text-gray-500">
                読み込んだCSV: <span class="font-medium text-indigo-700">{{ csvFileName }}</span>
                （{{ csvRecords.length }} 件）
            </p>

            <div class="mb-4 rounded-lg border-2 border-dashed border-gray-300 p-8 text-center hover:border-indigo-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto mb-3 h-10 w-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                </svg>
                <p class="mb-3 text-sm text-gray-500">画像ファイルが入っているフォルダを選択してください</p>
                <button
                    class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
                    @click="selectFolder"
                >
                    フォルダを選択
                </button>
            </div>

            <div class="mt-4 rounded-lg bg-gray-50 p-4 text-xs text-gray-500">
                <p class="mb-1 font-medium text-gray-600">対象ファイル</p>
                <p>jpg / jpeg / png / tif / tiff / webp / gif / bmp（サブフォルダは除外）</p>
                <p class="mt-1 text-amber-600">※ ブラウザからフォルダへのアクセス許可が求められます。</p>
            </div>

            <div class="mt-4 text-right">
                <button class="text-sm text-gray-400 hover:text-gray-600" @click="step = 1">← CSVを読み直す</button>
            </div>
        </div>

        <!-- STEP 3: プレビュー -->
        <div v-if="step === 3" class="space-y-4">
            <!-- サマリーバッジ -->
            <div class="rounded bg-white p-5 shadow">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h3 class="text-base font-semibold text-gray-800">Step 3 — リネームプレビュー</h3>
                        <p class="text-sm text-gray-500">フォルダ: <span class="font-medium">{{ dirName }}</span></p>
                    </div>
                    <div class="flex gap-3">
                        <span class="rounded-full bg-green-100 px-3 py-1 text-sm font-semibold text-green-700">
                            実行予定 {{ previewSummary.ok }} 件
                        </span>
                        <span v-if="previewSummary.warn > 0" class="rounded-full bg-amber-100 px-3 py-1 text-sm font-semibold text-amber-700">
                            警告 {{ previewSummary.warn }} 件
                        </span>
                        <span class="rounded-full bg-gray-100 px-3 py-1 text-sm font-semibold text-gray-600">
                            スキップ {{ previewSummary.skip }} 件
                        </span>
                    </div>
                </div>
            </div>

            <!-- プレビューテーブル -->
            <div class="rounded bg-white shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide w-8"></th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">現在のファイル名</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">変更後のファイル名</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">備考</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr
                                v-for="(c, i) in candidates"
                                :key="i"
                                :class="{
                                    'bg-white': c.status === 'ok',
                                    'bg-amber-50': c.status === 'warn',
                                    'bg-gray-50': c.status === 'skip',
                                }"
                            >
                                <td class="px-4 py-2 text-center">
                                    <span v-if="c.status === 'ok'" class="text-green-500 text-base">✓</span>
                                    <span v-else-if="c.status === 'warn'" class="text-amber-500 text-base">⚠</span>
                                    <span v-else class="text-gray-300 text-base">−</span>
                                </td>
                                <td class="px-4 py-2 font-mono text-xs text-gray-700">{{ c.originalName }}</td>
                                <td class="px-4 py-2 font-mono text-xs" :class="c.status === 'ok' ? 'text-indigo-700 font-medium' : 'text-gray-400'">
                                    {{ c.newName ?? '—' }}
                                </td>
                                <td class="px-4 py-2 text-xs text-gray-500">{{ c.warning ?? '' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 実行ボタン -->
            <div class="flex items-center justify-between">
                <button class="text-sm text-gray-400 hover:text-gray-600" @click="step = 2">← フォルダを選び直す</button>
                <button
                    v-if="previewSummary.ok > 0"
                    class="rounded-lg bg-indigo-600 px-6 py-2.5 text-sm font-bold text-white hover:bg-indigo-700"
                    @click="showConfirm = true"
                >
                    {{ previewSummary.ok }} 件をリネーム実行
                </button>
                <span v-else class="text-sm text-gray-400">実行対象のファイルがありません</span>
            </div>
        </div>

        <!-- 確認ダイアログ -->
        <div v-if="showConfirm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div class="mx-4 w-full max-w-md rounded-xl bg-white p-6 shadow-2xl">
                <h3 class="mb-2 text-lg font-bold text-gray-800">実行確認</h3>
                <p class="mb-1 text-sm text-gray-600">
                    <span class="font-semibold text-indigo-700">{{ previewSummary.ok }} 件</span>のファイルをリネームします。
                </p>
                <p class="mb-5 text-xs text-gray-400">
                    実行後は元のファイル名に戻せません。完了後にUndoマニフェスト（JSON）をダウンロードして保管してください。
                </p>
                <div class="flex gap-3 justify-end">
                    <button
                        class="rounded border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                        @click="showConfirm = false"
                    >
                        キャンセル
                    </button>
                    <button
                        class="rounded bg-indigo-600 px-5 py-2 text-sm font-bold text-white hover:bg-indigo-700"
                        @click="executeRename"
                    >
                        実行する
                    </button>
                </div>
            </div>
        </div>

        <!-- STEP 4: 進行中 -->
        <div v-if="step === 4" class="rounded bg-white p-8 shadow text-center">
            <div class="mb-4 text-4xl">⚙️</div>
            <h3 class="mb-4 text-lg font-semibold text-gray-800">リネーム実行中...</h3>
            <div class="mx-auto max-w-md">
                <div class="mb-2 flex justify-between text-sm text-gray-500">
                    <span>進捗</span>
                    <span>{{ progress }}%</span>
                </div>
                <div class="h-3 w-full overflow-hidden rounded-full bg-gray-200">
                    <div
                        class="h-full rounded-full bg-indigo-500 transition-all duration-300"
                        :style="{ width: progress + '%' }"
                    />
                </div>
            </div>
        </div>

        <!-- STEP 5: 完了 -->
        <div v-if="step === 5" class="space-y-4">
            <!-- 結果サマリー -->
            <div class="rounded bg-white p-6 shadow">
                <div class="mb-4 text-center">
                    <div class="mb-2 text-5xl">{{ resultSummary.error === 0 ? '✅' : '⚠️' }}</div>
                    <h3 class="text-xl font-bold text-gray-800">
                        {{ resultSummary.error === 0 ? 'リネーム完了' : 'リネーム完了（エラーあり）' }}
                    </h3>
                </div>
                <div class="flex flex-wrap justify-center gap-4">
                    <div class="rounded-lg bg-green-50 px-6 py-3 text-center">
                        <p class="text-2xl font-bold text-green-600">{{ resultSummary.success }}</p>
                        <p class="text-xs text-green-700">成功</p>
                    </div>
                    <div v-if="resultSummary.error > 0" class="rounded-lg bg-red-50 px-6 py-3 text-center">
                        <p class="text-2xl font-bold text-red-600">{{ resultSummary.error }}</p>
                        <p class="text-xs text-red-700">エラー</p>
                    </div>
                    <div class="rounded-lg bg-gray-50 px-6 py-3 text-center">
                        <p class="text-2xl font-bold text-gray-600">{{ resultSummary.skipped }}</p>
                        <p class="text-xs text-gray-500">スキップ</p>
                    </div>
                </div>
            </div>

            <!-- 結果テーブル -->
            <div class="rounded bg-white shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide w-8"></th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">元ファイル名</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">変更後ファイル名</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">結果</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr
                                v-for="(r, i) in results"
                                :key="i"
                                :class="{
                                    'bg-white': r.status === 'success',
                                    'bg-red-50': r.status === 'error',
                                    'bg-gray-50': r.status === 'skipped',
                                }"
                            >
                                <td class="px-4 py-2 text-center">
                                    <span v-if="r.status === 'success'" class="text-green-500">✓</span>
                                    <span v-else-if="r.status === 'error'" class="text-red-500">✗</span>
                                    <span v-else class="text-gray-300">−</span>
                                </td>
                                <td class="px-4 py-2 font-mono text-xs text-gray-600">{{ r.original }}</td>
                                <td class="px-4 py-2 font-mono text-xs" :class="r.status === 'success' ? 'text-indigo-700' : 'text-gray-400'">
                                    {{ r.renamed ?? '—' }}
                                </td>
                                <td class="px-4 py-2 text-xs" :class="{
                                    'text-green-600': r.status === 'success',
                                    'text-red-600': r.status === 'error',
                                    'text-gray-400': r.status === 'skipped',
                                }">
                                    {{ r.status === 'success' ? '成功' : r.status === 'error' ? `エラー: ${r.detail}` : r.detail }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- アクションボタン -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <button
                    class="rounded border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                    @click="reset"
                >
                    最初からやり直す
                </button>
                <button
                    class="rounded bg-indigo-600 px-5 py-2 text-sm font-bold text-white hover:bg-indigo-700"
                    @click="downloadManifest"
                >
                    Undoマニフェストをダウンロード
                </button>
            </div>
        </div>
    </template>
</template>
