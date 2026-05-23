# 進行表 V2 設計書
作成日: 2026-04-25

---

## 概要

進行表を「担当者管理 + ジョブ連携 + スケジュール連携」が一体となったデータ管理基盤として刷新する。
現在の「担当セル（user型）+ 登録ボタン（joblink型）をセットで作る」制約を撤廃し、
1つのセルで担当者管理・締め切り管理・ジョブ完了を完結させる。

---

## Q&A決定事項（設計根拠）

| 質問 | 決定 |
|------|------|
| workerセルにジョブ登録は必須か | 不要。外注・非PC利用者は担当者のみ設定可 |
| 締め切りの取得元 | D: カレンダー優先、なければ登録時モーダル、手動上書き可 |
| schedlinkの完了操作 | A: project_schedules.completed_at を更新 |
| 自動完了連携 | 現状維持（ジョブ完了→セル完了、校正連携も維持） |
| 既存シートの移行 | C: 変換ボタンでCが任意に新形式へ変換 |
| 担当抽出・検索 | A(進行表内フィルター) + C(Coordinator横断レポート) |
| 追加スコープ | 締め切りアラート色・完了率バッジ・テンプレート対応・User担当一覧・外部共有URL・セルメモ・横断レポート すべて含める |

---

## DB変更

### 追加カラム一覧

| テーブル | カラム名 | 型 | 説明 |
|---------|---------|-----|------|
| `progress_cells` | `schedule_id` | FK→project_schedules (nullable) | schedlinkセル / workerセルの締め切り元スケジュール |
| `progress_cells` | `cell_deadline` | date (nullable) | 手動上書き締め切り（最優先） |
| `progress_cells` | `cell_note` | text (nullable) | セルメモ・コメント |
| `progress_cells` | `completed_at` | timestamp (nullable) | セル完了日時（worker完了時・schedlink完了時に記録） |
| `progress_sheets` | `share_token` | string(64) (nullable, unique) | 読み取り専用共有URL用トークン |
| `project_schedules` | `completed_at` | timestamp (nullable) | schedlinkからの完了記録 |

### マイグレーション方針

- すべてadditive（既存データに影響なし）
- `progress_cells`への追加は1ファイルにまとめる
- `progress_sheets.share_token` は別ファイル
- `project_schedules.completed_at` は別ファイル

---

## セル型定義

### 既存セル型（変更なし）

| 型 | 用途 |
|----|------|
| `text` | テキスト入力 |
| `checkbox` | チェックボックス |
| `date` | 日付入力 |
| `user` | 担当者セレクター（旧型・後方互換で残す） |
| `proof_user` | 校正担当者（校正管理連携・変更なし） |
| `joblink` | 登録ボタン（旧型・後方互換で残す） |
| `worktime` | 作業時間 |
| `stage` | ステージ |
| `size` | サイズ |
| `assignment` | 作業分担 |
| `workItemType` | 作業種別 |

### 新規セル型

---

#### `worker`型（担当＋ジョブ統合セル）

**レイアウト:**
```
┌──────────────────────────────────┬──────────────────┐
│ 左70%                            │ 右30%            │
│ [担当者セレクター ▼]             │ ┄┄ 未登録 ┄┄   │
│ 締切: 26/11/03                   │ ＋ 登録          │
├──────────────────────────────────┼──────────────────┤
│ 🔒 田中 太郎（登録後ロック）     │ 登録済           │
│ 締切: 26/11/03                   │ 詳細             │
│                                  │ 完了にする       │
├──────────────────────────────────┼──────────────────┤
│ 🔒 田中 太郎                     │ ✓ 完了          │
│ 完了: 26/11/05                   │ 詳細             │
└──────────────────────────────────┴──────────────────┘
```

**状態遷移:**
1. 未設定 → 担当者セレクター表示のみ
2. 担当者選択後 → 右側に「＋ 登録」ボタン出現
3. 登録後（assignment_id あり）→ 担当者ロック・締め切り表示・詳細/完了ボタン
4. 完了後 → 「締切: 26/11/03」→「完了: 26/11/05」に変化・緑背景

