# PROOF_UNIFY_MANAGER1.md — 校正ジョブUI統合 進捗管理

## ステータス凡例
| 記号 | 意味 |
|---|---|
| ⬜ | 未着手 |
| 🔄 | 作業中 |
| ✅ | 完了 |
| ⏸ | 保留 |

---

## Phase 1: 完了同期バグ修正

| # | タスク | 状態 | 備考 |
|---|---|---|---|
| 1-1 | `ProofRequestController::complete()` — sender!=user 条件削除 | ✅ | |
| 1-2 | `ProofRequestController::uncomplete()` — 同上 | ✅ | |
| 1-3 | `MyProjectJobController::completeAssignment()` — ProofRequest完了処理追加 | ✅ | 自己proof / supersedes 両パターン |
| 1-4 | `EventController` — proof完了フックに supersedes_assignment_id パス追加 | ✅ | |
| 1-5 | 動作確認 | ⏸ | 要手動テスト |

## Phase 2: UI統合（校正ジョブタブ廃止）

| # | タスク | 状態 | 備考 |
|---|---|---|---|
| 2-1 | `SavesProofWorkSlots.php` — Eventをpja100直接作成（pja101廃止） | ✅ | |
| 2-2 | `User/ProofJobController.php` — 全アクションをリダイレクト化 | ✅ | |
| 2-3 | `UserNavigationTabs.vue` — 校正ジョブタブ削除 | ✅ | |
| 2-4 | `routes/web.php` — proof-jobsルート削除 | ⏸ | リダイレクト維持のため保留 |
| 2-5 | npm run build | ✅ | |

## Phase 3: 受信ジョブ表示強化

| # | タスク | 状態 | 備考 |
|---|---|---|---|
| 3-1 | `MyProjectJobController::showAssignment()` — proof情報を props追加 | ✅ | supersedes パス対応済み |
| 3-2 | `MyJobBox/Show.vue` — proof型受信ジョブ表示対応 | ✅ | 校正依頼情報カード追加 |
| 3-3 | npm run build | ✅ | |

## 完了後作業

| # | タスク | 状態 |
|---|---|---|
| C-1 | ChangelogSeeder に修正ログ追記 | ⬜ |
| C-2 | CONSOLIDATED_09 を更新 | ⬜ |
| C-3 | PLAN/MANAGER/PROMPT を archived/ へ移動 | ⬜ |

---

## 作業ログ

| 日時 | 内容 |
|---|---|
| 2026-05-30 | 設計確定。PLAN/MANAGER/PROMPT ファイル作成 |
| 2026-05-30 | Phase 1〜3 実装完了。npm run build OK |
