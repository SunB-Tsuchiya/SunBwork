# 進行管理表（ProgressSheet）機能仕様書

作成日: 2026-04-03
ステータス: **設計完了・実装未着手**

---

## 概要

Coordinatorが案件（ProjectJob）ごとに「進行管理表」を作成・管理する機能。
印刷・組版会社の台割（ページ割）に基づくワークフロー管理ツール。
縦軸＝台割行（P.1-4, 表紙など）、横軸＝ステージ列（入稿, 初校, 再校など）の表形式。

---

## DB設計（4テーブル）

### `progress_templates` — 共有フォーマットテンプレート
```sql
id
name          string
description   text nullable
column_config json              -- 列ツリー定義（後述）
created_by    FK → users
is_shared     boolean default false  -- true = 全Coordinator参照可
timestamps
```

### `progress_sheets` — 案件ごとの進行管理表（複数可）
```sql
id
project_job_id  FK → project_jobs
template_id     FK → progress_templates nullable  -- 元テンプレート
name            string   -- "本文用" / "表紙用" など
column_config   json     -- テンプレートからコピーしてカスタマイズ
created_by      FK → users
timestamps
```

### `progress_rows` — 台割の行（縦軸）
```sql
id
sheet_id  FK → progress_sheets
label     string   -- "P.1-4" / "表紙" / "奥付" など
order     integer
timestamps
```

### `progress_cells` — セル値（値が入ったもののみ保存: スパース）
```sql
id
row_id          FK → progress_rows
col_key         string   -- column_config内のリーフ列UUID
value_text      text nullable
value_date      date nullable
value_bool      boolean nullable
value_user_id   FK → users nullable
assignment_id   FK → project_job_assignments nullable  -- MyJob連動用
timestamps
```

---

## column_config JSON構造

```json
[
  {
    "key": "uuid-A",
    "label": "入稿",
    "type": "group",
    "children": [
      { "key": "uuid-A1", "label": "担当者", "type": "user" },
      { "key": "uuid-A2", "label": "入稿日", "type": "date" },
      { "key": "uuid-A3", "label": "完了",   "type": "checkbox" }
    ]
  },
  {
    "key": "uuid-B",
    "label": "初校",
    "type": "group",
    "children": [
      {
        "key": "uuid-B1",
        "label": "組版",
        "type": "group",
        "children": [
          { "key": "uuid-B1a", "label": "担当者", "type": "user" },
          { "key": "uuid-B1b", "label": "開始",   "type": "date" },
          { "key": "uuid-B1c", "label": "終了",   "type": "date" }
        ]
      }
    ]
  }
]
```

- `type: "group"` → 子を持つノード（セルなし）、最大3階層
- `type: "user" | "date" | "checkbox" | "text"` → リーフ（セルあり）
- 各ノードの `key` はUUID（列削除・並び替え時にセルとの対応を保持するため）

---

## テーブルヘッダーのcolspan/rowspan計算

- 最大深さ = theadの行数
- groupのcolspan = 配下リーフ数の合計
- リーフのrowspan = 最大深さ - 自分の深さ + 1
- groupのrowspan = 1

---

## ルート定義

### Coordinator（coordinatorミドルウェアグループ内に追加）