**担当者設定の種類:**
- 社内ユーザー → ジョブ発注（標準フロー）
- 外注先 → ジョブ登録なしで担当者のみ記録可
- 氏名テキスト入力 → 非PC利用者・手書き対応（`value_text`に名前を直接入力）

**締め切り表示ロジック（優先順）:**
1. `cell_deadline`（手動上書き）
2. `schedule_id`で紐づいたスケジュールの`end_date`
3. `assignment_id`で紐づいたジョブの`desired_end_date`
4. なし（表示しない）

**登録時の動作:**
- 担当者選択 → 「＋ 登録」クリック → モーダル表示
- モーダルでは「締め切り日」「スケジュール紐づけ」を確認・設定
- スケジュール選択があれば`schedule_id`をセルに保存
- 既存のジョブ登録フロー（Coordinatorがアサインメント作成）はそのまま

**完了連携（現状維持）:**
- ジョブ（JobBox）で完了 → `assignment.completed = true` → セルの`completed_at`を記録
- 「完了にする」ボタン → 既存の`coordinator.progress_sheets.assignments.complete`を呼ぶ + `completed_at`も保存

**アラート色:**
- 期日超過（today > deadline かつ未完了） → 赤背景（`bg-red-50`・左枠赤）
- 3日以内（today + 3 >= deadline かつ未完了） → 黄背景（`bg-yellow-50`）
- 完了済み → 緑背景（`bg-green-50`）

---

#### `schedlink`型（予定連携セル）

**レイアウト:**
```
┌──────────────────────────────────┬──────────────────┐
│ 左70%                            │ 右30%            │
│ [スケジュール選択 ▼]             │                  │
│ 入稿                             │ 完了にする       │
│ 26/11/03                         │ 詳細             │
├──────────────────────────────────┼──────────────────┤
│ ✓ 入稿（完了後）                 │ ✓ 完了          │
│ 完了: 26/11/05                   │ 詳細             │
└──────────────────────────────────┴──────────────────┘
```

**セレクターの選択肢:** 同一案件の`project_schedules`を`start_date`順に表示

**完了操作:** `project_schedules.completed_at = now()` をセット + `progress_cells.completed_at`も記録

**アラート色:** workerセルと同ロジック（schedule.end_date を締め切りとして使用）

---

## 機能仕様

### 1. 既存シート変換機能（V-07）

**対象:** `user`+`joblink`のペア列を持つ既存シート

#### 変換ルール

同一親列の `children` に `user` 型 + `joblink` 型のペアが存在する場合を検出し、1つの `worker` 型列に統合する。

**ペア検出アルゴリズム:**
- 連続するノードを走査し、`user`直後に`joblink`が続く場合をペアと判定
- `proof_user` + `joblink` は **校正セット** とみなし変換対象外（V-13 で別途対応）

**データ引き継ぎ（必須）:**

| 旧セル | 旧カラム | → 新workerセル | 新カラム |
|--------|---------|----------------|---------|
| user型 | `value_user_id` | worker型 | `value_user_id` |
| user型 | `value_subcontractor_id` | worker型 | `value_subcontractor_id` |
| joblink型 | `assignment_id` | worker型 | `assignment_id` |

- 旧 `user` セルの `col_key` をそのまま新 `worker` セルの `col_key` として使用
- 旧 `joblink` セルのレコードは `assignment_id` を移送後に削除（またはnull化）

#### UIフロー

```
① 「新形式に変換」ボタンを押す（ペアが存在するときのみ表示）
     ↓
② プレビューモーダルを表示
     ↓
③ ユーザーが内容を確認して「変換する」または「キャンセル」を選ぶ
     ↓
④ 変換実行 → ページリロード
```

#### ② プレビューモーダルの仕様

