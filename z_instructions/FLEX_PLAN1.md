# FLEX_PLAN1.md — 部署別ジョブフィールド柔軟化 設計仕様

## 概要・目的

`project_job_assignments` の作業フィールド（作業種別・ステージ・サイズ・数量）を
部署ごとにカスタマイズ可能にする。現在の情報出版（DTP）設定を壊さず、
経理・総務・営業など他部署が独自のラベル・選択肢を持てるようにする。

---

## 設計方針

### フィールドスロット（4固定）

| スロットID | 現DTP表示名 | 対応マスタテーブル | 型 |
|---|---|---|---|
| `type`    | 作業種別           | `work_item_types` | ドロップダウン |
| `stage`   | ステージ（校数）   | `stages`          | ドロップダウン |
| `size`    | サイズ             | `sizes`           | ドロップダウン |
| `amounts` | 数量               | なし（数値入力）  | オン/オフのみ |

各スロットは部署ごとに:
- `enabled` — 表示/非表示
- `label` — 表示ラベル名（例: "ステージ" → "処理工程"）
- `allowed_item_ids` — 使用するマスタ項目のID配列（null = 全て使用）

### 後方互換性

- `department_field_configs` にレコードがない部署 → 現行動作（全スロット表示）を維持
- DTP部署: 既存レコードはそのまま動作。設定を明示的にするなら移行スクリプトで初期レコードを投入

---

## DB設計

### 新規テーブル: `department_field_configs`

```sql
CREATE TABLE department_field_configs (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    department_id   BIGINT UNSIGNED NOT NULL,
    slot            ENUM('type','stage','size','amounts') NOT NULL,
    label           VARCHAR(100) NOT NULL DEFAULT '',      -- 部署独自ラベル
    enabled         TINYINT(1) NOT NULL DEFAULT 1,
    allowed_item_ids JSON NULL,                           -- null=全選択肢, 配列=絞込み
    sort_order      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    UNIQUE KEY uq_dept_slot (department_id, slot),
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
);
```

### 既存テーブル変更: `stages`

`work_item_types` / `sizes` には既存で `company_id`・`group` があるが、
`stages` にはない。workload-setting のグループ表示を統一するために追加:

```sql
ALTER TABLE stages
    ADD COLUMN company_id   BIGINT UNSIGNED NULL AFTER coefficient,
    ADD COLUMN group        VARCHAR(50) NULL AFTER company_id;
```

---

## フロントエンド設計

### AssignmentForm.vue の変更

1. props に `departmentFieldConfigs` を追加（部署変更時にController側から渡す）
2. スロット表示条件:
   ```js
   // config がない部署 = 全スロット表示（後方互換）
   const slotConfig = (slot) => configs.find(c => c.slot === slot) ?? { enabled: true, label: defaultLabels[slot], allowed_item_ids: null }
   ```
3. ドロップダウン選択肢のフィルタリング:
   ```js
   const filteredOptions = (slot, allOptions) => {
       const ids = slotConfig(slot).allowed_item_ids
       return ids ? allOptions.filter(o => ids.includes(o.id)) : allOptions
   }
   ```
4. ラベルは `slotConfig(slot).label` を使用

### 設定UI（Admin / Leader共通コンポーネント）

`DepartmentFieldConfigForm.vue`（新規）を作成し、両画面から利用:

- 4スロット×（ラベル入力 + 有効/無効トグル + 多選択チェックボックス）
- マスタ項目はグループ別に表示（`group` カラムで分類）
- 全選択/解除ボタン付き

---

## フェーズ別タスク

### Phase 1: DB・モデル
- [ ] `department_field_configs` テーブル migration
- [ ] `stages` に `company_id`・`group` 追加 migration
- [ ] `DepartmentFieldConfig` モデル作成
- [ ] `Stage` モデル fillable 更新
- [ ] `Department` モデルに `fieldConfigs()` リレーション追加

### Phase 2: workload-setting グループ対応
- [ ] `WorkloadSettingController` の `typeConfig()` に stages の `group` バリデーション追加
- [ ] WorkloadSetting の Edit.vue（またはIndex.vue）に stages の group 入力欄追加

