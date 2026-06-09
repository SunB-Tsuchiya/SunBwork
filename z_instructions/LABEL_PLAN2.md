# LABEL_PLAN2.md — 宛先ラベルPDF生成ツール Phase 2 設計書
# マスタデータDB化・管理UI追加

作成日: 2026-06-07

---

## 1. フェーズ概要

Phase 1 でハードコードしていた以下をDBで管理し、担当者がブラウザから編集できるようにする。

| 現状（ハードコード） | Phase 2 後 |
|---|---|
| CODE_DISPLAY_NAMES（教室表示名） | label_school_masters テーブル |
| DEFAULT_ROUTE_MAP（ルートコード） | label_school_masters テーブル |
| PRESETS の subject / itemLabel | label_subjects / label_item_types テーブル |
| テスト名なし（手入力のみ） | label_test_names テーブル + ドロップダウン |

---

## 2. DBテーブル設計

### 2-1. label_school_masters（教室マスタ）

```sql
CREATE TABLE label_school_masters (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code         VARCHAR(20) NOT NULL UNIQUE,   -- AA, AS_1, AS_2, $tokai ...
    display_name VARCHAR(150) NOT NULL,         -- 赤羽校、日能研小田原、表参道校 ...
    area         VARCHAR(50)  NOT NULL DEFAULT '',  -- 関東/東海/関西/四国/九州・沖縄/北海道/中国
    route        VARCHAR(10)  DEFAULT NULL,     -- A1〜I2（関東ルート）
    stop_order   TINYINT UNSIGNED DEFAULT NULL, -- ルート内順番（1〜6）
    is_active    TINYINT(1)   NOT NULL DEFAULT 1,
    notes        TEXT         DEFAULT NULL,     -- 重複コード備考など
    created_at   TIMESTAMP    NULL,
    updated_at   TIMESTAMP    NULL
);
```

**初期データ: 170件 + 特殊コード3件**

| コード | 表示名 | 備考 |
|---|---|---|
| AS_1 | 渋谷校 | 元コードAS（要担当確認） |
| AS_2 | 表参道校 | 元コードAS（要担当確認） |
| $tokai | 日能研東海本部 | 合成コード（特殊行） |
| $julius | ユリウス・アトラス分 | 合成コード（特殊行） |
| $yobi | 予備 | 合成コード（特殊行） |

### 2-2. label_test_names（テスト名マスタ）

```sql
CREATE TABLE label_test_names (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(200) NOT NULL,
    sort_order SMALLINT     NOT NULL DEFAULT 0,
    is_active  TINYINT(1)   NOT NULL DEFAULT 1,
    created_at TIMESTAMP    NULL,
    updated_at TIMESTAMP    NULL
);
```

初期データ: 54件（テスト名.txtより）

### 2-3. label_subjects（科目マスタ）

```sql
CREATE TABLE label_subjects (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    sort_order SMALLINT     NOT NULL DEFAULT 0,
    is_active  TINYINT(1)   NOT NULL DEFAULT 1,
    created_at TIMESTAMP    NULL,
    updated_at TIMESTAMP    NULL
);
```

初期データ: 27件（科目.txtより）

### 2-4. label_item_types（内容マスタ）

```sql
CREATE TABLE label_item_types (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(200) NOT NULL,
    sort_order SMALLINT     NOT NULL DEFAULT 0,
    is_active  TINYINT(1)   NOT NULL DEFAULT 1,
    created_at TIMESTAMP    NULL,
    updated_at TIMESTAMP    NULL
);
```

初期データ: 38件（内容.txtより）

---

## 3. API設計（routes/web.php に追加）

```
GET    /label-masters/schools          → JSON 全教室一覧
POST   /label-masters/schools          → 教室追加
PUT    /label-masters/schools/{id}     → 教室更新
DELETE /label-masters/schools/{id}     → 教室削除（論理削除）

GET    /label-masters/test-names       → テスト名一覧
POST   /label-masters/test-names       → 追加
PUT    /label-masters/test-names/{id}  → 更新
DELETE /label-masters/test-names/{id}  → 削除

同様: /label-masters/subjects / /label-masters/item-types
```

