import axios from 'axios';

// PDF・動画の分割アップロード。
// さくらの Web 側アップロード上限（5MB）に収まるよう 2MB ずつ送る。
//   PDF : init → chunk × N → complete（PDF 検証・ページ数確定）→ process × N（ページ画像化）
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

        // ページ画像化（サーバーが数ページずつ処理するので、完了するまで呼び続ける）
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