**API:** `GET /coordinator/progress-sheets/{sheet}/convert-preview`（実際の変換は行わない・読み取り専用）

**表示内容:**

```
┌─────────────────────────────────────────────────┐
│ 変換プレビュー                                   │
│                                                  │
│ 検出されたペア:                                  │
│  ✅ 組版 > 担当 + 登録欄 → worker型（担当+ジョブ）│
│  ✅ 修正 > 担当 + 登録欄 → worker型（担当+ジョブ）│
│                                                  │
│ 引き継がれるデータ:                              │
│  担当者設定: 12セル / ジョブ登録: 8セル          │
│  ⚠️ 引き継げないデータ: なし                    │
│                                                  │
│ ⚠️ 引き継げないデータがある場合の例:            │
│  ❌ 「担当」列に値があるが、対応するjoblink列が   │
│     別の行を参照しており統合できません（3セル）   │
│     → これらのセルは変換後に空になります         │
│                                                  │
│            [キャンセル] [変換する（元に戻せません）]│
└─────────────────────────────────────────────────┘
```

- 引き継げないデータ（例外ケース）が1件でもある場合は **赤字で件数を明示**
- 「変換する」ボタンのラベルは常に「変換する（元に戻せません）」として不可逆性を強調
- 引き継げないデータが0件の場合は「すべてのデータが引き継がれます」と表示

**プレビューAPIが返すデータ:**

```json
{
  "pairs": [
    {
      "parent_label": "組版",
      "user_col_key": "kumihan_user",
      "joblink_col_key": "kumihan_job",
      "worker_col_key": "kumihan_user",
      "cells_with_user": 5,
      "cells_with_job": 3,
      "cells_unmigratable": 0
    }
  ],
  "total_pairs": 2,
  "total_data_cells": 20,
  "total_unmigratable": 0
}
```

**「引き継げないデータ」が発生するケース（稀）:**
- user型セルと同一 `row_id` の joblink型セルが存在しない（片方のみ存在）→ 存在する方のみ引き継ぎ
- assignment が削除済みで参照先がない → `assignment_id` をnullで引き継ぎ（データ消失なし）

**API:** `PUT /coordinator/progress-sheets/{sheet}/convert-to-v2`（実際の変換）

#### 変換後の column_config 変化例

```
変換前:
  - key: kumihan, label: 組版, type: text
    children:
      - key: kumihan_user, label: 担当, type: user    ← 削除
      - key: kumihan_job,  label: 登録欄, type: joblink ← 削除

変換後:
  - key: kumihan_user, label: 担当, type: worker      ← 統合（key は user側を継承）
    ※ 親 kumihan ノードが user/joblink のみを子として持っていた場合は
       親ごとworkerに置き換え（label は親の label を引き継ぐ）
```

---

### 2. 締め切りアラート色

セル単位で自動的に背景色を変更する。DB変更なし・CSS追加のみ。

| 状態 | 条件 | スタイル |
|------|------|---------|
| 完了 | `completed_at` あり | `bg-green-50` + 左border緑 |
| 期日超過 | today > deadline かつ未完了 | `bg-red-50` + 左border赤 |
| 3日以内 | today + 3 >= deadline かつ未完了 | `bg-yellow-50` + 左border黄 |
| 通常 | それ以外 | デフォルト |

---

### 3. 完了率バッジ

**行単位:** 各行の末尾に「N/M 完了」を表示（`worker`型・`schedlink`型・`joblink`型のセルを集計）

**シート単位:** シート上部ツールバーに「全体: N/M完了 (XX%)」を表示

DB変更なし。Vueのcomputed計算のみ。

---

### 4. テンプレートへの新セル型対応

**対象ファイル:** `resources/js/Pages/Coordinator/ProgressTemplates/Edit.vue`
**対象:** 列タイプ選択ドロップダウンに`worker`・`schedlink`を追加

**セット方式初期化の更新:**
現在: `担当(user)` + `登録欄(joblink)` のペア生成
更新後: `担当(worker)` 1列のみ生成（ペア不要）

