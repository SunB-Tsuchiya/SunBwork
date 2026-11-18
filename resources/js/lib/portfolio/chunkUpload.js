import axios from 'axios';
import { openPdf } from './pdfRender';

// PDF・動画の分割アップロード。
// さくらの Web 側アップロード上限（5MB）に収まるよう 2MB ずつ送る。
//   PDF : init → chunk × N → complete（PDF 検証・ページ数確定）→ ページ画像化
//         画像化は、まずブラウザ（PDF.js）で行い 1 ページずつ送る（pages/{n} × N → pages-complete）。
//         さくらの Ghostscript が古く印刷用 PDF を正しく描画できないため。ブラウザで失敗したら、従来のサーバー変換（process × N）に切り替える
//   動画: init → chunk × N → video-complete（MP4 検証・ポスター保存）。圧縮は送る前にブラウザで済ませておく
const CHUNK_SIZE = 2 * 1024 * 1024;
const MAX_RETRY = 3;

function sleep(ms) {
    return new Promise((r) => setTimeout(r, ms));
}

/** 作品を作り（init）、ID を返す */
async function initItem(routeName, file, name, meta) {
    const { data } = await axios.post(route(routeName), {
        title: meta.title,
        description: meta.description || null,
        collection_id: meta.collection_id === '' ? null : (meta.collection_id ?? null),
        tags: meta.tags ?? [],
        original_name: name,
        size: file.size,
    });
    return data.id;
}

/** 2MB ずつ送る。順序ずれ（409）はサーバーの受信済み位置から再開し、通信エラーは数回再試行する */
async function sendChunks(id, file, onProgress) {
    let offset = 0;
    let resyncs = 0;
    while (offset < file.size) {
        const blob = file.slice(offset, offset + CHUNK_SIZE);
        const form = new FormData();
        form.append('offset', offset);
        form.append('chunk', blob, 'chunk');

        let sent = false;
        for (let attempt = 1; attempt <= MAX_RETRY && !sent; attempt++) {
            try {
                await axios.post(route('portfolio.api.items.chunk', { item: id }), form);
                sent = true;
            } catch (e) {
                if (e?.response?.status === 409 && typeof e.response.data?.size === 'number' && resyncs < 5) {
                    offset = e.response.data.size;
                    resyncs++;
                    break;
                }
                // 通信エラー等は少し待って再試行。バリデーション（4xx）はやり直しても無駄なので即中断
                if (attempt === MAX_RETRY || (e?.response && e.response.status < 500)) throw e;
                await sleep(800 * attempt);
            }
        }
        if (!sent) continue; // 409 で位置を合わせた場合は同じ位置からやり直す
        offset += blob.size;
        onProgress({ phase: 'upload', ratio: offset / file.size });
    }
}

/** 途中で失敗した作品は残さない（実体ファイルも消える）。消せなくても本人が一覧から削除できる */
async function discard(id) {
    try {
        await axios.delete(route('portfolio.api.items.destroy', { item: id }));
    } catch {
        // 無視
    }
}

/** ブラウザで PDF を 1 ページずつ画像化し、すぐ送る（全ページ分のメモリを抱えない）。完成した作品を返す */
async function convertInBrowser(id, file, total, onProgress) {
    const pdf = await openPdf(file);
    try {
        if (pdf.numPages !== total) throw new Error(`ページ数が一致しません（サーバー ${total} / ブラウザ ${pdf.numPages}）`);
        for (let n = 1; n <= total; n++) {
            const blob = await pdf.renderPage(n);
            await sendPage(id, n, blob);
            onProgress({ phase: 'convert', ratio: n / total, done: n, total });
        }
    } finally {
        pdf.destroy();
    }
    const { data } = await axios.post(route('portfolio.api.pdf.pages_complete', { item: id }));
    return data.item;
}

/** 1 ページ分の JPEG を送る。通信エラーは数回再試行する（同じページ番号は上書きされる） */
async function sendPage(id, n, blob) {
    for (let attempt = 1; ; attempt++) {
        try {
            const form = new FormData();
            form.append('image', blob, `p${n}.jpg`);
            await axios.post(route('portfolio.api.pdf.page', { item: id, page: n }), form);
            return;
        } catch (e) {
            if (attempt >= MAX_RETRY || (e?.response && e.response.status < 500)) throw e;
            await sleep(800 * attempt);
        }
    }
}

/**
 * PDF をアップロードしてページ画像に変換する。
 * @param {File} file PDF ファイル
 * @param {{title:string, description?:string, collection_id?:number|'', tags?:string[]}} meta
 * @param {(p:{phase:'upload'|'convert', ratio:number, done?:number, total?:number})=>void} onProgress
 * @returns 完成した作品（サーバーが返す item）
 */
export async function uploadPdf(file, meta, onProgress = () => {}) {
    const id = await initItem('portfolio.api.pdf.init', file, file.name, meta);
    try {
        await sendChunks(id, file, onProgress);

        const { data: completed } = await axios.post(route('portfolio.api.pdf.complete', { item: id }));
        const total = completed.page_count;
        onProgress({ phase: 'convert', ratio: 0, done: 0, total });

        // ① ブラウザ（PDF.js）でページ画像化して 1 ページずつ送る
        try {
            return await convertInBrowser(id, file, total, onProgress);
        } catch (e) {
            // 容量超過・権限などサーバーが意図して返した拒否（4xx）はそのまま中断する。PDF.js の失敗・通信エラーはサーバー変換に切り替える
            if (e?.response && e.response.status < 500) throw e;
            console.warn('ブラウザでのページ画像化に失敗したため、サーバー変換に切り替えます', e);
        }

        // ② サーバー変換（従来）。ブラウザで送れたページはそのまま使い、残りから続ける。完了するまで呼び続ける
        for (;;) {
            const { data: res } = await axios.post(route('portfolio.api.pdf.process', { item: id }));
            onProgress({ phase: 'convert', ratio: res.processed / res.total, done: res.processed, total: res.total });
            if (res.done) return res.item;
        }
    } catch (e) {
        await discard(id);
        throw e;
    }
}

/**
 * 動画（圧縮済み）をアップロードする。
 * @param {Blob} blob 送る動画本体（MP4）
 * @param {string} name 元のファイル名
 * @param {{title:string, description?:string, collection_id?:number|'', tags?:string[]}} meta
 * @param {{width:number, height:number, duration:number, poster:Blob}} info 寸法・長さ・ポスター画像
 */
export async function uploadVideo(blob, name, meta, info, onProgress = () => {}) {
    const id = await initItem('portfolio.api.video.init', blob, name, meta);
    try {
        await sendChunks(id, blob, onProgress);

        const form = new FormData();
        form.append('poster', info.poster, 'poster.jpg');
        form.append('width', Math.round(info.width));
        form.append('height', Math.round(info.height));
        form.append('duration', info.duration);
        const { data: item } = await axios.post(route('portfolio.api.video.complete', { item: id }), form);
        return item;
    } catch (e) {
        await discard(id);
        throw e;
    }
}
