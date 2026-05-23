# G-01 設計書：項目（ProjectJobItem）システム
作成日: 2026-04-24（改訂: 2026-04-25 v3）

---

## 概要

案件（ProjectJob）の進行管理表（ProgressSheet）と、カレンダー（project_schedules）を「項目」で橋渡しする仕組み。

### 3つのルール

1. **項目は進行表と必ず 1:1 対応する**（1枚の進行表 → 1つの項目一覧）
2. **カレンダーと項目の連携は任意**（項目ごとに ON/OFF を切り替える）
3. **項目は名前のみ**。日付・分類は持たない。日付はカレンダー（project_schedules）、進行状況は進行表（ProgressSheet）が管理する

---

## コンセプト図

```
案件（ProjectJob）
 ├── 進行表A（締切表）          ← Sheet 7の例
 │    └── [項目一覧: 締切表-項目一覧]
 │         ├── 縦行: 入稿 / 初校出し / 初校戻り / 再校出し / 再校戻り
 │         └── 横列: 予定日 > 日付 / チェック
 │
 └── 進行表B（担当表）          ← Sheet 5の例
      └── [項目一覧: 担当表-項目一覧]
           ├── 縦行: 学校A > 国語 / 算数   学校B > 国語 / 算数
           └── 横列: 初校 > 組版 / 校正

                    ↓ calendar_linked = true の項目のみ

              カレンダー（project_schedules）
              予定「入稿」 2026-04-10 → 項目「入稿」にリンク
              → progress_rows[入稿].deadline が自動更新
```

---

## 項目の2種類

| 種別 | 意味 | 参照先 |
|------|------|--------|
| 縦行（row） | 進行表の行ラベル | `progress_rows.id`（row_id） |
| 横列（column） | 進行表の列ヘッダー | `progress_sheets.column_config` の key（col_key） |

---

## DB 設計

### `project_job_items`（全面改訂）

| カラム            | 型                       | 備考                                         |
|------------------|--------------------------|----------------------------------------------|
| id               | bigint (PK)              |                                              |
| progress_sheet_id| FK → progress_sheets     | cascadeOnDelete                              |
| name             | string                   | 表示名                                        |
| type             | enum('row','column')     | 縦行 or 横列                                  |
| row_id           | nullable FK → progress_rows | nullOnDelete。type='row' のとき使用        |
| col_key          | nullable string          | column_config の key。type='column' のとき使用|
| parent_label     | nullable string          | グループ表示用ラベル（学校A、初校 など）        |
| calendar_linked  | boolean (default false)  | カレンダーと連携するか                         |
| order            | integer (default 0)      | 同種別内の表示順                               |
| created_at       | timestamp                |                                              |
| updated_at       | timestamp                |                                              |

> 旧設計の `project_job_id` / `category` / `start_date` / `deadline` はすべて削除

### 既存テーブル変更

| テーブル           | カラム操作                | 型              | 備考                                          |
|-------------------|--------------------------|-----------------|-----------------------------------------------|
| project_schedules | project_job_item_id 維持 | nullable FK     | nullOnDelete                                  |
| progress_rows     | project_job_item_id 削除 | —               | 新設計では items.row_id → rows の向きになる    |
| progress_rows     | deadline 維持            | date nullable   | カレンダー同期で更新される                      |

---

## カレンダー同期ルール

同期は **カレンダー → 進行表** の一方向のみ。

| 条件 | 処理 |
|------|------|
| スケジュールが更新され、`project_job_item_id` がセット済み | item を引く |
| item の `calendar_linked = true` かつ `type = 'row'` かつ `row_id` あり | `progress_rows[row_id].deadline = schedule.end_date` |
| item の `calendar_linked = false` または `type = 'column'` | 進行表への同期なし（カレンダー表示のみ） |

---

## 連携設定タブ UI（ProjectJobItemsTab.vue）

案件詳細（ProjectJobs/Show.vue）の「連携設定」タブに配置。

### タブ内の構成

進行表ごとにセクションを分ける:

```
[締切表-項目一覧]  [担当表-項目一覧]  ...
```

各セクションは進行表1枚に対応。

### 各セクションの3モード

#### 閲覧モード（デフォルト）

