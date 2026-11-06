import {
    ALL_FORMATS,
    BlobSource,
    BufferTarget,
    CanvasSink,
    Conversion,
    Input,
    Mp4OutputFormat,
    Output,
    Quality,
    canEncodeVideo,
} from 'mediabunny';

// 動画をアップ前にブラウザ側（WebCodecs）で圧縮し、ポスター画像も作る。
// さくらのサーバーに ffmpeg が無いための設計（PLAN §5.3）。長辺 1280px・H.264 約 2.5Mbps・AAC。
const MAX_EDGE = 1280;
const VIDEO_BITRATE = 2_500_000;
const AUDIO_BITRATE = 128_000;
/** この値以下の H.264 は軽いので、再エンコードせず MP4 へ詰め替えるだけにする（約 4Mbps） */
const REMUX_MAX_BITRATE = 4_000_000;
/** 圧縮できなかった場合に、原本のまま送ってよい上限 */
export const MAX_ORIGINAL_BYTES = 300 * 1024 * 1024;

/** ブラウザが動画の再エンコード（WebCodecs の H.264 エンコーダ）に対応しているか。詰め替えだけなら不要 */
export async function canCompressVideo() {
    try {
        if (typeof VideoEncoder === 'undefined' || typeof VideoDecoder === 'undefined') return false;
        return await canEncodeVideo('avc');
    } catch {
        return false;
    }
}

function canvasToJpeg(canvas) {
    if (canvas.convertToBlob) return canvas.convertToBlob({ type: 'image/jpeg', quality: 0.85 }); // OffscreenCanvas
    return new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.85));
}

/** mediabunny（WebCodecs）で、寸法・長さ・ポスター画像を取り出す */
async function inspectWithMediabunny(blob) {
    const input = new Input({ source: new BlobSource(blob), formats: ALL_FORMATS });
    try {
        const track = await input.getPrimaryVideoTrack();
        if (!track) throw new Error('映像トラックが見つかりません');
        const duration = await input.computeDuration();
        const width = track.displayWidth;
        const height = track.displayHeight;

        // ポスター: 冒頭の暗転を避けて、1 秒目（短い動画は中間）あたりのフレームを使う
        const sizeOpt = MAX_EDGE >= Math.max(width, height) ? {} : (width >= height ? { width: MAX_EDGE } : { height: MAX_EDGE });
        const sink = new CanvasSink(track, sizeOpt);
        const at = Math.min(1, duration / 2);
        // 先頭フレームの時刻が 0 でない動画もあるため、取れなければ先頭から順に試す
        const frame = (await sink.getCanvas(at)) ?? (await sink.getCanvas(await track.getFirstTimestamp()));
        if (!frame) throw new Error('フレームを取り出せません');
        const poster = await canvasToJpeg(frame.canvas);
        if (!poster) throw new Error('ポスター画像を作れません');

        return { width, height, duration, poster };
    } finally {
        input.dispose?.();
    }
}

/** ブラウザ標準の <video> 要素で、寸法・長さ・ポスター画像を取り出す（mediabunny で失敗したときの代替） */
function inspectWithVideoElement(blob) {
    return new Promise((resolve, reject) => {
        const url = URL.createObjectURL(blob);
        const video = document.createElement('video');
        const cleanup = () => {
            clearTimeout(timer);
            video.removeAttribute('src');
            video.load();
            URL.revokeObjectURL(url);
        };
        const fail = (msg) => { cleanup(); reject(new Error(msg)); };
        const timer = setTimeout(() => fail('動画の読み込みがタイムアウトしました'), 20000);

        video.muted = true;
        video.playsInline = true;
        video.preload = 'auto';
        video.onerror = () => fail('このブラウザでは動画を再生できない形式です');
        video.onloadedmetadata = () => {
            // 冒頭の暗転を避けて 1 秒目（短い動画は中間）へシークしてから描画する
            video.currentTime = Math.min(1, (video.duration || 0) / 2);
        };
        video.onseeked = () => {
            try {
                const w = video.videoWidth;
                const h = video.videoHeight;
                const scale = Math.min(1, MAX_EDGE / Math.max(w, h));
                const canvas = document.createElement('canvas');
                canvas.width = Math.round(w * scale);
                canvas.height = Math.round(h * scale);
                canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
                const duration = video.duration;
                canvas.toBlob((poster) => {
                    cleanup();
                    if (!poster) reject(new Error('ポスター画像を作れません'));
                    else resolve({ width: w, height: h, duration, poster });
                }, 'image/jpeg', 0.85);
            } catch (e) {
                fail(e?.message || 'フレームを描画できません');
            }
        };
        video.src = url;
    });
}

