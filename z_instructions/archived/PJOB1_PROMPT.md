# PJOB1_PROMPT.md — 新セッション開始用プロンプト

## 作業概要
coordinator の案件新規作成フォーム改修 + coordinator/prepress の CSV 一括登録整備。

## セッション開始前に必ず読むファイル
1. `z_instructions/PJOB_PLAN1.md` — 設計詳細・変更ファイル一覧
2. `z_instructions/PJOB_MANAGER1.md` — 進捗状況確認

## 主要な変更点サマリー
- `project_jobs` テーブルに `sales_rep`, `sales_rep_id`, `plate_submission_date`, `plate_down_date` を追加
- coordinator Create.vue: 案件タイトル→案件名、ステータス削除、詳細→メモ、担当営業追加、製版入稿日/下版日追加
- coordinator Index.vue: CSV読み込みボタン追加（テンプレートから一括作成と新規作成の間）
- 新規 `ProjectJobCsvController`: coordinator 専用 CSV analyze/import/sample download
- coordinator SalesRepController に apiList 追加
- prepress TicketController に downloadSample 追加
- prepress Index.vue CSV モーダルにサンプルダウンロードボタン追加

## ルート名（coordinator prefix `coordinator.`）
- `coordinator.project_jobs.csv.sample` — サンプル CSV ダウンロード
- `coordinator.project_jobs.csv.analyze` — CSV 解析（POST）
- `coordinator.project_jobs.csv.import` — CSV 一括登録（POST）
- `coordinator.sales_reps.api.list` — 営業担当一覧 JSON

## ルート名（prepress prefix `prepress.`）
- `prepress.tickets.csv.sample` — prepress サンプル CSV ダウンロード

## 重要な制約
- coordinator と prepress の CSV ルートは完全分離
- `NormalizesCsvEncoding` trait で Shift-JIS 対応
- CSV 重複チェック: coordinator は `project_jobs.jobcode`、prepress は `prepress_tickets.jobcode`
- coordinator CSV の user_id はログイン中ユーザーを自動設定
- `project_jobs.schedule` の `Arr::pull` は store() で維持すること