```
┌────────────── 締切表-項目一覧 ──────────────────────────────┐
│ [閲覧] [編集]                                                │
│ 縦行                        │ 横列                          │
│ ─────                       │ ─────                         │
│  入稿         📅連携         │  予定日                       │
│  初校出し     📅連携         │    日付     📅連携             │
│  初校戻り     📅連携         │    チェック                    │
│  再校出し     📅連携         │                               │
│  再校戻り     📅連携         │                               │
└─────────────────────────────────────────────────────────────┘
```

- 📅 アイコン = calendar_linked = true の項目
- 閲覧のみ、操作ボタンなし

#### 編集モード

```
┌────────────── 締切表-項目一覧 ──────────────────────────────┐
│ [閲覧] [編集★]                                              │
│ 縦行                         │ 横列                          │
│ ─────────────────────────── │ ─────────────────────────── │
│ [1] [入稿___] [行: 入稿▼] 📅[✓] [×]                        │
│ [2] [初校出し] [行: 初校出し▼] 📅[✓] [×]                   │
│ ...                          │                               │
│ [+ 縦行を追加]                │ [+ 横列を追加]                │
│                              │                               │
│                [保存]  [キャンセル]                           │
└─────────────────────────────────────────────────────────────┘
```

各行の編集フィールド:
- **名前**: テキスト入力
- **行セレクター（縦行）**: progress_rows のドロップダウン（`row_id` を選択）
- **列セレクター（横列）**: column_config の leaf key のドロップダウン（`col_key` を選択）
- **📅 カレンダー連携**: チェックボックス（calendar_linked）
- **×**: 削除ボタン

#### 作成モード（進行表はあるが項目がまだ存在しない場合）

```
┌────────────── 締切表-項目一覧 ──────────────────────────────┐
│ 項目がまだ登録されていません。進行表から自動読み込みできます。 │
│ [進行表から読み込む]                                         │
└─────────────────────────────────────────────────────────────┘
```

「進行表から読み込む」クリック後:

- progress_rows → 縦行の提案リストを生成
  - parent_id = null の行 → parent_label として使用
  - 子行（または親なし行）→ 個別 item 候補
- column_config → 横列の提案リストを生成
  - children を持つカラム → parent_label
  - leaf カラム（type: date / text / checkbox 等）→ 個別 item 候補
- 提案リストを左右に表示し、不要な項目は × で削除
- 「この内容で作成」ボタンで一括保存

---

## カレンダー UI での項目連携

- ProjectCalendar のスケジュール作成・編集モーダルに「項目に紐づける」ドロップダウンを追加
- 選択肢: 案件に紐づく全進行表の calendar_linked=true の項目
- 保存すると `project_schedules.project_job_item_id` がセット

---

## API エンドポイント

| メソッド | ルート名                                              | 説明                          |
|---------|-----------------------------------------------------|-------------------------------|
| GET     | coordinator.progress_sheets.items.index             | 項目一覧（JSON）               |
| POST    | coordinator.progress_sheets.items.store             | 項目作成                       |
| PUT     | coordinator.progress_sheets.items.update            | 項目更新（名前/order/連携ON-OFF）|
| DELETE  | coordinator.progress_sheets.items.destroy           | 項目削除                       |
| POST    | coordinator.progress_sheets.items.import_from_sheet | 進行表から自動読み込み           |
| GET     | coordinator.project_jobs.items.index_for_calendar   | カレンダー用・連携項目一覧       |

> ルート階層を project_jobs → progress_sheets に変更（項目は進行表に属するため）

---

## マイグレーション計画

| ファイル名（連番）| 内容 |
|-----------------|------|
| `...000003_create_project_job_items_table.php` | 初期作成（旧）|
| `...000004_add_item_id_to_project_schedules_table.php` | FK追加（旧・維持）|
| `...000005_add_item_id_and_deadline_to_progress_rows_table.php` | FK+deadline追加（旧）|
| `...000006_revise_project_job_items_table.php` | **新**: items を全面改訂（旧カラム削除・新カラム追加）|
| `...000007_drop_item_id_from_progress_rows_table.php` | **新**: progress_rows.project_job_item_id を削除 |

---

## 変更ファイル一覧

### 新規作成