---

### 5. セルメモ・コメント機能

**仕様:**
- `worker`・`schedlink`・`joblink`型のセルにメモを付与可能
- メモはセルの下部に小さく表示（折りたたみ可）
- Coordinatorのみ編集可、全員閲覧可
- `progress_cells.cell_note` に保存

**UI:**
- セル右下に小さい「メモ」アイコン（メモあり/なしで色変化）
- クリックでインライン編集（テキストエリア、blur保存）

---

### 6. User向け「自分の担当セル一覧」

**場所:** JobBox（`resources/js/Pages/JobBox/Index.vue`）に「進行表担当」タブを追加

**表示内容:**
- 自分が`value_user_id`に設定されている`worker`型セルを全案件横断で取得
- 案件名 / 進行表名 / 行名 / 列名 / 締め切り / 完了状況
- 締め切り順でソート
- 未完了のみ/全件 の切り替えフィルター

**API:** `GET /progress-cells/my-assignments`（User側ルート）

**Controller:** `app/Http/Controllers/User/ProgressCellController.php`（新規）

---

### 7. 進行表の読み取り専用共有URL

**仕様:**
- `progress_sheets.share_token` にランダムトークンを発行
- `/shared/progress-sheets/{token}` で認証なしでアクセス可能
- 閲覧専用（編集・ジョブ操作不可）
- セルメモ・担当者名・完了状況は表示
- assignment_idなどの内部情報は非表示

**UI（進行表ツールバー）:**
- 「共有リンクを発行」ボタン
- 発行済みの場合: URLをコピーするボタン + 「リンクを無効化」ボタン

**API:**
- `POST /coordinator/progress-sheets/{sheet}/share` → トークン発行・URL返却
- `DELETE /coordinator/progress-sheets/{sheet}/share` → トークン無効化
- `GET /shared/progress-sheets/{token}` → 公開ページ（認証不要）

**Controller:** `app/Http/Controllers/Shared/ProgressSheetController.php`（新規）

**Page:** `resources/js/Pages/Shared/ProgressSheets/Show.vue`（新規）

---

### 8. Coordinator横断レポート

**場所:** 案件一覧ページ、または新規メニュー項目「進行レポート」

**検索条件:**
- 担当者（user）
- 案件（project_job）
- 完了状況（未完了/完了/全件）
- 締め切り期間（from/to）

**表示内容:**
- 案件名 / 進行表名 / 行名 / 列名 / 担当者名 / 締め切り / 完了状況 / 完了日

**API:** `GET /coordinator/progress-report`（クエリパラメータでフィルター）

**Controller:** `app/Http/Controllers/Coordinator/ProgressReportController.php`（新規）

**Page:** `resources/js/Pages/Coordinator/ProgressReport/Index.vue`（新規）

---

## API設計まとめ

| メソッド | URL | 説明 |
|---------|-----|------|
| PUT | `/coordinator/progress-sheets/{sheet}/convert-to-v2` | 既存シートをV2形式に変換（不可逆） |
| GET | `/coordinator/progress-sheets/{sheet}/convert-preview` | 変換プレビュー取得（読み取り専用・変換しない） |
| POST | `/coordinator/progress-sheets/{sheet}/share` | 共有トークン発行 |
| DELETE | `/coordinator/progress-sheets/{sheet}/share` | 共有トークン無効化 |
| GET | `/shared/progress-sheets/{token}` | 公開読み取りページ |
| GET | `/user/progress-cells/my-assignments` | 自分の担当セル一覧 |
| GET | `/coordinator/progress-report` | 横断レポート |
| POST | `/coordinator/progress-cells/{cell}/complete` | schedlink完了 |
| PATCH | `/coordinator/progress-cells/{cell}/note` | セルメモ更新 |
| PATCH | `/coordinator/progress-cells/{cell}/deadline` | 締め切り手動上書き |

---

## 実装順序（推奨）

