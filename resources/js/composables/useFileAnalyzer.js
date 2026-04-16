import { ref, computed } from 'vue'
import * as pdfjsLib from 'pdfjs-dist'
import JSZip from 'jszip'

// pdf.js ワーカー設定（Vite/さくら両対応）
pdfjsLib.GlobalWorkerOptions.workerSrc = new URL(
    'pdfjs-dist/build/pdf.worker.mjs',
    import.meta.url
).toString()

// ---- 標準用紙サイズ定義（mm） ----
const PAPER_SIZES = [
    { name: 'A2',      w: 420, h: 594 },
    { name: 'A3',      w: 297, h: 420 },
    { name: 'A4',      w: 210, h: 297 },
    { name: 'A5',      w: 148, h: 210 },
    { name: 'A6',      w: 105, h: 148 },
    { name: 'B3(JIS)', w: 364, h: 515 },
    { name: 'B4(JIS)', w: 257, h: 364 },
    { name: 'B5(JIS)', w: 182, h: 257 },
    { name: 'B6(JIS)', w: 128, h: 182 },
    { name: 'Letter',  w: 216, h: 279 },
    { name: 'Tabloid', w: 279, h: 432 },
]
const TOLERANCE_MM = 3

function matchPaperSize(wMm, hMm) {
    for (const ps of PAPER_SIZES) {
        const wOk = Math.abs(wMm - ps.w) <= TOLERANCE_MM || Math.abs(wMm - ps.h) <= TOLERANCE_MM
        const hOk = Math.abs(hMm - ps.h) <= TOLERANCE_MM || Math.abs(hMm - ps.w) <= TOLERANCE_MM
        if (wOk && hOk) return ps.name
    }
    return `${Math.round(wMm)}×${Math.round(hMm)}mm`
}

function ptToMm(pt) { return pt * 25.4 / 72 }
function twipsToMm(twips) { return twips * 25.4 / 1440 }
export function formatSize(bytes) {
    if (bytes >= 1024 * 1024) return (bytes / 1024 / 1024).toFixed(1) + 'MB'
    if (bytes >= 1024) return (bytes / 1024).toFixed(0) + 'KB'
    return bytes + 'B'
}