ミドルウェア: `auth` のみ（全ログインユーザーが参照・編集可）

---

## 4. フロントエンド変更

### 4-1. LabelGenerator.vue の変更

**廃止するハードコード:**
- `CODE_DISPLAY_NAMES` → DBから取得
- `DEFAULT_ROUTE_MAP` → DBから取得
- `NO_SUFFIX_KEYWORDS` → displayName は DB の display_name をそのまま使う

**追加するUI:**
1. Excel取込後（Step 2）にテスト名ドロップダウン追加
2. マスタ管理タブ（教室・テスト名・科目・内容の一覧表示と編集）

**マスタデータの取得方法:**
- コンポーネント mount 時に `axios.get('/label-masters/schools')` 等で取得
- `ref` で保持、以後はDB不要（ページリロードまでキャッシュ）

### 4-2. マスタ管理タブ UI イメージ

```
[ツール] [マスタ管理]
         ↓
  [教室マスタ] [テスト名] [科目] [内容]
  ┌──────────────────────────────────┐
  │ コード  表示名      エリア  ルート │
  │ DL     赤羽校      関東    A1    [編集][削除] │
  │ DG     川口校      関東    A1    [編集][削除] │
  │ ...                                          │
  │ [+ 追加]                                     │
  └──────────────────────────────────┘
```

---

## 5. 変更ファイル一覧

| # | ファイル | 種別 | 内容 |
|---|---|---|---|
| 1 | `database/migrations/xxxx_create_label_school_masters_table.php` | 新規 | 教室マスタ |
| 2 | `database/migrations/xxxx_create_label_test_names_table.php` | 新規 | テスト名 |
| 3 | `database/migrations/xxxx_create_label_subjects_table.php` | 新規 | 科目 |
| 4 | `database/migrations/xxxx_create_label_item_types_table.php` | 新規 | 内容 |
| 5 | `app/Models/LabelSchoolMaster.php` | 新規 | Eloquentモデル |
| 6 | `app/Models/LabelTestName.php` | 新規 | Eloquentモデル |
| 7 | `app/Models/LabelSubject.php` | 新規 | Eloquentモデル |
| 8 | `app/Models/LabelItemType.php` | 新規 | Eloquentモデル |
| 9 | `app/Http/Controllers/LabelMasterController.php` | 新規 | CRUD API |
| 10 | `database/seeders/LabelMasterSeeder.php` | 新規 | 初期データ投入 |
| 11 | `routes/web.php` | 変更 | APIルート追加 |
| 12 | `resources/js/Components/Scripts/LabelGenerator.vue` | 変更 | DB連携・マスタ管理UI |

---

## 6. AS コード重複問題の対処

現状: 渋谷と表参道が同じコード「AS」

**Phase 2 での対処:**
- DB では `AS_1`（渋谷校）/ `AS_2`（表参道校）として別レコード作成
- `notes` 列に「元コードAS（担当確認要）」と記載
- Excel パース時: 同コードが複数行にある場合 `${code}_${rowIndex}` で自動分離（既存ロジック維持）
- 担当確認後、コードが確定したら管理UIから修正

---

## 7. 初期データ元ファイル

| データ | ファイル |
|---|---|
| 教室マスタ | `Shimizu_Seihan/school_master_draft.csv` |
| テスト名 | `Shimizu_Seihan/filemakerファイル_forClaude/テスト名.txt` |
| 科目 | `Shimizu_Seihan/filemakerファイル_forClaude/科目.txt` |
| 内容 | `Shimizu_Seihan/filemakerファイル_forClaude/内容.txt` |

---

## 8. 未対応事項（Phase 3 以降）

- プリセット（アイテム組み合わせ）のDB管理
- エリア別部数集計の表示（FileMakerの「部数集計」機能）
- パターンB（学習力育成テスト型）の学年別実施日入力
