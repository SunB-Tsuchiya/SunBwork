// アップロード前に画像を縮小する（さくらの Web 側アップロード上限が 5MB のため。
// サーバー側でも縮小するので、ここは「送れるサイズにする」ための前処理）。
// 失敗した場合は元ファイルをそのまま返す。
const MAX_EDGE = 2400;

export async function resizeImageForUpload(file) {
    try {
        if (!/^image\/(jpeg|png|webp)$/.test(file.type)) return file;
        const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
        const scale = Math.min(1, MAX_EDGE / Math.max(bitmap.width, bitmap.height));
        // 小さくて軽い画像はそのまま送る
        if (scale === 1 && file.size < 3 * 1024 * 1024) {
            bitmap.close?.();
            return file;
        }
        const w = Math.round(bitmap.width * scale);
        const h = Math.round(bitmap.height * scale);
        const canvas = document.createElement('canvas');
        canvas.width = w;
        canvas.height = h;
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#ffffff'; // 透過 PNG は白背景に
        ctx.fillRect(0, 0, w, h);
        ctx.drawImage(bitmap, 0, 0, w, h);
        bitmap.close?.();
        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.85));
        if (!blob) return file;
        const name = file.name.replace(/\.[^.]+$/, '') + '.jpg';
        return new File([blob], name, { type: 'image/jpeg' });
    } catch {
        return file;
    }
}