// ============================================================
// ファイル種別定義テーブル（1箇所で管理）
//
// columns : 'page'  → ページ数 + ドキュメントサイズ列
//           'image' → 幅×高さ + カラー列
//           'size'  → 容量のみ列（ページ取得不可 → ファイル数カウント）
//           'other' → 列なし（rejected のみ）
// rejected: true  → アップロード不可（除外リストへ）
// ext_label: SUPPORTED_TYPES 表示用の拡張子文字列
// info     : SUPPORTED_TYPES 表示用の説明（null = 非表示）
// order    : grouped 表示順
// ============================================================
const FILE_TYPE_DEFS = {
    // ── ページ系（Adobe / ドキュメント） ──────────────────────
    pdf:         { order:  1, label: 'PDF',                      columns: 'page',  rejected: false, ext_label: '.pdf',                          info: 'ページ数・ドキュメントサイズ' },
    ai:          { order:  2, label: 'Illustrator (AI)',          columns: 'page',  rejected: false, ext_label: '.ai',                           info: 'ページ数・ドキュメントサイズ' },
    indd:        { order:  3, label: 'InDesign (INDD)',           columns: 'page',  rejected: false, ext_label: '.indd',                         info: 'ページ数（CS4以降）/ 不明時は容量' },
    indd_legacy: { order:  4, label: 'InDesign (旧形式)',         columns: 'page',  rejected: false, ext_label: null,                            info: null }, // 内部区別のみ・表示しない
    idml:        { order:  5, label: 'InDesign (IDML)',           columns: 'page',  rejected: false, ext_label: '.idml',                         info: 'ページ数・ドキュメントサイズ' },
    docx:        { order:  6, label: 'Word (DOCX/DOC)',           columns: 'page',  rejected: false, ext_label: '.docx / .doc',                  info: 'ページ数・ドキュメントサイズ' },
    pptx:        { order:  7, label: 'PowerPoint (PPTX/PPT)',     columns: 'page',  rejected: false, ext_label: '.pptx / .ppt',                  info: 'スライド数' },
    eps:         { order:  8, label: 'EPS',                       columns: 'page',  rejected: false, ext_label: '.eps',                          info: 'ドキュメントサイズ（BoundingBox）' },
    // ── 容量のみ（ファイル数カウント） ──────────────────────────
    xlsx:        { order:  9, label: 'Excel (XLSX/XLS)',          columns: 'size',  rejected: false, ext_label: '.xlsx / .xls',                  info: '容量（ファイル数カウント）' },
    svg:         { order: 10, label: 'SVG',                       columns: 'size',  rejected: false, ext_label: '.svg',                          info: '容量（ファイル数カウント）' },
    html:        { order: 11, label: 'HTML',                      columns: 'size',  rejected: false, ext_label: '.html / .htm',                  info: '容量（ファイル数カウント）' },
    font:        { order: 12, label: 'フォント',                   columns: 'size',  rejected: false, ext_label: '.otf / .ttf / .woff / .woff2',  info: '容量（ファイル数カウント）' },
    raw:         { order: 13, label: 'RAWデータ',                  columns: 'size',  rejected: false, ext_label: '.arw / .nef / .cr2 / .dng 等',  info: '容量（ファイル数カウント）' },
    video:       { order: 14, label: '動画',                      columns: 'size',  rejected: false, ext_label: '.mp4 / .mov / .avi 等',          info: '容量（ファイル数カウント）' },
    // ── 画像（幅×高さ） ──────────────────────────────────────
    psd:         { order: 15, label: 'Photoshop (PSD)',           columns: 'image', rejected: false, ext_label: '.psd / .psb',                   info: '幅×高さ・カラーモード' },
    image:       { order: 16, label: '画像',                      columns: 'image', rejected: false, ext_label: '.jpg / .png / .tiff / .gif 等', info: '幅×高さ' },
    // ── コード / テキスト ──────────────────────────────────────
    // Web スタイル: css scss sass less
    // JavaScript 系: js mjs cjs jsx
    // TypeScript 系: ts mts cts tsx
    // フレームワーク: vue svelte astro
    // サーバーサイド: php rb py pyw go rs java cs swift kt
    // システム: c cpp h hpp m mm sh bash zsh fish ps1 bat cmd
    // データ / 設定: json json5 jsonc yaml yml toml ini env conf cfg
    // マークアップ / 文書: xml md mdx rst txt csv
    // DB / インフラ: sql graphql gql dockerfile tf
    code:        { order: 17, label: 'コード / テキスト',          columns: 'size',  rejected: false, ext_label: '.css / .js / .ts / .vue / .php / .py 等', info: '容量（ファイル数カウント）' },
    // ── 対応外（除外） ────────────────────────────────────────
    zip:         { order: 99, label: 'ZIP / その他',              columns: 'other', rejected: true,  ext_label: '.zip / その他',                  info: null, unsupported_reason: 'ファイル情報を読み取れない形式' },
    other:       { order: 99, label: 'その他',                    columns: 'other', rejected: true,  ext_label: null,                            info: null, unsupported_reason: 'ファイル情報を読み取れない形式' },
}

// テーブルから各定数を自動生成
const REJECTED_TYPES = Object.entries(FILE_TYPE_DEFS).filter(([, v]) => v.rejected).map(([k]) => k)

export const SUPPORTED_TYPES = Object.entries(FILE_TYPE_DEFS)
    .filter(([, v]) => !v.rejected && v.ext_label && v.info)
    .sort((a, b) => a[1].order - b[1].order)
    .map(([, v]) => ({ ext: v.ext_label, info: v.info }))

export const UNSUPPORTED_NOTICE = Object.entries(FILE_TYPE_DEFS)
    .filter(([, v]) => v.rejected && v.unsupported_reason && v.ext_label)
    .map(([, v]) => ({ ext: v.ext_label, reason: v.unsupported_reason }))

