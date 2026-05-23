# 組版ジョブ・校正ジョブ連携 + 進行管理表再設計 設計書

> 作成日: 2026-04-18
> ステータス: 設計確定（実装前レビュー待ち）
> 対象ブランチ: main

---

## 1. 背景と目的

印刷・組版会社の社内管理システム SunBWork において、以下の課題を解消する。

- 組版ジョブと校正ジョブの紐づけが ProofRequest 経由の間接参照のみで、逆引きが困難
- 進行管理表のセルとジョブが一方向（セル → ジョブ）にしか紐づかず、ジョブ側からセルを特定できない
- 「校正済み」であることを一目で確認できる仕組みがなく、コンプライアンス上の問題がある
- 進行管理表のテンプレート設計が自由度高すぎて、セルのペア関係が保証されない
- 校正管理者が「どの進行表セルの校正か」を手動で探す必要がある

---

## 2. 現状整理

### 2-1. ジョブ（ProjectJobAssignment）の種類

| 種別 | job_type | sender_id | 説明 |
|---|---|---|---|
| Coordinator依頼ジョブ | NULL | Coordinator | 通常の組版ジョブ依頼 |
| マイジョブ（自己割当） | NULL | = user_id | ユーザーが自分で作成 |
| 校正ジョブ pja100 | 'proof' | ProofCoordinator | 校正管理者が割当 |
| 校正スロット pja101 | 'proof' | = user_id | 校正員が自分のスケジュールとして登録 |

### 2-2. 現在の校正フロー

```
[Operator] マイジョブ詳細 or Coordinator ジョブ詳細
  → 「校正依頼」ボタン
  → ProofRequest 作成（project_job_assignment_id = pja_operator.id）
  → ProofCoordinator の Inbox へ

[ProofCoordinator] Inbox
  → Assign フォーム（AssignmentForm.vue 流用）
  → pja100 作成（job_type='proof', sender_id=ProofCoordinator）
  → ProofRequest.status = 'assigned'

[Proofreader] /user/proof-jobs
  → 作業スロット登録
  → pja101 作成（source_assignment_id=pja100.id, sender_id=user_id）
  → Event 複数件作成

[ProofCoordinator] 完了ボタン
  → ProofRequest.status = 'completed'
  → 依頼者（Operator）に通知
```

### 2-3. 進行管理表の現状構造

- `ProgressSheet`：案件（ProjectJob）に紐づく表。`column_config`（JSON）で列構造を定義
- `ProgressRow`：表の行。`parent_id` で階層化可能
- `ProgressCell`：行 × col_key のセル。`assignment_id` で ProjectJobAssignment を参照
- `column_config` は任意の木構造（ネスト可）。col_key は自由記入またはstagesテーブルから選択

### 2-4. 「進行表からジョブ作成」の既存実装

`ProjectJobAssignmentsController::store` で `_progress_sheet_id` / `_row_id` / `_col_key` を受け取り、ジョブ作成後に `ProgressCell.assignment_id` を更新する仕組みが実装済み。ただし **ジョブ側に progress_cell_id は存在しない**（逆引き不可）。

---

## 3. 課題定義

| # | 課題 | 影響 |
|---|---|---|
| C-1 | pja100 → pja_operator への直接 FK がない | 校正管理者が元ジョブを即座に参照できない |
| C-2 | ProgressCell.assignment_id が単方向のみ | ジョブ側からセル座標を特定できない |
| C-3 | 校正ジョブと進行表セルが無関係 | 「どの行の校正か」が構造的に追えない |
| C-4 | 「校正済み」フラグがない | コンプライアンス証跡として機能しない |
| C-5 | 進行表の列ペア（組版+校正）が保証されない | テンプレート設計者の任意に依存 |
| C-6 | 進行管理表が複雑で作成が難しい | ユーザーが誤った構造で作ってしまう |
| C-7 | ProofRequest.deadline が assign フォームの prefill に使われていない | pja_operator の締切が校正締切として誤って設定される |
| C-8 | 進行表の校正セルから校正管理経由の依頼が送れない | 進行表からの校正フローに断絶がある |

---

## 4. 目標と非目標

### 目標