### Phase 3: Admin 設定 UI
- [ ] `Admin/DepartmentController` に `fieldConfig()` (GET) / `updateFieldConfig()` (POST) 追加
- [ ] `Admin/Departments/Index.vue` に「フィールド設定」ボタン追加
- [ ] `Admin/Departments/FieldConfig.vue`（Inertia ページ）新規作成
- [ ] `routes/web.php` に Admin routes 追加

### Phase 4: Leader 設定 UI
- [ ] Leader 用 DepartmentFieldConfig コントローラーまたは既存コントローラーへのメソッド追加
- [ ] `Leader/DepartmentFieldConfig.vue`（Inertia ページ）新規作成
- [ ] `routes/web.php` に Leader routes 追加

### Phase 5: AssignmentForm.vue 統合
- [ ] Coordinator の `CompositeJobAssignmentController` で department_field_configs を eager load して渡す
- [ ] `AssignmentForm.vue` にスロットconfig対応（ラベル・表示制御・選択肢フィルタ）実装
- [ ] `department_id` 変更時にスロット設定を再描画

### Phase 6: WorkloadAnalyzer 統合
- [ ] WorkloadAnalyzer の集計・表示でカスタムラベルを使用
- [ ] 部署別にフィールドラベルを表示（現状の "作業種別" → 部署設定値）

---

## 変更ファイル一覧

### 新規作成
| ファイル | 種別 |
|---|---|
| `database/migrations/XXXX_create_department_field_configs_table.php` | migration |
| `database/migrations/XXXX_add_group_company_id_to_stages_table.php` | migration |
| `app/Models/DepartmentFieldConfig.php` | Model |
| `resources/js/Pages/Admin/Departments/FieldConfig.vue` | Vue Page |
| `resources/js/Pages/Leader/DepartmentFieldConfig.vue` | Vue Page |
| `resources/js/Components/DepartmentFieldConfigForm.vue` | Vue Component（共通） |

### 変更
| ファイル | 変更内容 |
|---|---|
| `app/Models/Department.php` | `fieldConfigs()` リレーション追加 |
| `app/Models/Stage.php` | `company_id`, `group` を fillable 追加 |
| `app/Http/Controllers/Admin/DepartmentController.php` | fieldConfig GET/POST メソッド追加 |
| `app/Http/Controllers/WorkloadSettingController.php` | stages の group バリデーション追加 |
| `app/Http/Controllers/Coordinator/CompositeJobAssignmentController.php` | field configs を渡す |
| `resources/js/Pages/Admin/Departments/Index.vue` | フィールド設定ボタン追加 |
| `resources/js/Pages/Coordinator/ProjectJobs/JobAssign/AssignmentForm.vue` | スロット config 対応 |
| `resources/js/Pages/WorkloadAnalyzer/Index.vue` | カスタムラベル対応 |
| `routes/web.php` | Admin / Leader field config routes 追加 |

**合計: 新規6 + 変更9 = 15ファイル**

---

## デフォルトラベル（設定なし時のフォールバック）

```js
const DEFAULT_SLOT_LABELS = {
    type:    '作業種別',
    stage:   'ステージ（校数）',
    size:    'サイズ',
    amounts: '数量',
}
```

---

## 注意事項

- `department_id` が `null` のアサインメント（会社管理の場合等）は全スロット表示をフォールバックとする
- `allowed_item_ids` が `null` の場合は全マスタ項目を表示（既存動作）
- サイズは `project_job.size_id` でロックされることがある（`_locked_size`）— この動作は変えない
- `amounts_unit` の選択肢（ページ・ファイル等）は今回の対象外。現行のまま
- workload 分析の**係数（coefficient）**はマスタテーブル側に残る。部署設定は「表示する/しない」「ラベル」のみ

---

## Phase 7: 汎用アイテムプール対応（Option B 拡張）

### 背景・動機

Phase 1〜5 の実装後、各スロットが固定テーブルに縛られており（typeスロット→work_item_types等）、
他部署が全く異なる種別の選択肢を使おうとすると意味論的に正しくない構造になることが判明。
例：営業の「交渉フェーズ」を `sizes` テーブルに入れる、など。
**汎用アイテムテーブル `job_field_options` を新設し、各スロットのデータソースを自由に選べるようにする。**

