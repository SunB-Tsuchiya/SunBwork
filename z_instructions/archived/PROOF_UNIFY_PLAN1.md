# PROOF_UNIFY_PLAN1.md — 校正ジョブUI統合 設計仕様

## 目的

「校正ジョブ」タブ（`/user/proof-jobs`）を廃止し、通常の「依頼されたジョブ→マイジョブ」フローに統一する。

---

## 現状の問題

```
ProofRequest 44
  ├─ pja100 (277): user=sender=9, job_type=proof  → 校正ジョブタブで表示 / MyJobBoxにも重複出現
  └─ pja101 (278): user=sender=9, coordinator_assignment_id=277 → 作業スロット

【問題1】完了が連動しない
  - proof-jobs/44 で完了 → ProofRequest完了 + pja100未完了（sender=user条件で弾かれる）
  - myjobbox/277 で完了 → pja100完了 + ProofRequest未完了（ロジックなし）

【問題2】スケジュール不整合
  - 校正管理者がスケジュールセット → pja101にEventが作成される
  - MyJobBox (pja100) 側ではセット済みに見えない

【問題3】UX混乱
  - 同一ジョブが「校正ジョブ」「マイジョブ」の2つのUIに存在する
```

---

## 新フロー設計

```
【After】
校正管理者 assignStore:
  → pja100 (sender=coordinator, user=proofreader, job_type='proof') 作成
  → 校正者の「依頼されたジョブ」に表示される

校正管理者がスケジュールをセット（PCなし校正者の代理入力）:
  → Events を pja100 に直接作成（pja101は作らない）
  → 校正者のカレンダーに反映（events.user_id=proofreader_id）

PCあり校正者:
  → 依頼されたジョブで pja100 を確認
  → 「マイジョブにする」→ pja101 (supersedes_assignment_id=pja100.id) 作成（通常フロー）
  → pja101 を完了 → ProofRequest完了 + pja100完了 + 通知

PCなし校正者:
  → 校正管理者が ProofRequestController::complete() で完了
  → ProofRequest完了 + pja100完了 + 通知（既存フロー）
```

---

## フェーズ別実装計画

### Phase 1: 完了同期バグ修正（最優先・既存機能の修正）

#### 1-1. `ProofRequestController::complete()` バグ修正
```php
// 現状（バグ）: sender=user の自己proofでpja100が完了されない
->whereColumn('sender_id', '!=', 'user_id')

// 修正: この条件を削除
```

#### 1-2. `ProofRequestController::uncomplete()` 同様に修正

#### 1-3. `MyProjectJobController::completeAssignment()` に ProofRequest完了処理追加
完了対象が `job_type='proof'` の場合:
- パターンA（自己proof: sender=user）: ProofRequestを直接完了
- パターンB（マイジョブ: supersedes_assignment_id → pja100 が job_type='proof'）:
  - pja100を完了
  - ProofRequestを完了
  - 通知

#### 1-4. `EventController` の proof完了フック更新
現状は `coordinator_assignment_id` 経由でのみProofRequestを完了。
`supersedes_assignment_id` 経由の新フローにも対応。

---

### Phase 2: UI統合（校正ジョブタブ廃止）

#### 2-1. `SavesProofWorkSlots.php` 変更
- pja101を作らず、Eventをpja100に直接作成
- ProofScheduleは引き続き作成
- `event.project_job_assignment_id = pja100.id`（変更点）

#### 2-2. `User/ProofJobController.php` → 廃止
- 全ルートを「依頼されたジョブ」or「マイジョブ」へリダイレクト

#### 2-3. `UserNavigationTabs.vue` — 校正ジョブタブ削除

#### 2-4. `routes/web.php` — proof-jobsルート削除（or 301リダイレクト）

---

### Phase 3: 受信ジョブ表示強化

#### 3-1. 受信ジョブ (JobBox) で proof 型pja100を適切に表示
- 現状: 既にsender≠userならjobboxに出る（通常ケースは既に動作）
- 追加: proof型受信ジョブにバッジ表示・校正依頼情報を添付

#### 3-2. `MyProjectJobController::showAssignment()` — proof型pja100の表示強化
- 校正依頼情報（ProofRequest）を props に追加
- 校正管理者がセットしたイベント（pja100のEvents）を表示

#### 3-3. `MyJobBox/Show.vue` — proof受信ジョブの表示対応
- 「マイジョブにする」ボタンが通常フローで動作（既存機能）
- proof情報バッジを表示

---

## 進行表工数計算への影響

`ProgressSheetController::show()` では proof工数を以下で集計：

```php
// pja100の直接Eventを集計（変更後も動作: $proofAssignmentIds に pja100.id が含まれる）
$total = (int)($rawProofMinutes[$proofId] ?? 0);  // pja100自身のEvents

// supersedes (pja101) のEventを加算（既存ロジック）
foreach ($supersedingProofPjas[$proofId] ?? [] as $wId) {
    $total += (int)($rawProofMinutes[$wId] ?? 0);
}
```

新設計（pja100にEvent直接 + pja101がsupersedesする）で**両方が集計される。変更不要**。

---

## 変更ファイル一覧

| # | ファイル | フェーズ | 変更概要 |
|---|---|---|---|
| 1 | `ProofRequestController.php` | P1 | complete/uncomplete の sender!=user 条件を削除 |
| 2 | `MyProjectJobController.php` | P1 | 完了時にProofRequest完了処理を追加 |
| 3 | `EventController.php` | P1 | proof完了フックに supersedes_assignment_id パスを追加 |
| 4 | `SavesProofWorkSlots.php` | P2 | Eventをpja100に直接作成（pja101作成廃止） |
| 5 | `User/ProofJobController.php` | P2 | 全アクションをリダイレクトに変更 |
| 6 | `UserNavigationTabs.vue` | P2 | 校正ジョブタブ削除 |
| 7 | `routes/web.php` | P2 | proof-jobsルート削除 |
| 8 | `MyProjectJobController.php` | P3 | showAssignment にProofRequest props追加 |
| 9 | `MyJobBox/Show.vue` | P3 | proof型表示対応 |

---

## データ整合性（移行後）

| 項目 | 旧 | 新 |
|---|---|---|
| 校正スロット保存先 | pja101（coordinator_assignment_id=pja100） | pja100直接 |
| 校正者マイジョブ | pja101（coordinator_assignment_id） | pja101（supersedes_assignment_id） |
| 進行表セル紐づけ | proof_assignment_id=pja100 | 変更なし |
| 工数集計 | pja100+pja101のEvents | 変更なし（同ロジックで両対応） |
| 完了トリガー | ProofJobController or ProofRequestController | MyProjectJobController + ProofRequestController |

---

## 既存データの扱い

- 既存の pja101 (coordinator_assignment_id 方式) は削除しない
- Phase1修正後、既存データでも完了同期が動作するようにロジックを書く
- Phase2移行後に作られる新 pja101 は supersedes_assignment_id 方式

---

## さくら本番への影響

- migration 不要（カラム変更なし）
- ルート削除なのでデプロイ時に自動反映