// ---- ファイル種別判定 ----
async function detectType(file) {
    const ext = file.name.split('.').pop()?.toLowerCase() ?? ''
    try {
        const header = await file.slice(0, 8).arrayBuffer()
        const bytes = new Uint8Array(header)
        const magic4 = String.fromCharCode(bytes[0], bytes[1], bytes[2], bytes[3])
        const magic2 = String.fromCharCode(bytes[0], bytes[1])

        if (magic4 === '%PDF') return 'pdf'
        if (magic4 === '8BPS') return 'psd'
        if (magic4 === 'PK\x03\x04') {
            // ZIP系 → 拡張子で分岐
            if (ext === 'indd') return 'indd'
            if (ext === 'idml') return 'idml'
            if (ext === 'docx' || ext === 'doc') return 'docx'
            if (ext === 'pptx' || ext === 'ppt') return 'pptx'
            if (['xlsx', 'xls', 'xlsm'].includes(ext)) return 'xlsx'
            if (ext === 'ai') return 'ai'
            return 'zip'
        }
        if (magic2 === '%!') return 'eps'
        // 旧式INDD（マジックバイト）
        if (bytes[0] === 0x06 && bytes[1] === 0x06 && bytes[2] === 0xED && bytes[3] === 0xE0) return 'indd_legacy'
        // SVG（テキスト先頭に <svg または <?xml）
        const head = String.fromCharCode(...bytes)
        if (head.startsWith('<?xm') || head.startsWith('<svg')) {
            if (ext === 'svg') return 'svg'
        }
    } catch (_) {}

    // フォールバック：拡張子
    if (ext === 'pdf') return 'pdf'
    if (ext === 'ai') return 'ai'
    if (ext === 'indd') return 'indd_legacy'
    if (ext === 'idml') return 'idml'
    if (ext === 'docx' || ext === 'doc') return 'docx'
    if (ext === 'pptx' || ext === 'ppt') return 'pptx'
    if (['xlsx', 'xls', 'xlsm'].includes(ext)) return 'xlsx'
    if (ext === 'svg') return 'svg'
    if (['html', 'htm'].includes(ext)) return 'html'
    if (['otf', 'ttf', 'woff', 'woff2', 'eot'].includes(ext)) return 'font'
    if (['arw', 'nef', 'cr2', 'cr3', 'dng', 'raf', 'orf', 'rw2', 'raw'].includes(ext)) return 'raw'
    if (['mp4', 'mov', 'avi', 'mkv', 'wmv', 'flv', 'm4v', 'webm', 'mts', 'm2ts'].includes(ext)) return 'video'
    if (['psd', 'psb'].includes(ext)) return 'psd'
    if (['jpg', 'jpeg', 'png', 'gif', 'tiff', 'tif', 'bmp', 'webp', 'ico', 'heic', 'heif'].includes(ext)) return 'image'
    if (ext === 'eps') return 'eps'
    if ([
        // Web スタイル
        'css', 'scss', 'sass', 'less',
        // JavaScript 系
        'js', 'mjs', 'cjs', 'jsx',
        // TypeScript 系
        'ts', 'mts', 'cts', 'tsx',
        // フレームワーク
        'vue', 'svelte', 'astro',
        // サーバーサイド
        'php', 'rb', 'py', 'pyw', 'go', 'rs', 'java', 'cs', 'swift', 'kt', 'kts',
        // システム / スクリプト
        'c', 'cpp', 'cc', 'cxx', 'h', 'hpp', 'hxx', 'm', 'mm',
        'sh', 'bash', 'zsh', 'fish', 'ps1', 'bat', 'cmd',
        // データ / 設定
        'json', 'json5', 'jsonc', 'yaml', 'yml', 'toml', 'ini', 'env', 'conf', 'cfg', 'properties',
        // マークアップ / 文書
        'xml', 'md', 'mdx', 'rst', 'txt', 'csv', 'tsv',
        // DB / インフラ
        'sql', 'graphql', 'gql', 'dockerfile', 'tf', 'tfvars', 'hcl',
        // その他ビルド設定
        'lock', 'gitignore', 'editorconfig', 'prettierrc', 'eslintrc',
    ].includes(ext)) return 'code'
    return 'other'
}

// ---- PDF / AI 解析 ----
async function analyzePdf(file) {
    const buf = await file.arrayBuffer()
    const pdf = await pdfjsLib.getDocument({ data: buf }).promise
    const pageCount = pdf.numPages
    let docSize = null
    if (pageCount > 0) {
        const page = await pdf.getPage(1)
        const vp = page.getViewport({ scale: 1 })
        docSize = matchPaperSize(ptToMm(vp.width), ptToMm(vp.height))
    }
    return { pages: pageCount, doc_size: docSize }
}