- 組版ジョブ ↔ 校正ジョブの双方向紐づけを実現する
- 進行管理表のセル ↔ ジョブの双方向紐づけを実現する
- 「校正済みマーク」をジョブに付与し、一覧から一目で確認できるようにする
- 進行管理表を「セット方式」で簡単に作れる新設計に移行する（旧設計は並走）
- 進行表の校正セルから校正管理への依頼送信を可能にする

### 非目標

- 再校・三校の連鎖管理（再校は新規ジョブとして作成する運用で対応）
- 旧進行表データの新設計への自動移行（別途検討）
- 校正ジョブの添付ファイル管理
- 監査ログの詳細記録

---

## 5. 用語定義

| 用語 | 定義 |
|---|---|
| pja_operator | オペレーターの組版ジョブ（ProjectJobAssignment。job_type=NULLまたはCoordinator依頼） |
| pja100 | 校正管理者が作成した校正割当ジョブ（job_type='proof', sender_id=ProofCoordinator） |
| pja101 | 校正員が自分のスロットとして作成した自己割当（source_assignment_id=pja100.id） |
| 組版セット | 進行表上の「組版担当セル + 組版登録セル」のペア |
| 校正セット | 進行表上の「校正担当セル + 校正登録セル」のペア |
| 校正ラウンド | 初校・再校・三校などの1回分の校正作業単位 |
| 校正済みマーク | pja_operator に付く `proof_completed_at` タイムスタンプ |
| 新進行表 | セット方式で作成する新設計の進行表（旧進行表と並走） |
| 旧進行表 | 現在稼働中の自由構成の進行表 |

---

## 6. ドメインモデル案（変更後）

```
ProjectJob
  └─ ProjectJobAssignment (pja_operator)
       ├─ proof_completed_at: timestamp|null   ← NEW: 校正済みマーク
       ├─ progress_cell_id: FK|null            ← NEW: 逆引き用
       │
       ├─ ProofRequest (project_job_assignment_id)
       │    ├─ deadline (= 校正締切。組版締切とは別)
       │    └─ pja100 (ProjectJobAssignment, job_type='proof')
       │         ├─ progress_cell_id → 校正セル  ← NEW
       │         └─ pja101 (source_assignment_id)
       │
       └─ ProgressCell (assignment_id)
            └─ ProgressRow
                 └─ ProgressSheet → ProjectJob

ProgressSheet (新設計 v2)
  └─ ProgressRowV2
       └─ ProgressCellV2
            ├─ assignment_id      (組版ジョブ)
            ├─ proof_assignment_id (校正ジョブ)   ← NEW
            └─ cell_type: enum
```

---

## 7. エンティティ関係（変更点のみ）

### `project_job_assignments` への追加カラム

```sql
ALTER TABLE project_job_assignments
  ADD COLUMN proof_completed_at TIMESTAMP NULL AFTER completed,
  ADD COLUMN progress_cell_id   BIGINT UNSIGNED NULL AFTER proof_completed_at,
  ADD FOREIGN KEY (progress_cell_id) REFERENCES progress_cells(id) ON DELETE SET NULL;
```

### `progress_cells` への追加カラム

```sql
ALTER TABLE progress_cells
  ADD COLUMN proof_assignment_id BIGINT UNSIGNED NULL AFTER assignment_id,
  ADD COLUMN cell_type VARCHAR(32) NULL AFTER proof_assignment_id,
  ADD FOREIGN KEY (proof_assignment_id) REFERENCES project_job_assignments(id) ON DELETE SET NULL;
```

`cell_type` の値：

| 値 | 意味 |
|---|---|
| `typesetting_tanto` | 組版担当者セレクト |
| `typesetting_register` | 組版登録ボタン |
| `proof_tanto` | 校正担当者セレクト（校正管理 or 直接ユーザー） |
| `proof_register` | 校正登録ボタン |
| NULL | 旧設計のセル（cell_type なし） |

---

## 8. ジョブのカプセル化方針

校正管理者が `assignPage` でフォームを開く際に、元ジョブから以下の情報を **自動プリフィル** する（現在の実装を修正・補完）：

