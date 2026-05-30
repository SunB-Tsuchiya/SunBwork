# PROOF_UNIFY1_PROMPT.md — 校正ジョブUI統合 セッション開始プロンプト

## タスク概要

「校正ジョブ」タブ（`/user/proof-jobs`）を廃止し、通常の「依頼されたジョブ→マイジョブ」フローに統一する。

詳細設計: `z_instructions/PROOF_UNIFY_PLAN1.md`
進捗管理: `z_instructions/PROOF_UNIFY_MANAGER1.md`

## 主な変更方針

### フロー変更
- 【旧】 校正依頼 → pja100（校正ジョブタブ）＋ pja101（作業スロット、coordinator_assignment_id方式）
- 【新】 校正依頼 → pja100（依頼されたジョブ）→ プロが「マイジョブにする」→ pja101（supersedes_assignment_id方式）
- 校正管理者がスケジュールをセット → pja101を作らず pja100 に直接 Event を作成

### 重要な既存設計
- PCなし校正者の完了: `ProofCoordinator/ProofRequestController::complete()` で校正管理者が代理完了（既存・変更なし）
- 工数計算: `ProgressSheetController` は pja100 直接のEventも集計する（変更不要）
- `proof_assignment_id` = ProgressCell が参照する pja100 の ID（変更なし）

## 既存バグ（Phase 1 で最初に修正）

### バグ1: `ProofRequestController::complete()` と `uncomplete()`
```php
// この条件を削除: 自己proof（coordinator=proofreader）でpja100が完了されない
->whereColumn('sender_id', '!=', 'user_id')
```

### バグ2: `MyProjectJobController::completeAssignment()`
- job_type='proof' の自己割当が完了しても ProofRequest が完了しない
- pja101（supersedes proof pja100）が完了しても ProofRequest が完了しない

### バグ3: `EventController` proof完了フック
- `coordinator_assignment_id` 経由でのみ ProofRequest を特定しているが、
  新フローの `supersedes_assignment_id` 経由にも対応が必要

## 変更ファイル

| # | ファイル | Phase |
|---|---|---|
| 1 | `app/Http/Controllers/ProofCoordinator/ProofRequestController.php` | P1 |
| 2 | `app/Http/Controllers/User/MyProjectJobController.php` | P1, P3 |
| 3 | `app/Http/Controllers/EventController.php` | P1 |
| 4 | `app/Http/Controllers/Concerns/SavesProofWorkSlots.php` | P2 |
| 5 | `app/Http/Controllers/User/ProofJobController.php` | P2 |
| 6 | `resources/js/Components/Tabs/UserNavigationTabs.vue` | P2 |
| 7 | `routes/web.php` | P2 |
| 8 | `resources/js/Pages/MyJobBox/Show.vue` | P3 |