// ---- DOCX 解析（JSZip） ----
async function analyzeDocx(file) {
    const buf = await file.arrayBuffer()
    const zip = await JSZip.loadAsync(buf)

    let pages = null
    try {
        const appXml = await zip.file('docProps/app.xml')?.async('string')
        if (appXml) {
            const m = appXml.match(/<Pages>(\d+)<\/Pages>/)
            if (m) pages = parseInt(m[1])
        }
    } catch (_) {}

    let docSize = null
    try {
        const docXml = await zip.file('word/document.xml')?.async('string')
        if (docXml) {
            const m = docXml.match(/<w:pgSz[^>]+w:w="(\d+)"[^>]+w:h="(\d+)"/)
            if (m) {
                docSize = matchPaperSize(twipsToMm(parseInt(m[1])), twipsToMm(parseInt(m[2])))
            }
        }
    } catch (_) {}

    return { pages, doc_size: docSize }
}

// ---- PPTX 解析（JSZip） ----
async function analyzePptx(file) {
    const buf = await file.arrayBuffer()
    const zip = await JSZip.loadAsync(buf)

    // スライド数: ppt/slides/slide*.xml のファイル数
    const slides = Object.keys(zip.files).filter(n => /^ppt\/slides\/slide\d+\.xml$/.test(n))
    const pages = slides.length || null

    // スライドサイズ: ppt/presentation.xml の p:sldSz
    let docSize = null
    try {
        const presXml = await zip.file('ppt/presentation.xml')?.async('string')
        if (presXml) {
            // cx/cy は EMU (English Metric Units): 1mm = 914400/25.4 ≒ 36000 EMU
            const m = presXml.match(/<p:sldSz[^>]+cx="(\d+)"[^>]+cy="(\d+)"/)
            if (m) {
                const wMm = parseInt(m[1]) / 914400 * 25.4
                const hMm = parseInt(m[2]) / 914400 * 25.4
                docSize = matchPaperSize(wMm, hMm)
            }
        }
    } catch (_) {}

    return { pages, doc_size: docSize }
}

// ---- INDD 解析（JSZip、CS4以降） ----
async function analyzeIndd(file) {
    const buf = await file.arrayBuffer()
    const zip = await JSZip.loadAsync(buf)

    let pages = null
    let docSize = null
    try {
        const designMap = await zip.file('designmap.xml')?.async('string')
        if (designMap) {
            const pageMatches = designMap.match(/<Page\s/g)
            if (pageMatches) pages = pageMatches.length

            const wMatch = designMap.match(/PageWidth="([0-9.]+)"/)
            const hMatch = designMap.match(/PageHeight="([0-9.]+)"/)
            if (wMatch && hMatch) {
                docSize = matchPaperSize(ptToMm(parseFloat(wMatch[1])), ptToMm(parseFloat(hMatch[1])))
            }
        }
    } catch (_) {}

    return { pages, doc_size: docSize }
}

// ---- IDML 解析（JSZip） ----
async function analyzeIdml(file) {
    const buf = await file.arrayBuffer()
    const zip = await JSZip.loadAsync(buf)

    let pages = 0
    let docSize = null
    try {
        // Spreads/ フォルダ内の各スプレッドXMLにある <Page 要素を数える
        const spreadKeys = Object.keys(zip.files).filter(n => n.startsWith('Spreads/') && n.endsWith('.xml'))
        for (const key of spreadKeys) {
            const xml = await zip.file(key)?.async('string')
            if (xml) {
                const m = xml.match(/<Page\s/g)
                if (m) pages += m.length
                // 最初のスプレッドからページサイズを取得
                if (!docSize) {
                    // GeometricBounds="y1 x1 y2 x2" (pt)
                    const boundsM = xml.match(/<Page[^>]+GeometricBounds="([^"]+)"/)
                    if (boundsM) {
                        const parts = boundsM[1].split(/\s+/).map(parseFloat)
                        if (parts.length === 4) {
                            const hPt = parts[2] - parts[0]
                            const wPt = parts[3] - parts[1]
                            docSize = matchPaperSize(ptToMm(wPt), ptToMm(hPt))
                        }
                    }
                }
            }
        }
    } catch (_) {}

    return { pages: pages || null, doc_size: docSize }
}