| 項目 | 引き継ぎ元 | 備考 |
|---|---|---|
| タイトル | `pja_operator.title + "_校正"` | 実装済み ✅ |
| 作業種別・サイズ・難易度 | pja_operator から | 実装済み ✅ |
| detail（作業指示） | pja_operator から | 実装済み ✅ |
| amounts / amounts_unit | pja_operator から | 実装済み ✅ |
| **desired_end_date** | **`ProofRequest.deadline`** | **⚠️ バグ修正必要（現在は pja_operator.desired_end_date を使用）** |
| desired_time | null（引き継がない） | 実装済み ✅ |
| user_id | null（校正管理者が設定） | 実装済み ✅ |
| progress_cell_id | ProofRequest の発信元セル | **NEW: 自動紐づけ** |

### バグ修正箇所

`ProofRequestController::assignPage` L112-114:

```php
// 変更前（バグ）
'desired_end_date' => $src?->desired_end_date
    ? (is_string($src->desired_end_date) ? $src->desired_end_date : $src->desired_end_date->format('Y-m-d'))
    : null,

// 変更後（正）
'desired_end_date' => $proofRequest->deadline
    ? (is_string($proofRequest->deadline) ? substr($proofRequest->deadline, 0, 10) : $proofRequest->deadline->format('Y-m-d'))
    : null,
```

---

## 9. 組版ジョブ → 校正ジョブ受け渡しフロー（設計後）

### 経路A：マイジョブ詳細 / Coordinatorジョブ詳細から

```
1. オペレーターが「校正依頼」ボタンをクリック
2. モーダル（タイトル・校正締切・メモ）
3. ProofRequest 作成
   - project_job_assignment_id = pja_operator.id
   - deadline = オペレーターが設定（組版締切より前）
   - note = メモ
4. ProofCoordinator の Inbox に届く
5. 割当フォームで校正員を選択・プリフィル確認
6. pja100 作成（proof_completed_at=null, progress_cell_id=元セルから自動設定）
7. ProofRequest.status = 'assigned'
```

### 経路B：進行表の校正セルから「校正管理」を選択

```
1. 進行表の校正セル担当者セレクトで「校正管理」を選択
2. 校正依頼モーダルが開く（経路Aと同じUI）
   - row_id / col_key（校正セルの座標）が hidden で付随
3. ProofRequest 作成（上記 + progress_cell_id の紐づけ情報を含む）
4. 以降は経路Aと同じ
5. pja100 作成時に自動的に校正セルの assignment_id を pja100.id で更新
   + pja_operator の progress_cell_id も組版セルに設定
```

### 経路C：進行表の校正セルに直接ユーザーを割当

```
1. 進行表の校正セル担当者セレクトで通常ユーザーを選択
2. 直接割当（ProofRequestは作成しない）
   - ProgressCell.proof_assignment_id に自己割当ジョブを作成して紐づけ
   - cell_type='proof_register' のセルと紐づいている組版セルを特定
3. 直接割当ジョブが completed = true になったとき
   → pja_operator.proof_completed_at = now() を自動設定
```

---

## 10. 校正管理者による配分フロー

現状から変更なし。ただし以下を追加：

- `assignStore` 実行時に `ProofRequest.progress_cell_id`（または関連するセル座標）が存在する場合、pja100 作成後に `ProgressCell` の `proof_assignment_id` を自動更新する
- pja100 に `progress_cell_id`（校正セルへの FK）を設定する

---

## 11. 校正結果の記録とオペレーター返却フロー

### 経路A（校正管理経由）の完了

```php
// ProofRequestController::complete() に追記
$proofRequest->update([
    'status'       => 'completed',
    'completed_at' => now(),
]);

// 元ジョブ（pja_operator）に校正済みマークを付与
if ($proofRequest->project_job_assignment_id) {
    ProjectJobAssignment::where('id', $proofRequest->project_job_assignment_id)
        ->update(['proof_completed_at' => now()]);
}

// 依頼者への完了通知（既存）
JobNotificationService::notifyProofCompleted(Auth::user(), $proofRequest->fresh());
```

### 経路C（直接割当）の完了

直接割当ジョブ（校正セルの `proof_assignment_id`）が `completed = true` になったとき：

```php
// ProjectJobAssignment::booted() の updated イベント、または
// 完了APIのコントローラで以下を実行：

// proof セルから pja_operator を特定
$proofCell = ProgressCell::where('proof_assignment_id', $assignment->id)->first();
if ($proofCell) {
    // 同じ行の typesetting_register セルを探す
    $typeCell = ProgressCell::where('row_id', $proofCell->row_id)
        ->where('cell_type', 'typesetting_register')
        ->whereNotNull('assignment_id')
        ->first();
    if ($typeCell) {
        ProjectJobAssignment::where('id', $typeCell->assignment_id)
            ->whereNull('proof_completed_at')
            ->update(['proof_completed_at' => now()]);
    }
}
```