```php
// 進行管理テンプレート
Route::get('progress-templates', [ProgressTemplateController::class, 'index'])->name('progress_templates.index');
Route::get('progress-templates/create', [ProgressTemplateController::class, 'create'])->name('progress_templates.create');
Route::post('progress-templates', [ProgressTemplateController::class, 'store'])->name('progress_templates.store');
Route::get('progress-templates/{template}/edit', [ProgressTemplateController::class, 'edit'])->name('progress_templates.edit');
Route::put('progress-templates/{template}', [ProgressTemplateController::class, 'update'])->name('progress_templates.update');
Route::delete('progress-templates/{template}', [ProgressTemplateController::class, 'destroy'])->name('progress_templates.destroy');

// 進行管理シート（案件配下）
Route::post('project_jobs/{projectJob}/progress-sheets', [ProgressSheetController::class, 'store'])->name('project_jobs.progress_sheets.store');
Route::get('progress-sheets/{sheet}', [ProgressSheetController::class, 'show'])->name('progress_sheets.show');
Route::put('progress-sheets/{sheet}', [ProgressSheetController::class, 'update'])->name('progress_sheets.update');
Route::delete('progress-sheets/{sheet}', [ProgressSheetController::class, 'destroy'])->name('progress_sheets.destroy');
Route::post('progress-sheets/{sheet}/register-template', [ProgressSheetController::class, 'registerAsTemplate'])->name('progress_sheets.register_template');

// 行管理
Route::post('progress-sheets/{sheet}/rows', [ProgressRowController::class, 'store'])->name('progress_sheets.rows.store');
Route::put('progress-sheets/{sheet}/rows/{row}', [ProgressRowController::class, 'update'])->name('progress_sheets.rows.update');
Route::delete('progress-sheets/{sheet}/rows/{row}', [ProgressRowController::class, 'destroy'])->name('progress_sheets.rows.destroy');
Route::post('progress-sheets/{sheet}/rows/import', [ProgressRowController::class, 'import'])->name('progress_sheets.rows.import');
Route::put('progress-sheets/{sheet}/rows-reorder', [ProgressRowController::class, 'reorder'])->name('progress_sheets.rows.reorder');

// セル一括更新
Route::put('progress-sheets/{sheet}/cells', [ProgressCellController::class, 'bulkUpdate'])->name('progress_sheets.cells.update');
```

### User（userミドルウェアグループ内に追加）

```php
Route::get('progress-sheets/{sheet}', [UserProgressSheetController::class, 'show'])->name('progress_sheets.show');
Route::post('progress-sheets/{sheet}/cells/{cell}/assign', [UserProgressSheetController::class, 'assign'])->name('progress_sheets.cells.assign');
Route::delete('progress-sheets/{sheet}/cells/{cell}/assign', [UserProgressSheetController::class, 'unassign'])->name('progress_sheets.cells.unassign');
```

---

## コントローラ一覧

```
app/Http/Controllers/Coordinator/
  ProgressTemplateController.php   index/create/store/edit/update/destroy
  ProgressSheetController.php      show/store/update/destroy/registerAsTemplate
  ProgressRowController.php        store/update/destroy/import/reorder
  ProgressCellController.php       bulkUpdate

app/Http/Controllers/User/
  ProgressSheetController.php      show/assign/unassign
```

### ProgressSheetController::show() が返すデータ
```php
[
  'sheet'     => [...],          // name, column_config
  'rows'      => [...],          // id, label, order
  'cells'     => [...],          // row_id, col_key, value_*, assignment_id
  'users'     => [...],          // id, name (担当者選択用)
  'projectJob'=> [...],          // id, title
  'canEdit'   => bool,           // オーナーまたはサブCo
]
```

### ProgressSheetController::registerAsTemplate()
- sheet の column_config をコピーして progress_templates に INSERT
- is_shared = true でデフォルト作成
- name は「{sheet.name}のテンプレート」で初期値、リクエストで上書き可

### ProgressCellController::bulkUpdate()
- リクエスト: `{ cells: [{ row_id, col_key, value_type, value }] }`
- upsert（row_id + col_key でユニーク）

---

## モデル

```
app/Models/ProgressTemplate.php
  fillable: name, description, column_config, created_by, is_shared
  casts: column_config → array
  belongsTo: creator (User)

app/Models/ProgressSheet.php
  fillable: project_job_id, template_id, name, column_config, created_by
  casts: column_config → array
  belongsTo: projectJob, template, creator
  hasMany: rows

app/Models/ProgressRow.php
  fillable: sheet_id, label, order
  belongsTo: sheet
  hasMany: cells

app/Models/ProgressCell.php
  fillable: row_id, col_key, value_text, value_date, value_bool, value_user_id, assignment_id
  casts: value_date → date, value_bool → boolean
  belongsTo: row, user (value_user), assignment
```

---

## Vue ページ・コンポーネント

### ページ
```
resources/js/Pages/Coordinator/ProgressSheets/Show.vue
  - 通常モード: 多段ヘッダーテーブル表示 + セルインライン編集
  - 編集モード: 右サイドパネルで列ツリー操作 + 行管理 + CSV取込
  - 「テンプレートとして登録」ボタン（モーダルで名前入力）

resources/js/Pages/Coordinator/ProgressTemplates/Index.vue
  - テンプレート一覧（共有 / 自分のもの）
  - 複製・編集・削除

resources/js/Pages/Coordinator/ProgressTemplates/Edit.vue
  - ColumnTreeEditor コンポーネントを使う列構成編集

resources/js/Pages/User/ProgressSheets/Show.vue
  - 閲覧 + 担当者セルに自分を登録（MyJob化）
```