// ---- PSD ヘッダー直読み ----
async function analyzePsd(file) {
    const buf = await file.slice(0, 26).arrayBuffer()
    const view = new DataView(buf)
    const colorModeMap = { 1: 'グレースケール', 3: 'RGB', 4: 'CMYK', 9: 'Lab' }
    const height = view.getUint32(14)
    const width = view.getUint32(18)
    const bitDepth = view.getUint16(22)
    const colorMode = colorModeMap[view.getUint16(24)] ?? `mode${view.getUint16(24)}`
    return { width, height, extra: `${colorMode} ${bitDepth}bit` }
}

// ---- 画像（JPG/PNG等）解析 ----
async function analyzeImage(file) {
    return new Promise((resolve) => {
        const url = URL.createObjectURL(file)
        const img = new Image()
        img.onload = () => {
            resolve({ width: img.naturalWidth, height: img.naturalHeight })
            URL.revokeObjectURL(url)
        }
        img.onerror = () => {
            resolve({ width: null, height: null })
            URL.revokeObjectURL(url)
        }
        img.src = url
    })
}

// ---- EPS 解析 ----
async function analyzeEps(file) {
    const text = await file.slice(0, 1024).text()
    const m = text.match(/%%BoundingBox:\s*([\d.-]+)\s+([\d.-]+)\s+([\d.-]+)\s+([\d.-]+)/)
    if (!m) return { doc_size: null }
    const wPt = parseFloat(m[3]) - parseFloat(m[1])
    const hPt = parseFloat(m[4]) - parseFloat(m[2])
    return { doc_size: matchPaperSize(ptToMm(wPt), ptToMm(hPt)) }
}

