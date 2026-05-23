# REPAIR5 セッション開始プロンプト

このファイルを新しいセッションの最初に Claude に読ませてから作業を依頼してください。

---

## セッション開始時に Claude へ貼るプロンプト

```
z_instructions/REPAIR_MANAGER5.md と z_instructions/REPAIR_PLAN5.md を読んでください。
CLAUDE.md も必ず参照してください。
現在の進捗を確認し、次の推奨作業を提示してください。
```

---

## 設計サマリー（Claude へ）

### プロジェクト概要
- Laravel 11 / Vue 3 / Inertia.js / Vite / Tailwind CSS
- 印刷・組版会社向け社内案件管理サイト
- 本番: さくらレンタルサーバー `https://sun-brain.co.jp/members`

### 今回の修繕範囲
`userwantslist0519.txt`（2026-05-19 のユーザーデバッグ結果）に基づく不具合修正。REPAIR_PLAN4（レスポンシブ対応）は全完了済み。

### 重要な設計方針
- **ジョブ重複問題（R5-13）:** 「マイジョブとして登録」後に元の Coordinator 割当の `is_registered = true` をセットするだけ。大規模設計変更はしない。既存の JobBox / MyJobBox の区別（sender_id = user_id が自己割当）は変えない。
- **CSV文字コード変換（R5-11）:** バックエンド（PHP `mb_convert_encoding`）で変換。フロントエンド不要。
- **モーダルリロード（R5-10）:** 各モーダルの close 処理に `router.reload()` を追加する統一対応。新しい仕組みは作らない。

### 主要ファイル・ルール
- `ProjectJobAssignmentByMyself` = `project_job_assignments` where `sender_id = user_id`（自己割当）
- `supersedes_assignment_id` = マイジョブが置き換えた元のコーディネーター割当を指す FK
- お気に入りルート: 進行表は `progress_sheet_list.favorite`、管理シートは `workflow_sheet_list.favorite` が参考実装
- タイムゾーン: proof イベントは UTC 保存、通常イベントは JST 文字列保存。`CalculatesEventTime` トレイト使用
- `project_jobs.schedule` カラムはさくら本番に存在しない → `Arr::pull($data, 'schedule')` 必須

### 全 ID 一覧
| ID | 内容 | 難易度 |
|----|------|--------|
| R5-01 | 通知時間表記修正 | 極小 |
| R5-02 | 案件お気に入り星 | 小 |
| R5-03 | スケジュールパネルCSVボタン削除 | 極小 |
| R5-04 | ジョブ名全角ハイフン→アンダーバー | 小（要調査） |
| R5-05 | ジョブ編集時の開始時間保持 | 小（要調査） |
| R5-06 | 伝票番号カラム + 表示設定 | 中 |
| R5-07 | スケジュール編集タブ移動 | 小〜中 |
| R5-08 | 進行表ユーザー名表示 | 小〜中（要調査） |
| R5-09 | 完了/未完了フロー | 中（要調査） |
| R5-10 | モーダルリロード統一 | 小〜中（要調査） |
| R5-11 | CSV文字コード自動変換 | 小 |
| R5-12 | テンプレートから作成タブ | 大 |
| R5-13 | ジョブ重複防止 | 小 |
| R5-14 | 日報タイムライン・カレンダー連動 | 中〜大（要調査） |
| R5-15 | Quillエディター箇条書き | 中（要調査） |
| — | OCR精度 | 保留 |
