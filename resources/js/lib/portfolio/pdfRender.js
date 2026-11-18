// PDF をブラウザ（PDF.js）でページ画像（JPEG）にする。
// さくらの Ghostscript が古く、印刷用 PDF（JPEG2000・透明・CID フォント等）を正しく描画できないため、サーバーではなく利用者のブラウザで行う。
// PDF.js は大きいので、PDF を選んだときだけ読み込む（動的 import）。

const DPI = 150; // サーバー変換（PdfRasterizer）と同じ 150dpi 相当
const MAX_EDGE = 2000; // 長辺の上限（px）。サーバー変換と同じ
const MAX_PIXELS = 16 * 1024 * 1024; // iPhone/iPad の canvas 上限（約 1,677 万画素）に収める
const QUALITY = 0.8;
const MAX_BLOB_BYTES = 4 * 1024 * 1024; // さくらの Web 側アップロード上限（5MB）に収める

// CMap（日本語フォントの文字コード表）・標準フォント・JPEG2000 等のデコーダ（wasm）は、必要なものだけ読み込む。
// Vite のビルドで public/build/assets 配下に出力され、/members の基準パスも自動で付く。
const DIR = '/node_modules/pdfjs-dist/';
const binaryUrls = {
    ...import.meta.glob('/node_modules/pdfjs-dist/cmaps/*.bcmap', { query: '?url', import: 'default' }),
    ...import.meta.glob('/node_modules/pdfjs-dist/standard_fonts/*', { query: '?url', import: 'default' }),
    ...import.meta.glob('/node_modules/pdfjs-dist/wasm/*', { query: '?url', import: 'default' }),
};
const KIND_DIR = { cMapUrl: 'cmaps/', standardFontDataUrl: 'standard_fonts/', wasmUrl: 'wasm/' };

/** PDF.js が必要とするバイナリ（CMap・フォント・wasm）を、ビルドで出力したファイルから取得する */
class BinaryDataFactory {
    async fetch({ kind, filename }) {
        const loader = binaryUrls[DIR + (KIND_DIR[kind] ?? '') + filename];
        if (!loader) throw new Error(`${filename} が見つかりません。`);
        const res = await fetch(await loader());
        if (!res.ok) throw new Error(`${filename} を読み込めません（${res.status}）。`);
        return new Uint8Array(await res.arrayBuffer());
    }
}

let pdfjsPromise = null;
function loadPdfjs() {
    if (!pdfjsPromise) {
        pdfjsPromise = (async () => {
            const [pdfjs, worker] = await Promise.all([
                import('pdfjs-dist/legacy/build/pdf.mjs'),
                // ワーカーは Vite の ?worker で .js として出力する（さくらの Apache が .mjs を JavaScript として返さない場合に備える）
                import('pdfjs-dist/legacy/build/pdf.worker.min.mjs?worker'),
            ]);
            pdfjs.GlobalWorkerOptions.workerPort = new worker.default();
            return pdfjs;
        })().catch((e) => {
            pdfjsPromise = null; // 次回やり直せるようにする
            throw e;
        });
    }
    return pdfjsPromise;
}

function toBlob(canvas, quality) {
    return new Promise((resolve, reject) => {
        canvas.toBlob((b) => (b ? resolve(b) : reject(new Error('画像への変換に失敗しました。'))), 'image/jpeg', quality);
    });
}

/**
 * PDF を開く。ページ数と、1 ページずつ画像化する関数を返す。
 * @param {File|Blob} file
 * @returns {Promise<{numPages:number, renderPage:(n:number)=>Promise<Blob>, destroy:()=>void}>}
 */
export async function openPdf(file) {
    const pdfjs = await loadPdfjs();
    const data = new Uint8Array(await file.arrayBuffer());
    const doc = await pdfjs.getDocument({
        data,
        BinaryDataFactory,
        // 取得は上の BinaryDataFactory が行う（値は「指定あり」にするためのダミー）。ワーカー側の取得は使わない
        cMapUrl: 'binary/cmaps/',
        cMapPacked: true,
        standardFontDataUrl: 'binary/standard_fonts/',
        wasmUrl: 'binary/wasm/',
        useWorkerFetch: false,
        isEvalSupported: false,
    }).promise;

    return {
        numPages: doc.numPages,
        /** n ページ目（1 始まり）を JPEG の Blob にする */
        async renderPage(n) {
            const page = await doc.getPage(n);
            try {
                const base = page.getViewport({ scale: 1 });
                let scale = Math.min(DPI / 72, MAX_EDGE / Math.max(base.width, base.height));
                // 長辺が短くても面積が大きいとき（極端な判型）は、画素数の上限に収める
                const pixels = base.width * scale * (base.height * scale);
                if (pixels > MAX_PIXELS) scale *= Math.sqrt(MAX_PIXELS / pixels);
                const vp = page.getViewport({ scale });

                const canvas = document.createElement('canvas');
                canvas.width = Math.max(1, Math.round(vp.width));
                canvas.height = Math.max(1, Math.round(vp.height));
                const ctx = canvas.getContext('2d');
                ctx.fillStyle = '#fff'; // 透過ページを白背景に敷く（JPEG 化のため）
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                await page.render({ canvasContext: ctx, viewport: vp }).promise;

                let blob = await toBlob(canvas, QUALITY);
                // 大きすぎる場合は画質を段階的に下げる（さくらの Web 側アップロード上限対策）
                for (let q = 0.7; blob.size > MAX_BLOB_BYTES && q >= 0.4; q -= 0.1) {
                    blob = await toBlob(canvas, q);
                }
                canvas.width = canvas.height = 0; // メモリを早めに解放する
                return blob;
            } finally {
                page.cleanup();
            }
        },
        destroy() {
            doc.destroy();
        },
    };
}