---

## 12. 進行管理表との紐づけ方針

### 双方向紐づけの実現

| 方向 | 実現方法 |
|---|---|
| セル → 組版ジョブ | `ProgressCell.assignment_id`（既存） |
| セル → 校正ジョブ | `ProgressCell.proof_assignment_id`（新規追加） |
| 組版ジョブ → セル | `ProjectJobAssignment.progress_cell_id`（新規追加） |
| 校正ジョブ → セル | `ProjectJobAssignment.progress_cell_id`（新規追加、同カラム流用） |
| 組版ジョブ → 校正ジョブ | `ProofRequest.project_job_assignment_id` 経由（既存） |
| 校正ジョブ → 組版ジョブ | `ProofRequest.project_job_assignment_id` を逆引き（既存） |

### 後からの紐づけ（シンプル実装）

既存ジョブを後から進行表セルに紐づける機能：

- ジョブ詳細ページに「進行表に紐づける」ボタンを追加
- クリックするとモーダルが開き、同一案件（ProjectJob）の進行表一覧 → 行 → セルを選択
- 選択後：
  - `ProgressCell.assignment_id = pja.id`
  - `pja.progress_cell_id = cell.id`
- **注意：既にセルに別のジョブが紐づいている場合は上書き確認ダイアログを表示**
- **注意：この操作でジョブの種別（独立ジョブ ↔ 進行表連携ジョブ）は変わらない**

---

## 13. 進行管理表 新設計（v2）

### 設計方針

旧設計（自由構成）を `progress_sheets`, `progress_rows`, `progress_cells` として保存しつつ、新設計は同テーブルを利用するが `column_config` に `schema_version: 2` を付与して区別する。

### 新 column_config の構造

```json
{
  "schema_version": 2,
  "rounds": [
    {
      "key": "shokou",
      "label": "初校",
      "type": "proof_round",
      "children": [
        {
          "key": "shokou_kumihan",
          "label": "組版",
          "type": "typesetting_group",
          "children": [
            { "key": "shokou_kumihan_tanto",  "label": "担当",  "type": "typesetting_tanto" },
            { "key": "shokou_kumihan_toroku", "label": "登録欄", "type": "typesetting_register" }
          ]
        },
        {
          "key": "shokou_kosei",
          "label": "校正",
          "type": "proof_group",
          "children": [
            { "key": "shokou_kosei_tanto",  "label": "担当",  "type": "proof_tanto" },
            { "key": "shokou_kosei_toroku", "label": "登録欄", "type": "proof_register" }
          ]
        }
      ]
    }
  ]
}
```

**ペアリングアルゴリズム：** 同じ `proof_round` 親グループ内の `typesetting_group` と `proof_group` が1対1でペアとなる。

### テンプレートエディタの変更

- 新規テンプレート作成時は「校（初校/再校/三校…）を何回分作るか」を入力するだけで `column_config` を自動生成
- 組版セット・校正セットは **必ずペアで追加** され、片方だけの削除はバリデーションエラー
- col_key は `{round_key}_kumihan_tanto` / `{round_key}_kosei_tanto` などプログラムが自動生成
- ユーザーは「校の名前（初校/再校/三校）」のみ入力

### 校正セルの担当者セレクタ

校正セル（`cell_type = 'proof_tanto'`）の担当者セレクタには以下を表示：

```
── 校正管理 ──    ← 先頭に固定表示
田中 太郎
山田 花子
...（案件メンバー一覧）
```

- **「校正管理」選択時**：校正依頼モーダルを表示 → ProofRequest 作成フロー（経路B）
- **通常ユーザー選択時**：直接割当フロー（経路C）

---

## 14. UI 変更案

### 14-1. 一覧・詳細での「校正済みマーク」表示

以下の画面で `proof_completed_at IS NOT NULL` の場合にバッジを表示：