/**
 * 動画の寸法・長さ・ポスター画像を取り出す（圧縮の有無にかかわらず使う）。
 * まず mediabunny、だめならブラウザ標準の <video> 要素で試す。両方失敗したら詳しい理由つきで投げる。
 */
export async function inspectVideo(blob) {
    try {
        return await inspectWithMediabunny(blob);
    } catch (e1) {
        console.warn('[portfolio] mediabunny での動画解析に失敗。<video> 要素で再試行します', e1);
        try {
            return await inspectWithVideoElement(blob);
        } catch (e2) {
            console.error('[portfolio] 動画の解析に失敗しました', { mediabunny: e1, videoElement: e2, type: blob.type, size: blob.size });
            throw new Error(`${e1?.message || e1} / ${e2?.message || e2}`);
        }
    }
}

/**
 * 動画を MP4 に変換する（アップ前の軽量化）。
 *   - 軽い H.264（長辺 1280px 以下・4Mbps 以下）: **再エンコードせず MP4 へ詰め替えるだけ**。
 *     画質が落ちず速く、ブラウザのエンコーダにも依存しない（社用 iPhone の短い動画はほぼこちら）。
 *     MOV の moov が末尾にあっても、先頭へ移して（fastStart）すぐ再生できる形にする。
 *   - それ以外（HEVC・高解像度・高ビットレート）: 長辺 1280px・H.264 約 2.5Mbps に再エンコードする。
 * mediabunny は変換できないトラックを黙って捨てるため、出力に映像が残ったかを必ず確認する。
 * 失敗したら例外を投げる（呼び出し側が、原本が小さければそのまま送る）。
 *
 * @param {File} file
 * @param {(ratio:number)=>void} onProgress 0〜1
 * @returns {Promise<{blob: Blob, mode: 'remux'|'transcode'|'original'}>}
 */
export async function compressVideo(file, onProgress = () => {}) {
    const input = new Input({ source: new BlobSource(file), formats: ALL_FORMATS });
    const output = new Output({ format: new Mp4OutputFormat({ fastStart: 'in-memory' }), target: new BufferTarget() });
    try {
        const track = await input.getPrimaryVideoTrack();
        if (!track) throw new Error('映像トラックが見つかりません');

        const w = track.displayWidth;
        const h = track.displayHeight;
        const duration = await input.computeDuration();
        const bitrate = duration > 0 ? (file.size * 8) / duration : Infinity;
        const lightAvc = track.codec === 'avc' && Math.max(w, h) <= MAX_EDGE && bitrate <= REMUX_MAX_BITRATE;

        let video = {}; // 何も指定しない = 可能ならコピー（詰め替え）
        let audio = {};
        if (!lightAvc) {
            // ⚠️ new Quality(数値) は「品質レベル」の指定でビットレートではない。ビットレートは { bitrate } で明示する
            video = { codec: 'avc', quality: new Quality({ bitrate: VIDEO_BITRATE }), frameRate: 30 };
            // 長辺を 1280px に収める（もともと小さければ拡大しない）。縦横比は自動で保つ
            if (Math.max(w, h) > MAX_EDGE) {
                if (w >= h) video.width = MAX_EDGE;
                else video.height = MAX_EDGE;
            }
            audio = { codec: 'aac', quality: new Quality({ bitrate: AUDIO_BITRATE }) };
        }

        const conversion = await Conversion.init({ input, output, video, audio, showWarnings: false });

        // 映像が捨てられていたら失敗扱い（音声だけの MP4 を「成功」にしない）
        const videoKept = conversion.utilizedTracks.some((t) => t.type === 'video');
        if (!videoKept) {
            const reason = conversion.discardedTracks.find((d) => d.track.type === 'video')?.reason ?? 'unknown';
            throw new Error(`映像を変換できません（${reason}）`);
        }

        conversion.onProgress = (p) => onProgress(p);
        await conversion.execute();

        const blob = new Blob([output.target.buffer], { type: 'video/mp4' });
        // 再エンコードしたのに元より大きくなった場合は、元が MP4 ならそれを使う
        if (!lightAvc && file.type === 'video/mp4' && blob.size >= file.size) return { blob: file, mode: 'original' };
        return { blob, mode: lightAvc ? 'remux' : 'transcode' };
    } finally {
        input.dispose?.();
    }
}