| ID | 内容 | 依存 | 規模 |
|----|------|------|------|
| V-01 | DBマイグレーション（全6カラム）| なし | 小 |
| V-02 | `worker`型セル Backend API対応 | V-01 | 中 |
| V-03 | `worker`型セル Frontend実装（ProgressCell.vue） | V-02 | 大 |
| V-04 | `schedlink`型セル Backend API対応 | V-01 | 小 |
| V-05 | `schedlink`型セル Frontend実装 | V-04 | 中 |
| V-06 | 締め切りアラート色 + 完了率バッジ | V-03 | 小 |
| V-07 | 既存シート変換機能 | V-03 | 中 |
| V-08 | テンプレートへの新セル型対応 | V-03 | 小 |
| V-09 | セルメモ機能 | V-01 | 小 |
| V-10 | User向け「自分の担当セル一覧」 | V-03 | 中 |
| V-11 | 進行表の読み取り専用共有URL | V-01 | 中 |
| V-12 | Coordinator横断レポート | V-01 | 中 |
| V-13 | 校正列のworker型対応 | V-03 | 中 |
| V-14 | カレンダー予定の完了表示 | V-04 | 小 |
| V-15 | セット方式削除・列追加UI刷新 | V-03 | 中 |
| V-16 | 進行表の印刷機能 | V-11 | 小 |

---

## 10. 進行表の印刷機能（V-16）

### 概要

Coordinator・User・共有URL の3つのコンテキストで進行管理表を印刷できる機能。
専用の印刷ページ（`Print.vue`）を新しいタブで開き、ブラウザの印刷ダイアログを手動で起動する。

### 仕様

- 各ビューのツールバー（またはヘッダー）に「印刷」ボタンを追加
- クリックすると専用の印刷URLを新しいタブで開く
- 印刷ページはミニマルレイアウト（AppLayout なし）
- 操作パネル（「印刷を実行」ボタン）は `@media print` で非表示
- 自動印刷なし（「印刷を実行」ボタンを押して `window.print()` を実行）
- テーブルは `canEdit=false`・操作ボタンなしで描画

### ルート

| メソッド | URL | ルート名 | 認証 |
|---------|-----|---------|------|
| GET | `/coordinator/progress-sheets/{sheet}/print` | `coordinator.progress_sheets.print` | 要（Coordinator） |
| GET | `/user/progress-sheets/{sheet}/print` | `user.progress_sheets.print` | 要（User） |
| GET | `/shared/progress-sheets/{token}/print` | `shared.progress_sheets.print` | 不要 |

### 実装ファイル

| ファイル | 変更内容 |
|---------|---------|
| `app/Http/Controllers/Coordinator/ProgressSheetController.php` | `printView()` + `buildPrintCells()` 追加 |
| `app/Http/Controllers/User/ProgressSheetController.php` | `printView()` 追加 |
| `app/Http/Controllers/Shared/ProgressSheetController.php` | `printView()` 追加・`show()` に `token` prop 追加 |
| `routes/web.php` | 上記3ルート追加 |
| `resources/js/Pages/Shared/ProgressSheets/Print.vue` | 新規: 印刷専用ページ |
| `resources/js/Pages/Coordinator/ProgressSheets/Show.vue` | 「印刷」ボタン追加 |
| `resources/js/Pages/User/ProgressSheets/Show.vue` | 「印刷」ボタン追加 |
| `resources/js/Pages/Shared/ProgressSheets/Show.vue` | 「印刷」ボタン・`token` prop 追加 |

---

## 9. セット方式削除・列追加UI刷新（V-15）

### 背景・方針

現在「セット方式で初期化」「セット方式で作成」ボタンは、クリックすると**シートの既存データが消えてしまう破壊的操作**であり、誤操作リスクが高い。
V2 では `worker` 型が1列で担当＋ジョブ登録を完結できるため、「セットで2列作る」仕組みは不要になった。
これらのボタンを削除し、代わりに **列タイプセレクター** から柔軟に列を追加できる UI に刷新する。