| 画面 | 表示箇所 |
|---|---|
| マイジョブ一覧（MyJobBox/Index.vue） | ジョブ行に「校了」バッジ（緑色） |
| Coordinatorジョブ一覧 | 同上 |
| 進行管理表（組版登録セル） | セルに小さい「校了」アイコン |
| ダッシュボード（カレンダー） | is_proof_completed フラグで色分け |

### 14-2. 校正依頼モーダル（既存 + 改善）

`ProofRequestModal.vue`（または新規作成）：
- タイトル（元ジョブタイトル + "_校正" で自動入力）
- 校正締切（必須、オペレーターが設定。組版締切より前でないと警告）
- メモ（任意）
- 送信先 = 校正管理者全員に通知（既存）

### 14-3. 進行表テンプレートエディタ（新設計）

```
[新規テンプレート作成]
  校の数: [初校] [再校] [三校] [+ 追加]

  ※ 各校に「組版セット（担当+登録欄）」と「校正セット（担当+登録欄）」が自動生成されます

  [保存]
```

---

## 15. API / サーバー処理 変更案

### 変更が必要なコントローラー・サービス

| ファイル | 変更内容 |
|---|---|
| `ProofRequestController::assignPage` | prefill の `desired_end_date` を `ProofRequest.deadline` に修正 |
| `ProofRequestController::assignStore` | pja100 作成時に `progress_cell_id` を設定 + `ProgressCell.proof_assignment_id` 更新 |
| `ProofRequestController::complete` | `pja_operator.proof_completed_at = now()` を追記 |
| `ProofRequestController::store` | `progress_cell_id`（校正セルの参照）をオプションで受け取り保存 |
| `ProjectJobAssignmentsController::store` | ジョブ作成時に `_progress_cell_id` を受け取り `pja.progress_cell_id` に保存 |
| `ProgressSheetController::show` | `proof_assignment_id` / `cell_type` を cells に含める |
| `ProgressSheetController::linkJob` | 既存ジョブとセルを後から紐づける API を追加 |
| `ProgressCellController` (Coordinator) | `proof_assignment_id` / `cell_type` の更新に対応 |
| `User/ProgressSheetController::assign` | 校正セルへの直接割当時に `proof_assignment_id` を設定 |

### 新規コントローラー

| ファイル | 役割 |
|---|---|
| `Coordinator/ProgressTemplateV2Controller` | 新設計テンプレートの CRUD |
| （または既存 ProgressTemplateController に schema_version 分岐を追加） | |

### ProjectJobAssignment モデルへの追加

```php
// app/Models/ProjectJobAssignment.php
public function progressCell()
{
    return $this->belongsTo(ProgressCell::class, 'progress_cell_id');
}

// 校正済みかどうか
public function getIsProofCompletedAttribute(): bool
{
    return $this->proof_completed_at !== null;
}
```

---

## 16. DB 変更案

### マイグレーション一覧（追加順）

```
2026_04_XX_000001_add_proof_completed_at_and_cell_id_to_project_job_assignments.php
  - proof_completed_at TIMESTAMP NULL
  - progress_cell_id BIGINT UNSIGNED NULL FK → progress_cells.id (ON DELETE SET NULL)

2026_04_XX_000002_add_proof_assignment_id_and_cell_type_to_progress_cells.php
  - proof_assignment_id BIGINT UNSIGNED NULL FK → project_job_assignments.id (ON DELETE SET NULL)
  - cell_type VARCHAR(32) NULL

2026_04_XX_000003_add_progress_cell_id_to_proof_requests.php
  - proof_cell_id BIGINT UNSIGNED NULL FK → progress_cells.id (ON DELETE SET NULL)
  ※ ProofRequest が「どの校正セル宛か」を記録するため
```

### さくら本番の注意事項

- `project_jobs.schedule` カラムと同様に `Arr::pull()` 対象カラムがあれば要確認
- `progress_cells` は本番に実データあり。`proof_assignment_id` / `cell_type` は NULL 許容で追加するため既存データへの影響なし

---

## 17. 既存機能を活かす方針

| 既存実装 | 活かし方 |
|---|---|
| `ProjectJobAssignmentsController::store` の `_progress_sheet_id` / `_row_id` / `_col_key` | そのまま維持。`progress_cell_id` も合わせてセット |
| `ProofRequestController::assignPage` の prefill 構造 | `desired_end_date` のバグ修正のみ |
| `AssignmentForm.vue` の流用 | 変更なし |
| `ProofRequest` の status フロー | 変更なし（complete 時に proof_completed_at 追記のみ） |
| 旧進行表（schema_version なし / 1）| そのまま残存。新規作成時は v2 を推奨 |

