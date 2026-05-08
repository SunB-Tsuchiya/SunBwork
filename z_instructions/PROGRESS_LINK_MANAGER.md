# 進行表×カレンダー連携 作業管理書

作成日: 2026-05-06
更新日: 2026-05-06

---

## ■ この管理書の使い方

**ユーザーへ:**
- 各作業の開始時に「P-01 を始めましょう」などと Claude に伝えてください
- Claude は以下の「作業フロー」に従って安全に進めます
- 作業完了ごとに「進捗一覧」を更新します

**Claude へ（セッション開始時に必ず読むこと）:**
1. この管理書（PROGRESS_LINK_MANAGER.md）を読む
2. `z_instructions/PROGRESS_LINK_PLAN.md` を読む（詳細設計・実装コード骨格）
3. 現在の進捗一覧を確認し、ユーザーに次の推奨作業を提示する
4. 以下の「作業フロー」に従って進める

---

## ■ 参照ドキュメント

| ドキュメント | 内容 |
|-------------|------|
| `z_instructions/PROGRESS_LINK_PLAN.md` | 詳細設計書・実装コード骨格・動作確認チェックリスト |
| `CLAUDE.md` | プロジェクト全体のルール（必読） |
| `app/Http/Controllers/Coordinator/ProgressCellController.php` | セル完了フック追加対象 |
| `app/Http/Controllers/User/ProgressCellController.php` | User側セル完了フック追加対象 |
| `app/Http/Controllers/Coordinator/ProgressSheetItemController.php` | 連携設定API |
| `app/Http/Controllers/Coordinator/ProjectSchedulesController.php` | uncomplete修正対象 |
| `app/Services/ProgressLinkService.php` | 新規作成（進捗再計算サービス） |
| `resources/js/Components/ProjectJobItemsTab.vue` | 連携設定UI |
| `resources/js/Components/ProjectCalendar.vue` | カレンダー進捗表示 |

---

## ■ 作業フロー（Claude はこの手順を厳守すること）

```
STEP 1: 計画書を読む
  → PROGRESS_LINK_PLAN.md の該当フェーズを読み、実装内容を把握する
  → 対象ファイルの現状コードを Read して確認する

STEP 2: 設計・方針の提示（ユーザーに確認を取る）
  → 変更ファイル・変更箇所・影響範囲を提示する
  → ユーザーの「OK」を確認してから実装へ

STEP 3: 実装
  → 承認された内容で実装する
  → Vue/JSファイルを変更したら npm run build を実行

STEP 4: 動作確認の依頼
  → 変更内容を簡潔に報告する
  → ユーザーに動作確認をお願いする

STEP 5: 完了記録
  → ユーザーから「OK」が出たら進捗一覧のステータスを「✅ 完了」に更新
  → 次の推奨作業を提示して止まる
```

### ⚠️ 安全ルール（必ず守ること）
- STEP 2 でユーザーの確認なしに実装を始めない
- DB マイグレーション（P-01）は必ず別途確認を取る
- 1 つの作業が完了するまで次の作業に移らない
- `Arr::pull($data, 'schedule')` のような既存の安全ルールを破らない

---

## ■ 進捗一覧

| ID | 内容 | ステータス | 備考 |
|----|------|-----------|------|
| P-01 | DBマイグレーション（`project_job_items.linked_schedule_id` 追加） | ✅ 完了 | migration + ProjectJobItem model 更新 |
| P-02 | `ProgressLinkService.php` 新規作成 | ✅ 完了 | 行/列リンク再計算ロジック実装 |
| P-03 | `Coordinator/ProgressCellController::complete()` にフック追加 | ✅ 完了 | ProgressLinkService::recalculate() 呼び出し |
| P-04 | `User/ProgressCellController::complete()` にフック追加 | ✅ 完了 | 同上 |
| P-05 | `ProjectSchedulesController::uncomplete()` — progress=0 追加 | ✅ 完了 | completed_at=null と同時に progress=0 |
| P-06 | `ProgressSheetItemController` 修正（schedules prop / linked_schedule_id 対応） | ✅ 完了 | API に schedules/allColumns 追加、linked_schedule_id CRUD 対応 |
| P-07 | `ProjectJobItemsTab.vue` UIリニューアル（スケジュールセレクタ） | ✅ 完了 | calendar_linked チェックボックス廃止、スケジュール連携セレクタ追加 |
| P-08 | ビルド・動作確認 | ✅ 完了（ビルドOK） | npm run build 成功。ブラウザ確認待ち |
| P-09 | `ProjectCalendar.vue` progress 表示確認・修正 | 🔲 未着手 | |

---

## ■ ステータス凡例

| 記号 | 意味 |
|------|------|
| 🔲 未着手 | まだ始めていない |
| 🔍 調査中 | コード調査・仕様確認中 |
| 📝 設計中 | 設計・方針をユーザーと確認中 |
| 🔨 実装中 | コード変更・ビルド中 |
| ✅ 完了 | ユーザー確認済み |
| ⏸ 保留 | 依存関係・仕様未定のため一時停止 |

---

## ■ 推奨着手順

| 順序 | ID | 理由 |
|------|----|------|
| 1番目 | P-01 | DB変更が先。他のすべてが依存する |
| 2番目 | P-02 | サービスクラスを先に作り、3〜5で呼び出す |
| 3〜5番目 | P-03〜P-05 | 独立しているので並行可能 |
| 6番目 | P-06 | UI（P-07）の前にAPIを整える |
| 7番目 | P-07 | フロントエンド実装（最も変更量が多い） |
| 8番目 | P-08 | ビルド・確認 |
| 9番目 | P-09 | カレンダー表示の確認（軽微な可能性が高い） |

---

## ■ 作業ログ

| 日付 | ID | 内容 | 対応者 |
|------|----|------|--------|
| 2026-05-06 | — | PROGRESS_LINK_PLAN.md・PROGRESS_LINK_MANAGER.md・PROGRESS_LINK_PROMPT.md 作成 | Claude |
