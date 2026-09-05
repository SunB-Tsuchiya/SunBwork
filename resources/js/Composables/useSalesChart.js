// 売上分析画面の共通chartヘルパー（金額フォーマット・増減率クラス・Chart.jsライフサイクル）。
// REVIEW3 15.1節「Chart.js生成・破棄、金額tooltip、未登録値、負数0基準線を統一するchart helper」対応
// （2026-09-04 Phase 12、月次分析を完成見本として新設）。

export function useSalesChart() {
    const yen = (v) => (v === null || v === undefined ? '—' : `¥${Number(v).toLocaleString()}`);

    const pct = (v) => (v === null || v === undefined ? '比較データなし' : `${v > 0 ? '+' : ''}${v}%`);

    // 日本の慣習（赤字=マイナス）に合わせ、増加=青、減少=赤にする（2026-09-04実機フィードバック対応）
    const pctClass = (v) => {
        if (v === null || v === undefined) return 'text-gray-400';
        return v > 0 ? 'text-blue-600' : v < 0 ? 'text-red-600' : 'text-gray-500';
    };

    // 金額軸の共通tooltip/目盛りフォーマッタ（¥+桁区切り）
    const yenTicks = { callback: (v) => `¥${Number(v).toLocaleString()}` };

    // 負数を含む可能性がある軸に0基準線を明示するための共通オプション
    const divergingScale = (extra = {}) => ({
        beginAtZero: false,
        grid: { color: (ctx) => (ctx.tick.value === 0 ? '#9CA3AF' : '#E5E7EB') },
        ticks: yenTicks,
        ...extra,
    });

    return { yen, pct, pctClass, yenTicks, divergingScale };
}
