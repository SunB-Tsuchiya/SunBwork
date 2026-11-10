import { ref } from 'vue';
import axios from 'axios';

// 売上レポートのExcel出力（SUNASALES_PLAN1.md §19）。画面と同じ条件で、サーバーが作ったExcelをダウンロードする。
// 出力できない条件（データが無い・比べられない 等）のときは、サーバーのメッセージを画面に出す（JSONが画面に表示されないように、リンクではなく axios で受け取る）。
export function useReportExport() {
    const exporting = ref(false);
    const exportError = ref('');

    // Content-Disposition の filename*=UTF-8''... からファイル名を取り出す
    const filenameFrom = (disposition) => {
        const m = /filename\*=UTF-8''([^;]+)/i.exec(disposition ?? '');
        if (m) {
            try { return decodeURIComponent(m[1]); } catch { /* 下のフォールバック */ }
        }
        const plain = /filename="?([^";]+)"?/i.exec(disposition ?? '');

        return plain ? plain[1] : '売上レポート.xlsx';
    };

    const exportExcel = async (url, params = {}) => {
        exporting.value = true;
        exportError.value = '';
        try {
            const res = await axios.get(url, { params, responseType: 'blob' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(res.data);
            link.download = filenameFrom(res.headers['content-disposition']);
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(link.href);
        } catch (e) {
            // エラーの本文は Blob で届くので、読み取ってメッセージを取り出す
            let message = 'Excelの出力に失敗しました。';
            try {
                const text = await e.response?.data?.text?.();
                message = (text && JSON.parse(text).message) || message;
            } catch { /* 既定のメッセージ */ }
            exportError.value = message;
        } finally {
            exporting.value = false;
        }
    };

    return { exporting, exportError, exportExcel };
}
