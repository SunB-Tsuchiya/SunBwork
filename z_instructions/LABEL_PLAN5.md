# LABEL_PLAN5.md — 宛先ラベルPDF生成ツール V2 設計書

作成日: 2026-06-11

---

## 概要

LabelGeneratorV2.vue の現時点の完全な設計記録。  
V2 は V1（LabelGenerator.vue）とは独立した別ファイル。清水製版向け。

---

## V2 マスタ管理システム 実装記録（2026-06-11 完了）

### 実装フェーズ

| フェーズ | 内容 | 状態 |
|---|---|---|
| V2-M1 | テスト名マスタ: test名一覧.txt から投入・表示/非表示チェック | ✅ 完了 |
| V2-M2 | 教室マスタ: stop_order 順ソート・ヘッダークリックでカラムソート | ✅ 完了 |
| V2-M3 | 社内便マスタ DB 設計（label_routes / label_route_stops） | ✅ 完了 |
| V2-M4 | 社内便Excel解析シーダー（色カテゴリ含む） | ✅ 完了 |
| V2-M5 | 社内便マスタ Vue グリッド UI + セル編集 | ✅ 完了 |
| V2-M6 | 停留所 挿入（ORDER BY DESC回避）/ 削除シフト | ✅ 完了 |
| V2-M7 | セル色分け・凡例・色カテゴリ選択 | ✅ 完了 |
| V2-M8 | アイテムマスタ: アイテムマスタ.txt 投入・表示/非表示 | ✅ 完了 |

---

## DB 設計

### label_routes

```sql
CREATE TABLE label_routes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) UNIQUE NOT NULL,
    course TINYINT NOT NULL,           -- 1: コース1, 2: コース2
    area VARCHAR(50) NOT NULL DEFAULT '',
    day1 VARCHAR(20) NOT NULL DEFAULT '',
    day1_start VARCHAR(50) NOT NULL DEFAULT '',
    day2 VARCHAR(20) NULL,
    day2_start VARCHAR(50) NULL,
    sort_order TINYINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

### label_route_stops

```sql
CREATE TABLE label_route_stops (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    route_id BIGINT UNSIGNED NOT NULL REFERENCES label_routes(id) ON DELETE CASCADE,
    stop_order TINYINT NOT NULL,
    school_code VARCHAR(10) NULL,       -- label_school_masters.code への外部キー（nullable）
    school_name VARCHAR(150) NOT NULL DEFAULT '',
    arrival_time VARCHAR(10) NULL,      -- HH:MM 形式
    notes VARCHAR(200) NULL,
    color_category VARCHAR(20) NULL,    -- honbu/kanto/busho/henkou/kakunin/ng
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY (route_id, stop_order)
);
```

---

## Excel 解析仕様（社内便_ルート一覧_2025.1001～.xlsx）

### 列マッピング

```
列インデックス（0始まり）:
  A: [1, 2] (name, code)
  B: [3, 4]
  C: [5, 6]
  D: [7, 8]
  E: [9, 10]
  F: [11, 12]
  G: [13, 14]
  H: [15, 16]
  I: [17, 18]
  EXTRA: [21, 22]  （K→ルートなどの追加）
```

### ソート順（ROUTE_SORT）

```
A1=1, B1=2, C1=3, D1=4, E1=5, F1=6, G1=7, H1=8, I1=9, G水便=10,
A2=11, B2=12, C2=13, D2=14, E2=15, F2=16, G2=17, H2=18, I2=19, G土便=20
```

### 停留所番号解析

- 「B-1」「停留所 3」のような行からルート文字と番号を取得
- ハイフン除去: `B-1` → `B1`
- 時刻: Excelの小数値（0〜1.0）または文字列 → `HH:MM`
  - 小数: `round(value * 24 * 60)` 分 → `H:MM` フォーマット

---

## コントローラー API 実装

```
LabelMasterController::stopsInsertAt(Request, LabelRoute)
  → ORDER BY DESC で increment（重複キー回避）
  → 新規空白停留所を作成
  → Route の stops を eager load して返す

LabelMasterController::stopsDestroyShift(LabelRouteStop)
  → 削除後、上位の stop_order を decrement
  → Route を再取得して返す
```

---

## Vue コンポーネント設計（LabelGeneratorV2.vue 関連部分）

### マスタ State

```js
const testNameMaster  = ref([]);  // { id, name, isActive }
const itemTypeMaster  = ref([]);  // { id, name, isActive }
const schoolMaster    = ref([]);  // { id, code, name, route, stopOrder }
const routeMaster     = ref([]);  // LabelRoute with nested stops[]
const masterTab       = ref('testNames');  // 'testNames'|'schools'|'itemTypes'|'routes'
```

### 社内便マスタ固有 State

```js
const routeCourse   = ref(1);     // 表示コース 1 or 2
const editingStop   = ref(null);  // { routeId, routeCode, stopOrder, id?, school_name, school_code, arrival_time, notes, color_category }
const editingRoute  = ref(null);  // ルートヘッダー編集

const CELL_COLOR = {
    honbu:'bg-amber-100', kanto:'bg-green-100', busho:'bg-yellow-100',
    henkou:'bg-pink-100', kakunin:'bg-red-100', ng:'bg-sky-100',
};
const LEGEND = [
    { key:'honbu', label:'本部系教室', cls:'bg-amber-200' },
    { key:'kanto', label:'関東系教室', cls:'bg-green-200' },
    { key:'busho', label:'部署等',     cls:'bg-yellow-200' },
    { key:'henkou',label:'変更',       cls:'bg-pink-200' },
    { key:'kakunin',label:'確認',      cls:'bg-red-200' },
    { key:'ng',    label:'NG便',       cls:'bg-sky-200' },
];
```

### computed

```js
activeRoutes      // routeMaster.value.filter(r => r.course === routeCourse.value)
course1Routes     // course 1 のルート
course2Routes     // course 2 のルート
activeStopOrders  // 実データの stop_order の Set + min[2..10] → ソート済み配列
```

### 主要関数

| 関数 | 役割 |
|---|---|
| `loadMasters()` | 全マスタを並列取得（4エンドポイント） |
| `openMasterModal(tab)` | タブ指定でモーダルを開く（schoolsは sort リセット） |
| `masterToggleActive(item, tab)` | testNames / itemTypes の表示切替を即時保存 |
| `masterSaveEdit(tab)` | schools / itemTypes / testNames の編集を PUT で保存 |
| `masterSaveAdd()` | _tab に応じて POST で新規追加 |
| `masterDelete(tab, id)` | 対応エンドポイントに DELETE |
| `openStopEdit(route, stopOrder)` | 停留所セルクリック → editingStop に設定 |
| `saveStopEdit()` | PUT（既存ID）or POST（新規）で停留所を保存 |
| `insertStopAt(offset)` | offset=0: 上挿入, offset=1: 下挿入 |
| `deleteStopShift()` | 停留所削除（下の行を上にシフト） |
| `cellColorClass(stop)` | color_category → Tailwind クラス |
| `getStop(route, stopOrder)` | ルート+番号でstopオブジェクトを取得 |

---

## 今後の課題

- [ ] ラベル生成時に label_route_stops DB からルートコード/停車順を動的取得（現状: FALLBACK_ROUTE_MAP固定）
- [ ] 一式PDFの内容生成
- [ ] 学年ラベルオーバーライド
- [ ] 教室表示名のオーバーライド（GA → 日能研小田原）
- [ ] 科目マスタ（label_subjects）の活用