### 削除対象

| 場所 | 対象 |
|------|------|
| `Show.vue` ツールバー | 「セット方式で初期化」ボタン |
| `Show.vue` | `showV2InitModal`・`v2InitRounds`・`generateV2ColumnConfig()`・`applyV2Init()` 等の関連state/関数 |
| `Show.vue` | セット方式初期化モーダル（`<template v-if="showV2InitModal">`） |
| `ProgressTemplates/Edit.vue`（確認後） | 同様の「セット方式で作成」UI があれば削除 |

### 追加する列追加UI

**場所:** 編集モード（`editMode`）時、列ツリーエディターの「列を追加」ドロップダウン

**追加セレクター選択肢（例）:**

| 選択肢 | 追加される列定義 |
|--------|---------------|
| テキスト列 | `{ type: 'text', label: '新しい列' }` |
| 担当＋ジョブ（worker） | `{ type: 'worker', label: '担当' }` |
| スケジュール連携（schedlink） | `{ type: 'schedlink', label: 'スケジュール' }` |
| 組版＋校正セット | `{ type: 'text', label: '組版', children: [ { type: 'worker', label: '担当' }, { type: 'text', label: '校正', children: [ { type: 'proof_user', label: '担当' }, { type: 'joblink', label: '登録欄' } ] } ] }` |
| 日付列 | `{ type: 'date', label: '日付' }` |
| チェックボックス | `{ type: 'checkbox', label: 'チェック' }` |

**「組版＋校正セット」選択時の挙動:**
- ラベル入力モーダルを表示（「列グループ名を入力してください」）
- 入力されたラベルを親ノードの `label` に設定
- 一意の `key` を自動生成（`col_{timestamp}` 形式）
- 列ツリーに即時追加（`column_config` に反映）

### 実装ファイル

| ファイル | 変更内容 |
|---------|---------|
| `resources/js/Pages/Coordinator/ProgressSheets/Show.vue` | ボタン削除・モーダル削除・関連state/関数削除・列追加セレクターUI追加 |
| `resources/js/Components/ColumnTreeEditor.vue` | 「列を追加」UI にセット選択肢を追加（または Show.vue 側で制御） |
| `resources/js/Pages/Coordinator/ProgressTemplates/Edit.vue` | 同様のセット方式UIがあれば削除・列追加セレクター統一 |

### 注意点

- `generateV2ColumnConfig()` は V-07 の変換ロジックから参照されている可能性があるため、V-07 完了後に削除する
- `key` の自動生成は `col_` + `Date.now()` で十分（ツリー内でユニークであればよい）
- 削除するモーダルが他のモーダルと連動している場合は影響範囲を確認する

---

## 参照ファイル（実装時に必ず読むこと）

| ファイル | 用途 |
|---------|------|
| `app/Models/ProgressCell.php` | セルモデル |
| `app/Models/ProgressSheet.php` | シートモデル |
| `app/Models/ProgressRow.php` | 行モデル |
| `app/Models/ProjectSchedule.php` | スケジュールモデル |
| `app/Http/Controllers/Coordinator/ProgressSheetController.php` | シートCRUD |
| `app/Http/Controllers/Coordinator/ProgressCellController.php` | セルCRUD |
| `app/Http/Controllers/ProjectJobs/JobBoxController.php` | ジョブ完了連携 |
| `resources/js/Components/ProgressCell.vue` | セルコンポーネント |
| `resources/js/Pages/Coordinator/ProgressSheets/Show.vue` | 進行表メインページ |
| `resources/js/Pages/Coordinator/ProgressTemplates/Edit.vue` | テンプレート編集 |
| `resources/js/Pages/JobBox/Index.vue` | User JobBox |
| `routes/web.php` | ルート定義 |
| `z_instructions/CONSOLIDATED_09_domain_rules.md` | ドメインルール |
| `z_instructions/LAYOUT_GUIDELINES.md` | UIルール |