| ファイル | 内容 |
|---------|------|
| `database/migrations/..._000006_revise_project_job_items_table.php` | テーブル全面改訂 |
| `database/migrations/..._000007_drop_item_id_from_progress_rows_table.php` | 不要FK削除 |
| `app/Http/Controllers/Coordinator/ProgressSheetItemController.php` | 新コントローラ |

### 既存ファイル変更

| ファイル | 変更内容 |
|---------|---------|
| `app/Models/ProjectJobItem.php` | fillable・cast・リレーション全面改訂 |
| `app/Models/ProgressSheet.php` | `items()` リレーション追加 |
| `app/Models/ProgressRow.php` | `project_job_item_id` 削除 |
| `app/Http/Controllers/Coordinator/ProjectJobItemController.php` | 廃止 → ProgressSheetItemController へ |
| `app/Http/Controllers/Coordinator/ProjectSchedulesController.php` | 同期ロジック修正（row_id 経由で deadline 更新）|
| `routes/web.php` | progress_sheets.items ルートへ変更 |
| `resources/js/Components/ProjectJobItemsTab.vue` | 全面書き直し（3モード・左右分割）|
| `resources/js/Components/ProjectCalendar.vue` | items イベントレイヤー削除・リンク用ドロップダウン追加 |
| `resources/js/Pages/Coordinator/ProjectSchedules/Calendar.vue` | calendar_linked 項目のみ取得 |
| `resources/js/Pages/Coordinator/ProjectJobs/Show.vue` | 「項目」タブ：進行表セクション対応 |

---

## 未実装・今後の検討

- カレンダー同期時に `progress_cells.value_date` も更新する（現状は `progress_rows.deadline` のみ）
- 横列（column）項目のカレンダー連携時の進行表同期（現状は表示のみ）
- ProgressTable.vue 内でのハイライト表示

---

## 次期改修計画：G-02 ジョブ完了による進行表セル自動更新

### 背景・目的

ユーザー（担当者）がジョブを完了した際、対応する進行表のセルを自動で更新することで、
コーディネーターや担当者が進行表を直接操作しなくても進捗が反映されるようにする。

例: 「学校A > 国語 × 初校 > 組版」を担当したユーザーがジョブ完了 →
進行表の該当セル（組版列 × 国語行）に完了日を自動記入

### 必要な設計変更

#### ① `project_job_items.type` に `'cell'` を追加

| type   | row_id | col_key | 意味                        |
|--------|--------|---------|-----------------------------|
| row    | 有     | null    | 行のみ（現行）               |
| column | null   | 有      | 列のみ（現行）               |
| cell   | 有     | 有      | 行＋列の交点（新規）          |

`type = 'cell'` のとき `row_id` と `col_key` の両方をセットして特定のセルを指す。

#### ② `project_job_assignments` に `project_job_item_id` を追加

- nullable FK → `project_job_items.id`
- コーディネーターが割り当て作成・編集時に「このジョブはどのセルに対応するか」を設定できる
- 設定しない場合は現行動作のまま（進行表への自動反映なし）

#### ③ 完了時の自動同期ロジック（バックエンド）

ジョブ完了処理（`project_job_assignments` の完了操作）時に以下を実行:

```
if assignment.project_job_item_id が存在する:
    item = ProjectJobItem::find(assignment.project_job_item_id)
    if item.type === 'cell' && item.row_id && item.col_key:
        end_date = assignment.end_date ?? null  // 期限日を完了日として使用
        if end_date:
            progress_cells[(row_id, col_key)] に end_date を書き込む
        // end_date がなければ無視（エラーにしない）
```

#### ④ UI 変更

- 連携設定タブ: `type='cell'` の項目作成UI（行セレクター＋列セレクター を同時に選択）
- ジョブ割り当て作成・編集モーダル: 「進行表セルに紐づける」ドロップダウン追加
  - 選択肢: 案件に紐づく全進行表の `type='cell'` 項目一覧

### 注意事項

- `project_job_assignments` には現時点で完了日カラムが存在しないため、
  `end_date`（期限日）を完了日として代用する
- 将来的に `completed_at` カラムを追加する場合はそちらを優先する
- 進行表の `progress_cells` への書き込みは上書きとする（確認ダイアログなし）
- 完了取り消し時の進行表ロールバックは実装しない（手動で修正）