---

## 18. 段階的リリース案

### Phase 1：バグ修正 + 校正済みマーク（最小変更）

- [ ] `ProofRequestController::assignPage` の `desired_end_date` バグ修正
- [ ] `proof_completed_at` カラム追加マイグレーション
- [ ] `ProofRequestController::complete` に `proof_completed_at` 更新を追記
- [ ] マイジョブ一覧・Coordinatorジョブ一覧に「校了」バッジ表示

### Phase 2：双方向紐づけ

- [ ] `progress_cell_id` カラム追加（ProjectJobAssignment）
- [ ] `proof_assignment_id` / `cell_type` カラム追加（ProgressCell）
- [ ] `assignStore` で `progress_cell_id` 自動設定
- [ ] `ProgressSheetController` の各アクションで `progress_cell_id` を設定・返却
- [ ] 進行管理表の組版セルに「校了」アイコン表示

### Phase 3：進行表 校正セルの改修

- [ ] `proof_assignment_id_to_proof_requests` マイグレーション追加
- [ ] 校正セルの担当者セレクタに「校正管理」選択肢を追加
- [ ] 「校正管理」選択時の ProofRequest 送信モーダル（経路B）実装
- [ ] 直接割当（経路C）完了時の `proof_completed_at` 自動設定

### Phase 4：新進行表設計（v2）

- [ ] `column_config` の `schema_version` 対応
- [ ] テンプレートエディタの新 UI（校の数を選ぶだけ）
- [ ] v2 テンプレートの保存・読み込み
- [ ] ペアバリデーション（組版+校正セットが揃っていないと保存不可）

### Phase 5：後から紐づけ機能

- [ ] ジョブ詳細に「進行表に紐づける」ボタン
- [ ] 紐づけモーダル（進行表 → 行 → セル選択）
- [ ] 上書き確認ダイアログ

---

## 19. リスクと対策

| リスク | 対策 |
|---|---|
| `proof_completed_at` が複数の ProofRequest で二重設定される | 最初に完了した時刻を使用（`whereNull` で既設定をスキップ）。ダブルチェックの場合は最初の完了者が設定 |
| 旧進行表と新進行表が混在してユーザーが混乱 | 新規作成ボタンで「新形式」を明示。旧形式は「レガシー」ラベルを付与 |
| `progress_cell_id` の FK が削除時に NULL になり紐づきが失われる | `ON DELETE SET NULL` で整合性を保つ。ジョブ一覧では NULL の場合は「進行表未設定」として表示 |
| さくら本番の `progress_cells` に NULL 不可のカラムを追加しようとする | 新カラムはすべて `NULL` 許容にする |
| 直接割当校正完了時の `proof_completed_at` 自動設定が重い | セル → ペア検索は1クエリで済む。パフォーマンス問題は発生しない見込み |

---

## 20. 未確定事項 / 要追加確認事項

| # | 事項 | 確認が必要な理由 |
|---|---|---|
| U-1 | ダブルチェック（複数校正者）で複数の ProofRequest が存在する場合、`proof_completed_at` はどちらが完了したときに設定するか | 「全員完了後」か「最初の完了で設定」かで実装が変わる |
| U-2 | 経路C（直接割当）の校正完了は、割当ジョブの `completed = true` を起点にするか、別途「校正完了」ボタンを設けるか | UX の明確化が必要 |
| U-3 | 新進行表 v2 の行（ProgressRow）構造は変更するか | 現在の `label` + `parent_id` 構造で v2 も対応可能か確認 |
| U-4 | 「校正管理を通さない校正」は ProofRequest を作成しないため、校正履歴（History）に記録されない。これでよいか | 直接割当は履歴に残らなくてよいか確認 |
| U-5 | 進行表「後から紐づけ」機能の優先度（Phase 5 で後回しでよいか） | ユーザーの実運用上の緊急度次第 |

---

## 21. 実装タスクの分解

### Phase 1（推奨着手）