### コンポーネント
```
resources/js/Components/ProgressTable.vue
  - props: rows[], columnConfig[], cells[], users[], canEdit, editMode
  - 多段ヘッダー（colspan/rowspan計算）
  - セルクリックで ProgressCell に委譲

resources/js/Components/ProgressCell.vue
  - props: cell, colDef (type), canEdit
  - type別表示: checkbox=チェック / date=日付 / user=名前 / text=テキスト
  - 編集時: type別インプット表示

resources/js/Components/ColumnTreeEditor.vue
  - 右サイドパネル
  - ツリー表示（再帰コンポーネント）
  - 操作: 列追加・サブ列追加・ラベル編集・type変更・削除・並び替え
```

---

## ProjectJob Show ページへの追加

`resources/js/Pages/Coordinator/ProjectJobs/Show.vue` の末尾セクションに追加：

```html
<!-- ── 進行管理表セクション ─────────────────── -->
<section class="py-5">
  <div class="mb-3 flex items-center gap-4">
    <h3 class="font-semibold text-gray-800">進行管理表</h3>
    <button @click="createSheet">新規作成</button>
    <Link :href="route('coordinator.progress_templates.index')">テンプレート管理</Link>
  </div>
  <div v-if="progressSheets.length > 0">
    <!-- シート一覧カード -->
  </div>
</section>
```

`ProjectJobController::show()` に `progressSheets` を追加:
```php
'progressSheets' => $projectJob->progressSheets()
    ->select(['id','name','created_at'])
    ->orderByDesc('created_at')->get()
```

---

## MyJob連動フロー

```
① UserがShow.vueの「担当者」型セルをクリック
   → 「自分を担当に登録」ボタン表示

② POST user/progress-sheets/{sheet}/cells/{cell}/assign
   → progress_cells.value_user_id = 自分
   → project_job_assignments に INSERT（MyJob化）
     title = "{案件名} - {行ラベル}/{列ラベル}"
   → progress_cells.assignment_id = 作成したassignment.id

③ MyJobBoxでジョブを完了すると
   → ProjectJobAssignment モデルの Observer（或いはJobBoxController）で
     対応するprogress_cellsを検索して
     同じrow_idの「終了」型セルに value_date = 完了日時 をセット
     （同じrow_idかつtype='date'かつlabelが"終了"のセルを対象）
```

---

## 実装フェーズ

| Phase | 内容 | 優先度 |
|---|---|---|
| 1 | マイグレーション（4テーブル）+ モデル | 最高 |
| 2 | Coordinator ProgressSheetController + ProgressRowController | 最高 |
| 3 | ProgressTable.vue + ProgressCell.vue（多段ヘッダー + セル表示） | 最高 |
| 4 | ColumnTreeEditor.vue（編集モード・サイドパネル） | 高 |
| 5 | ProjectJob Show.vue に進行管理表セクション追加 | 高 |
| 6 | ProgressTemplate CRUD + テンプレートとして登録 | 中 |
| 7 | User/ProgressSheets/Show.vue + 担当登録 | 中 |
| 8 | MyJob完了 → セル自動更新（Observer） | 低 |

---

## マイグレーションファイル名（予定）

```
2026_04_03_200001_create_progress_templates_table.php
2026_04_03_200002_create_progress_sheets_table.php
2026_04_03_200003_create_progress_rows_table.php
2026_04_03_200004_create_progress_cells_table.php
```

---

## 権限ルール

- シートの作成: 案件のオーナーCo または サブCo のみ
- シートの閲覧: 案件の担当Co + 案件メンバー（User）
- セルの入力（通常モード）: 上記全員
- 列構成の編集（編集モード）: Coordinatorのみ
- テンプレートの閲覧: 全Coordinator
- テンプレートの編集・削除: 作成者のみ

---

## 関連既存ファイル

- `app/Http/Controllers/Coordinator/ProjectJobController.php` → show()にprogressSheets追加
- `resources/js/Pages/Coordinator/ProjectJobs/Show.vue` → 進行管理表セクション追加
- `routes/web.php` → coordinator/userグループに追加（line 559の`});`の直前）
- `app/Models/ProjectJob.php` → `hasMany(ProgressSheet::class)` 追加
