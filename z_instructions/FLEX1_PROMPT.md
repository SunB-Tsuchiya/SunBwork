# FLEX1_PROMPT.md — 新セッション開始用プロンプト

## このプロンプトの使い方

新しい会話セッションを開始するとき、以下をそのままコピーして送信してください。

---

## ▼ コピー用プロンプト

```
FLEX_PLAN1.md / FLEX_MANAGER1.md の作業を続けます。

## 機能概要
`project_job_assignments` の作業フィールド（作業種別・ステージ・サイズ・数量）を
部署ごとにカスタマイズ可能にする機能を実装しています。

## 設計サマリー
- フィールドスロット4固定: type / stage / size / amounts
- 各スロットは部署ごとに「ラベル名」「有効/無効」「使用するマスタ項目ID配列」を設定可能
- 設定者: Admin（全部署）& Leader（自部署のみ）
- 新規テーブル: `department_field_configs` (department_id, slot, label, enabled, allowed_item_ids JSON)
- 既存DTPデータは変更なし（configレコードなし = 現行動作のまま）
- 数量スロットはオン/オフのみ（数値入力の型は変えない）

## 関連ファイル
- 設計: z_instructions/FLEX_PLAN1.md
- 進捗: z_instructions/FLEX_MANAGER1.md
- 現行フォーム: resources/js/Pages/Coordinator/ProjectJobs/JobAssign/AssignmentForm.vue
- workload設定: app/Http/Controllers/WorkloadSettingController.php
- 現行Department Model: app/Models/Department.php
- Admin Departments: resources/js/Pages/Admin/Departments/Index.vue

## 現在の進捗
FLEX_MANAGER1.md の進捗テーブルを確認してください。

## 今日の作業
FLEX_MANAGER1.md の進捗テーブルで次の「⬜ 未着手」フェーズから継続してください。
作業前に必ず関連ファイルを読み、実装前に方針を確認してから進めてください。
```

---

## 主要ルール（CLAUDE.md より抜粋）

- Artisan は `docker compose exec laravel bash -lc "php artisan ..."` で実行
- Vue/JS 変更後は必ず `npm run build`（プロジェクトルートで実行）
- 新規ページは `z_instructions/CONSOLIDATED_01_layout_and_ui.md` のレイアウトルールに従う
- ルートは `routes/web.php`（api.php は使わない）
- さくら本番ルール: ナビは `route()` 関数を使う（パスのハードコード禁止）

## 既存コード確認ポイント

- `WorkloadSettingController::typeConfig()` — stages/types/sizes/statuses/difficulties のマスタ管理
- `AssignmentForm.vue` L305〜440 付近 — 4スロットの現行実装
- `CompositeJobAssignmentController.php` — アサインメント作成・編集で props を渡す箇所
- `WorkItemType` モデル: `group`, `company_id`, `department_id` あり
- `Size` モデル: `group`, `company_id`, `department_id` あり
- `Stage` モデル: `group`, `company_id` はまだない（Phase 1 で追加）
