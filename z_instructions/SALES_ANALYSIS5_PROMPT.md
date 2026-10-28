# SALES_ANALYSIS5_PROMPT.md — Phase23（経費「部署シート」ビュー）新セッション用

最終更新: 2026-10-28

正本は `SALES_ANALYSIS_PLAN1.md` の Phase23 節と `SALES_ANALYSIS_MANAGER1.md` の Phase23 タスク表。全体の経緯は `SALES_ANALYSIS4_PROMPT.md` を参照。

## 目的
経費Excelの「部署シート」（科目→会社名→4月〜3月→累計、小計・合計）の全体俯瞰を、経費比較ページの新タブ「部署シート」として再現する。

## 確定事項
- 配置: `ExpenseComparison.vue` にタブ切替（科目別比較／部署シート）。基準年度バー・部署タブは共用。
- 支払先はExcel登場順（`MIN(id)`）が既定、金額順に切替可。科目は初期閉じ、全開／全閉ボタン、sticky、ヒートマップ、前年比（支払先単位も、前年に無ければ「新規」）。
- DB変更なし。売上DBは別接続 `sales`（`sunbwork_sales`）。本番の売上DBをClaude/Codexが直接照会しない（経費データ取込はユーザーが画面から行う）。
- スコープ外（後日）: 年度並列表示／4部署比較／Excel出力／「請求書未着（暫定）」月の印。

## 注意
- 作業ツリーの `routes/web.php` などには他機能の未コミット変更が混在している。デプロイは `origin/main` ベースの別worktreeで自分の変更だけを適用する（VITE_APP_BASE_PATH=/members を環境変数で渡してビルド、旧ビルド資産は削除せず残す）。
- routes追加後は Ziggy 再生成（`php artisan ziggy:generate resources/js/ziggy.js`）。

## Phase24（フェーズ2）の状況
設計はPLAN1.md Phase24、進捗はMANAGER1.mdのPhase24表（24-1〜24-6）を参照。確定事項: 経費Excelは「数値が入っていれば確定」（セル色に意味なし）→暫定月の印は不要、最終実績月＝金額が入っている最後の会計月（ファイル全体）。期の途中の年度は同期間（YTD）比較が既定。
