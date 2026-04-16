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
    // 縦横どちらでも一致させる
    for (const ps of PAPER_SIZES) {
        const wOk = Math.abs(wMm - ps.w) <= TOLERANCE_MM || Math.abs(wMm - ps.h) <= TOLERANCE_MM
        const hOk = Math.abs(hMm - ps.h) <= TOLERANCE_MM || Math.abs(hMm - ps.w) <= TOLERANCE_MM
        if (wOk && hOk) return ps.name
    }
    return `${Math.round(wMm)}×${Math.round(hMm)}mm`
}

function ptToMm(pt) { return pt * 25.4 / 72 }
function twipsToMm(twips) { return twips * 25.4 / 1440 }
function formatSize(bytes) {
    if (bytes >= 1024 * 1024) return (bytes / 1024 / 1024).toFixed(1) + 'MB'
    if (bytes >= 1024) return (bytes / 1024).toFixed(0) + 'KB'
    return bytes + 'B'
}

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
            if (ext === 'docx' || ext === 'doc') return 'docx'
            if (ext === 'ai') return 'ai'
            return 'zip'
        }
        if (magic2 === '%!') return 'eps'
        // 旧式INDD（マジックバイト）
        if (bytes[0] === 0x06 && bytes[1] === 0x06 && bytes[2] === 0xED && bytes[3] === 0xE0) return 'indd_legacy'
    } catch (_) {}

    // フォールバック：拡張子
    if (ext === 'pdf') return 'pdf'
    if (ext === 'ai') return 'ai'
    if (ext === 'indd') return 'indd_legacy'
    if (ext === 'docx' || ext === 'doc') return 'docx'
    if (['psd', 'psb'].includes(ext)) return 'psd'
    if (['jpg', 'jpeg', 'png', 'gif', 'tiff', 'tif', 'bmp', 'webp'].includes(ext)) return 'image'
    if (ext === 'eps') return 'eps'
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

    // ページ数: docProps/app.xml の Pages 要素
    let pages = null
    try {
        const appXml = await zip.file('docProps/app.xml')?.async('string')
        if (appXml) {
            const m = appXml.match(/<Pages>(\d+)<\/Pages>/)
            if (m) pages = parseInt(m[1])
        }
    } catch (_) {}

    // ドキュメントサイズ: word/document.xml の w:pgSz
    let docSize = null
    try {
        const docXml = await zip.file('word/document.xml')?.async('string')
        if (docXml) {
            const m = docXml.match(/<w:pgSz[^>]+w:w="(\d+)"[^>]+w:h="(\d+)"/)
            if (m) {
                const wMm = twipsToMm(parseInt(m[1]))
                const hMm = twipsToMm(parseInt(m[2]))
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
            // ページ数: MasterSpread/Spread の Page 要素を数える
            const pageMatches = designMap.match(/<Page\s/g)
            if (pageMatches) pages = pageMatches.length

            // ドキュメントサイズ: DocumentPreferences の PageWidth/PageHeight
            const wMatch = designMap.match(/PageWidth="([0-9.]+)"/)
            const hMatch = designMap.match(/PageHeight="([0-9.]+)"/)
            if (wMatch && hMatch) {
                docSize = matchPaperSize(ptToMm(parseFloat(wMatch[1])), ptToMm(parseFloat(hMatch[1])))
            }
        }
    } catch (_) {}

    return { pages, doc_size: docSize }
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
    return {
        width,
        height,
        extra: `${colorMode} ${bitDepth}bit`,
    }
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

// ---- グループ分け定義 ----
const GROUP_DEFS = {
    pdf:         { label: 'PDF',        columns: 'page' },
    ai:          { label: 'Illustrator (AI)', columns: 'page' },
    indd:        { label: 'InDesign (INDD)',  columns: 'page' },
    indd_legacy: { label: 'InDesign (旧形式)', columns: 'page' },
    docx:        { label: 'Word (DOCX)',      columns: 'page' },
    eps:         { label: 'EPS',              columns: 'page' },
    psd:         { label: 'Photoshop (PSD)',  columns: 'image' },
    image:       { label: '画像',             columns: 'image' },
    other:       { label: 'その他',           columns: 'other' },
}

// ---- 対応外ファイル定義 ----
// これらの種別はページ数・サイズの自動取得が不可能なため弾く
const REJECTED_TYPES = ['indd', 'indd_legacy', 'other', 'zip']

export const UNSUPPORTED_NOTICE = [
    { ext: '.indd',        reason: 'InDesignバイナリ形式のためページ数・サイズ取得不可' },
    { ext: '.zip / その他', reason: 'ファイル情報を読み取れない形式' },
]

export const SUPPORTED_TYPES = [
    { ext: '.pdf',              info: 'ページ数・ドキュメントサイズ' },
    { ext: '.ai',               info: 'ページ数・ドキュメントサイズ' },
    { ext: '.docx',             info: 'ページ数・ドキュメントサイズ' },
    { ext: '.psd / .psb',       info: '幅×高さ・カラーモード' },
    { ext: '.jpg / .png / .tiff / .gif', info: '幅×高さ' },
    { ext: '.eps',              info: 'ドキュメントサイズ（BoundingBox）' },
]

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
            if (!map[key]) {
                map[key] = {
                    type: key,
                    label: GROUP_DEFS[key]?.label ?? key,
                    columns: GROUP_DEFS[key]?.columns ?? 'other',
                    files: [],
                    totalSize: 0,
                    totalPages: 0,
                }
            }
            map[key].files.push(f)
            map[key].totalSize += f.size
            if (f.pages) map[key].totalPages += f.pages
        }
        // 表示順を固定
        const order = ['pdf', 'ai', 'indd', 'indd_legacy', 'docx', 'eps', 'psd', 'image', 'other']
        return order.filter(k => map[k]).map(k => map[k])
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

        // 50件制限（対応ファイルのみカウント。事前に種別判定できないため全件でチェック）
        if (files.length > 50) {
            alert(`一度に読み込めるファイルは50件までです。${files.length}件選択されています。50件以内に絞ってください。`)
            return
        }

        analyzing.value = true

        // ---- 先に種別を判定して対応外を弾く ----
        const supported = []
        const newRejected = []
        for (const file of files) {
            const ext = file.name.split('.').pop()?.toLowerCase() ?? ''
            const type = await detectType(file)
            if (REJECTED_TYPES.includes(type)) {
                const reason = type === 'indd' || type === 'indd_legacy'
                    ? 'INDDバイナリ形式（ページ数・サイズ取得不可）'
                    : '対応外形式'
                newRejected.push({ name: file.name, ext, reason })
            } else {
                supported.push(file)
            }
        }

        // 対応外ファイルをリストに追加
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

                // 100MB超はスキップ
                if (file.size > 100 * 1024 * 1024) {
                    meta = {}
                } else if (type === 'pdf' || type === 'ai') {
                    meta = await analyzePdf(file)
                } else if (type === 'docx') {
                    meta = await analyzeDocx(file)
                } else if (type === 'indd') {
                    meta = await analyzeIndd(file)
                } else if (type === 'psd') {
                    meta = await analyzePsd(file)
                } else if (type === 'image') {
                    meta = await analyzeImage(file)
                } else if (type === 'eps') {
                    meta = await analyzeEps(file)
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

    function removeFile(index) {
        // grouped内のindexではなく results全体のindexで削除
        results.value.splice(index, 1)
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

        // groups集計
        const groups = {}
        for (const g of grouped.value) {
            groups[g.type] = {
                count: g.files.length,
                pages: g.totalPages || null,
                size_bytes: g.totalSize,
                doc_size: g.files[0]?.doc_size ?? null,
            }
        }

        // サマリー文字列
        const summaryParts = grouped.value
            .filter(g => g.columns === 'page' && g.files.length > 0)
            .map(g => `${g.label}×${g.files.length}${g.totalPages ? `(${g.totalPages}p)` : ''}`)
        const imageParts = grouped.value
            .filter(g => g.columns === 'image' && g.files.length > 0)
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