```
Task 1-1: マイグレーション作成
  - proof_completed_at, progress_cell_id を project_job_assignments に追加
  ファイル: database/migrations/2026_04_XX_000001_...

Task 1-2: ProofRequestController バグ修正
  - assignPage の desired_end_date prefill を ProofRequest.deadline に変更
  ファイル: app/Http/Controllers/ProofCoordinator/ProofRequestController.php L112-114

Task 1-3: ProofRequestController::complete 修正
  - 完了時に pja_operator.proof_completed_at を更新
  ファイル: app/Http/Controllers/ProofCoordinator/ProofRequestController.php L598-617

Task 1-4: ProjectJobAssignment モデル更新
  - progressCell リレーション追加
  - is_proof_completed アクセサ追加
  ファイル: app/Models/ProjectJobAssignment.php

Task 1-5: Vue 一覧への「校了」バッジ追加
  ファイル: resources/js/Pages/MyJobBox/Index.vue
  ファイル: resources/js/Pages/Coordinator/ProjectJobs/JobAssign/Index.vue
```

### Phase 2

```
Task 2-1: ProgressCell マイグレーション
  - proof_assignment_id, cell_type カラム追加

Task 2-2: ProofRequestController::assignStore 修正
  - pja100 作成後に progress_cell_id / proof_assignment_id を設定

Task 2-3: ProgressSheetController 修正
  - show で proof_assignment_id / cell_type を返す
  - linkJob / assign で progress_cell_id を設定

Task 2-4: 進行管理表 Vue に「校了」アイコン追加
  ファイル: resources/js/Pages/Coordinator/ProgressSheets/Show.vue
  ファイル: resources/js/Pages/User/ProgressSheets/Show.vue
```

---

## 22. Claude が次に実装着手しやすい順序

以下の順で進めれば、各 Phase が独立してリリース可能。

```
① Task 1-2（バグ修正）           --- 最小リスク・即効性あり
② Task 1-1（マイグレーション）    --- 後続すべての前提
③ Task 1-3（complete 修正）      --- 校正済みマークの核心
④ Task 1-4（モデル更新）          --- ①〜③の後に
⑤ Task 1-5（Vue バッジ表示）      --- npm run build 必要
⑥ Task 2-1（ProgressCell 移行）  --- Phase 2 の前提
⑦ Task 2-2（assignStore 修正）   --- 双方向紐づけの核心
⑧ Task 2-3（ProgressSheet修正）  --- セル側の対応
⑨ Task 2-4（進行表 Vue 修正）     --- npm run build 必要
⑩ Phase 3 以降（校正セル改修）   --- Phase 2 完了後
⑪ Phase 4（新進行表 v2）          --- 最も規模が大きい
⑫ Phase 5（後から紐づけ）         --- 最後
```

---

## 付録：関連ファイル一覧（実装時の参照先）

| ファイル | 役割 | Phase |
|---|---|---|
| `app/Models/ProjectJobAssignment.php` | ジョブモデル | 1 |
| `app/Models/ProgressCell.php` | セルモデル | 2 |
| `app/Models/ProofRequest.php` | 校正依頼モデル | 1 |
| `app/Http/Controllers/ProofCoordinator/ProofRequestController.php` | 校正依頼管理 | 1, 2 |
| `app/Http/Controllers/User/ProgressSheetController.php` | 進行表（User） | 2 |
| `app/Http/Controllers/Coordinator/ProgressSheetController.php` | 進行表（Coordinator） | 2 |
| `app/Http/Controllers/Coordinator/ProjectJobAssignmentsController.php` | ジョブ割当 | 2 |
| `resources/js/Pages/MyJobBox/Index.vue` | マイジョブ一覧 | 1 |
| `resources/js/Pages/Coordinator/ProjectJobs/JobAssign/Index.vue` | Coジョブ一覧 | 1 |
| `resources/js/Pages/Coordinator/ProgressSheets/Show.vue` | 進行表（Coordinator） | 2 |
| `resources/js/Pages/User/ProgressSheets/Show.vue` | 進行表（User） | 2 |
| `resources/js/Pages/ProofCoordinator/Inbox/Assign.vue` | 校正割当フォーム | 1 |
| `database/migrations/` | マイグレーション | 1, 2 |
| `z_instructions/progresssheet.png` | 進行表スクリーンショット | 参照 |
