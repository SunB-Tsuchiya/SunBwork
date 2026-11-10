# SUNASALES1_PROMPT.md — 新セッション用（サンエー印刷 売上レポート）

最終更新: 2026-11-10

## これは何か
サンエー印刷（`company_type='general'`）専用の「売上レポート」（営業部向けの売上分析）。営業部の「得意先別」Excel（得意先×年×月の売上金額・受注件数・担当部門）を取り込み、サン・ブレーンの売上分析と同じ作りの分析画面で見られるようにする。
設計の正本は `SUNASALES_PLAN1.md`、進捗・判断ログは `SUNASALES_MANAGER1.md`。まず両方を読むこと。流用元は `SALES_ANALYSIS_PLAN1.md`（Phase 21〜24 の経費取込・差分・名寄せの仕組みも参考）。

## 設計サマリー
- 1期=1月〜12月（暦年）。期別分析は作らない。部=第一/第二/第三、課=第三部のみ（第一課/第二課/直轄）
- データ: 売上DB（`sales` 接続）の `suna_sl_*`（departments/imports/facts/import_changes/audit_logs）。全テーブル `company_id`＋`ScopedToCompany`（会社未設定は例外）。通常DBに `suna_sl_report_permissions`（閲覧/取込スイッチ）
- 取込: Excelをそのまま（形式 v1 reader）。アップロード→差分プレビュー→確定。**得意先×年の行単位 upsert**（ファイルに無い行は残す。年の入れ替えは明示オプション）。未登録月（空欄）と0を区別
- 権限: Admin エリアの許可設定（ポートフォリオ「利用メンバー設定」と同形＋役職チップ）。既定OFF。SuperAdmin は常に可。ロール行に「営業」（経理の後・ユーザーの前）
- 画面: 独立レイアウト（AppLayout 不使用）。URL `/suna/sales/report/...`、ルート名 `suna.sales.report.*`
- 段階: 1 基盤 → 2 年次/同月/得意先分析 → 3 部門比較/左右比較/月次/名寄せ/Excel出力/注目ポイント

## 絶対ルール
- サン・ブレーンの挙動・`sales_*` テーブル・既存売上分析コードを変えない（general ガードか SunA 配下の新規のみ）
- **AIは売上データを参照しない**。AI関連コードから SunA Sales / `suna_sl_` / `sales` 接続を使わない（自動テストで担保）
- **実データを扱わない**: 開発はダミーデータ。本番の売上レコードを照会しない（構造確認のみ可）。`★…得意先別.xlsx` は `.gitignore` に入れ、内容を読まない／出力に出さない
- 質問は1つずつ。設計→確認→実装。Vue/JS変更後は `npm run build`。デプロイは隔離 worktree＋SSH は事前提示