### 新規テーブル: `job_field_options`

```sql
CREATE TABLE job_field_options (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(255) NOT NULL,
    group_key       VARCHAR(100) NOT NULL DEFAULT '',
    company_id      BIGINT UNSIGNED NULL,
    department_id   BIGINT UNSIGNED NULL,
    coefficient     DECIMAL(6,3) NULL,
    sort_order      INT UNSIGNED NOT NULL DEFAULT 0,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    INDEX idx_company_group (company_id, group_key)
);
```

### `project_job_assignments` への追加カラム（FK なし）

```sql
ALTER TABLE project_job_assignments
    ADD COLUMN field_type_val  BIGINT UNSIGNED NULL,
    ADD COLUMN field_stage_val BIGINT UNSIGNED NULL,
    ADD COLUMN field_size_val  BIGINT UNSIGNED NULL;
```

### `department_field_configs` への追加カラム

```sql
ALTER TABLE department_field_configs
    ADD COLUMN source       VARCHAR(50)  NULL DEFAULT NULL,
    ADD COLUMN source_group VARCHAR(100) NULL DEFAULT NULL;
```

| `source` 値 | 意味 | 保存先カラム |
|---|---|---|
| NULL（省略） | スロット既定テーブル（DTP互換） | `work_item_type_id` / `stage_id` / `size_id` |
| `'job_field_options'` | 汎用アイテムプール | `field_type_val` / `field_stage_val` / `field_size_val` |

`source_group`：`source='job_field_options'` 時にフィルタするグループキー。
`allowed_item_ids` と `source_group` が両方あれば `allowed_item_ids` 優先。

### AssignmentForm.vue の source 対応ロジック

```js
// source='job_field_options' のとき → 新テーブルのアイテムを表示、field_*_val に保存
// source=null のとき → 従来の work_item_type_id / stage_id / size_id に保存（後方互換）
function getSlotBinding(block, slot) {
    const cfg = getSlotConfig(block.department_id, slot)
    if (cfg.source === 'job_field_options') {
        return { items: jobFieldItemsFor(cfg), model: `field_${slot}_val` }
    }
    return { items: legacyItemsFor(slot, block), model: LEGACY_MODEL[slot] }
}
```

### Phase 7 タスク一覧

| # | タスク | 状態 |
|---|---|---|
| 7-1 | `job_field_options` テーブル migration | ⬜ |
| 7-2 | `project_job_assignments` に `field_*_val` 追加 migration | ⬜ |
| 7-3 | `department_field_configs` に `source` / `source_group` 追加 migration | ⬜ |
| 7-4 | `JobFieldOption` モデル作成 | ⬜ |
| 7-5 | `ProjectJobAssignment` モデル fillable/casts 更新 | ⬜ |
| 7-6 | `WorkloadSettingController` に `job_field_options` タイプ追加 | ⬜ |
| 7-7 | `HandleInertiaRequests` に `jobFieldOptions` 共有データ追加 | ⬜ |
| 7-8 | `DepartmentFieldConfigForm.vue` に source/source_group 選択 UI 追加 | ⬜ |
| 7-9 | `AssignmentForm.vue` の source 対応（表示・保存先切り替え） | ⬜ |
| 7-10 | `npm run build` & 動作確認 | ⬜ |

### 変更ファイル（Phase 7 追加分）

| ファイル | 内容 |
|---|---|
| `database/migrations/XXXX_create_job_field_options_table.php` | 新規 |
| `database/migrations/XXXX_add_field_vals_to_project_job_assignments.php` | 新規 |
| `database/migrations/XXXX_add_source_to_department_field_configs.php` | 新規 |
| `app/Models/JobFieldOption.php` | 新規 |
| `app/Models/ProjectJobAssignment.php` | fillable/casts 更新 |
| `app/Http/Controllers/WorkloadSettingController.php` | job_field_options タイプ追加 |
| `app/Http/Middleware/HandleInertiaRequests.php` | jobFieldOptions 追加 |
| `resources/js/Components/DepartmentFieldConfigForm.vue` | source 選択 UI 追加 |
| `resources/js/Pages/Coordinator/ProjectJobs/JobAssign/AssignmentForm.vue` | source 対応 |