// ============================================================
// メインコンポーザブル
// ============================================================
export function useFileAnalyzer() {
    const analyzing = ref(false)
    const results = ref([])        // 対応ファイル
    const rejectedFiles = ref([])  // 対応外ファイル { name, ext, reason }

    // 種別ごとにグループ化
    const grouped = computed(() => {
        const map = {}
        for (const f of results.value) {
            const key = f.type
            const def = FILE_TYPE_DEFS[key]
            if (!map[key]) {
                map[key] = {
                    type: key,
                    label: def?.label ?? key,
                    columns: def?.columns ?? 'other',
                    order: def?.order ?? 99,
                    files: [],
                    totalSize: 0,
                    totalPages: 0,
                }
            }
            map[key].files.push(f)
            map[key].totalSize += f.size
            if (f.pages) map[key].totalPages += f.pages
        }
        return Object.values(map).sort((a, b) => a.order - b.order)
    })

    const summary = computed(() => {
        const totalFiles = results.value.length
        const totalPages = results.value.reduce((s, f) => s + (f.pages || 0), 0)
        const totalBytes = results.value.reduce((s, f) => s + f.size, 0)
        return { totalFiles, totalPages, totalBytes, totalSizeLabel: formatSize(totalBytes) }
    })

    async function analyzeFiles(fileList) {
        if (!fileList || fileList.length === 0) return

        const files = Array.from(fileList)

        if (files.length > 50) {
            alert(`一度に読み込めるファイルは50件までです。${files.length}件選択されています。50件以内に絞ってください。`)
            return
        }

        analyzing.value = true

        // ---- 重複チェック・種別判定・対応外を弾く ----
        const supported = []
        const newRejected = []
        for (const file of files) {
            const ext = file.name.split('.').pop()?.toLowerCase() ?? ''

            // 重複チェック: 名前とサイズが一致するファイルが既に登録済みなら除外
            const isDuplicate = results.value.some(r => r.name === file.name && r.size === file.size)
            if (isDuplicate) {
                newRejected.push({ name: file.name, ext, reason: '名前とサイズが一致したため同一ファイルと判断しました' })
                continue
            }

            const type = await detectType(file)
            if (REJECTED_TYPES.includes(type)) {
                newRejected.push({ name: file.name, ext, reason: '対応外形式' })
            } else {
                supported.push(file)
            }
        }

        rejectedFiles.value.push(...newRejected)

        if (supported.length === 0) {
            analyzing.value = false
            return
        }

        // 各ファイルをプレースホルダーとして追加し、順次解析
        const startIndex = results.value.length
        for (const file of supported) {
            const ext = file.name.split('.').pop()?.toLowerCase() ?? ''
            results.value.push({
                name: file.name,
                ext,
                type: 'other',
                size: file.size,
                pages: null,
                doc_size: null,
                width: null,
                height: null,
                extra: null,
                analyzing: true,
            })
        }

        for (let i = 0; i < supported.length; i++) {
            const file = supported[i]
            const idx = startIndex + i
            try {
                const type = await detectType(file)
                let meta = {}

                // 100MB超はスキップ（容量のみ記録）
                if (file.size <= 100 * 1024 * 1024) {
                    if (type === 'pdf' || type === 'ai') {
                        meta = await analyzePdf(file)
                    } else if (type === 'docx') {
                        meta = await analyzeDocx(file)
                    } else if (type === 'pptx') {
                        meta = await analyzePptx(file)
                    } else if (type === 'indd') {
                        meta = await analyzeIndd(file)
                    } else if (type === 'idml') {
                        meta = await analyzeIdml(file)
                    } else if (type === 'psd') {
                        meta = await analyzePsd(file)
                    } else if (type === 'image') {
                        meta = await analyzeImage(file)
                    } else if (type === 'eps') {
                        meta = await analyzeEps(file)
                    }
                    // xlsx / svg / html / font / raw / video / indd_legacy → meta = {} (容量のみ)
                }

                results.value[idx] = {
                    ...results.value[idx],
                    type,
                    pages: meta.pages ?? null,
                    doc_size: meta.doc_size ?? null,
                    width: meta.width ?? null,
                    height: meta.height ?? null,
                    extra: meta.extra ?? null,
                    analyzing: false,
                }
            } catch (_) {
                results.value[idx] = { ...results.value[idx], type: 'other', analyzing: false }
            }
        }

        analyzing.value = false
    }

    function removeByGroupIndex(type, localIndex) {
        let count = 0
        for (let i = 0; i < results.value.length; i++) {
            if (results.value[i].type === type) {
                if (count === localIndex) {
                    results.value.splice(i, 1)
                    return
                }
                count++
            }
        }
    }

    function clearFiles() {
        results.value = []
        rejectedFiles.value = []
    }

    function clearRejected() {
        rejectedFiles.value = []
    }

    // 保存用JSONオブジェクト生成
    function buildFileInfo() {
        if (results.value.length === 0) return null

        const totalPages = summary.value.totalPages
        const totalBytes = summary.value.totalBytes

        const groups = {}
        for (const g of grouped.value) {
            groups[g.type] = {
                count: g.files.length,
                pages: g.totalPages || null,
                size_bytes: g.totalSize,
                doc_size: g.files[0]?.doc_size ?? null,
            }
        }

        const summaryParts = grouped.value
            .filter(g => g.columns === 'page' && g.files.length > 0)
            .map(g => `${g.label}×${g.files.length}${g.totalPages ? `(${g.totalPages}p)` : ''}`)
        const imageParts = grouped.value
            .filter(g => (g.columns === 'image' || g.columns === 'size') && g.files.length > 0)
            .map(g => `${g.label}×${g.files.length}`)
        const summaryStr = [...summaryParts, ...imageParts].join(' / ')

        return {
            total_files: results.value.length,
            total_pages: totalPages || null,
            total_size_bytes: totalBytes,
            summary: summaryStr,
            groups,
            files: results.value.map(f => ({
                name: f.name,
                ext: f.ext,
                type: f.type,
                size: f.size,
                pages: f.pages,
                doc_size: f.doc_size,
                width: f.width,
                height: f.height,
                extra: f.extra,
            })),
        }
    }

    return {
        analyzing,
        results,
        rejectedFiles,
        grouped,
        summary,
        analyzeFiles,
        removeByGroupIndex,
        clearFiles,
        clearRejected,
        buildFileInfo,
        formatSize,
    }
}
