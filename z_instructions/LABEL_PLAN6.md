# LABEL_PLAN6.md — データ照会・部数集計・エリアマスタ 設計書

作成日: 2026-06-12

---

## 概要

LabelGeneratorV2 に以下を追加する:

1. **エリアマスタ** — `label_area_masters` テーブル + Master UI タブ（社内便マスタの右）
2. **データ照会ビュー** — Excelロード後に見られる学校×部数一覧（FileMaker 風）
3. **部数集計モーダル** — エリア別・シート別集計

---

## フェーズ別タスク

### Phase 6-1: エリアマスタ DB + Master UI

**DB Migration**: `label_area_masters`
```sql
id, name VARCHAR(100), sort_order SMALLINT DEFAULT 0, is_active BOOLEAN DEFAULT true, timestamps
UNIQUE(name)
```

**Seeder**: `LabelAreaSeeder.php` — エリアマスタ.txt の9件を sort_order 1〜9 で投入
```
1: 本部
2: 東海本部
3: 本部部署分
4: 関東
5: 関東スタッフ
6: 関西
7: 九州
8: 本部職員
9: 東海本部職員
```

**Model**: `app/Models/LabelAreaMaster.php`

**Controller**: `LabelMasterController` に CRUD 4メソッド + reorder対応（既存 reorder エンドポイントに `areaMasters` 分岐追加）

**Routes** (`label-masters` グループ):
```
GET/POST  /area-masters
PUT/DELETE /area-masters/{area}
```

**Vue**:
- `areaMaster = ref([])` state 追加
- `mapArea()` 追加
- `loadMasters()` に並列追加
- ヘッダーボタン「エリアマスタ（N件）」— 社内便マスタの右
- モーダルタブ「エリアマスタ」— 社内便マスタの右
- タブパネル: ↑↓ボタン + チェックボックス + 名前 + 編集/削除（isshikiDestinations と同じ UI）
- `masterMoveItem('areaMasters', ...)` に分岐追加

---

### Phase 6-2: Excel エリア解析 + データ照会ビュー

#### 2-1. Excel エリア解析アルゴリズム

`parseSchoolRows` を拡張して各学校に `area` を付与する。

**アルゴリズム（2パス）:**

```js
// Pass 1: ～小計 行を全て検出し、各セクションの (開始行, エリア名) を構築
// 「本部小計」→ area='本部', 境界行=r
// 「関東スタッフ小計」→ area='関東スタッフ'
// 「総合計」「合計」「本部計」のような集計行は無視

const sectionBoundaries = [];  // [{ boundary: rowIdx, area: '本部' }, ...]
for (let r = 0; r < data.length; r++) {
    const row = data[r] || [];
    for (const c of [0, 1, 2]) {
        const raw = String(row[c] || '').normalize('NFKC').trim();
        // "本部小計" → match[1]='本部'
        // "本部部署分小計" → match[1]='本部部署分'
        const m = raw.match(/^(.+?)(?:小計|本部計)$/);
        if (m && !/^(総合|合)$/.test(m[1])) {
            sectionBoundaries.push({ boundary: r, area: m[1] });
            break;
        }
    }
}

// Pass 2: 各学校の rowIdx に対して、最初に rowIdx より大きい boundary を持つ
// sectionBoundaries エントリの area を取得する
function getAreaForRow(rowIdx) {
    for (const { boundary, area } of sectionBoundaries) {
        if (boundary > rowIdx) return area;
    }
    return '';
}
```

**変更**: `parseSchoolRows` の返り値に `area` フィールド追加:
```js
schools[schoolKey] = { code, name, grades, rowIdx, colIdx, area: '' };  // area は後で設定
// ... 全行スキャン後:
for (const school of Object.values(schools)) {
    school.area = getAreaForRow(school.rowIdx);
}
```

#### 2-2. データ照会ビュー

**新 view state**: `currentView = 'config' | 'generated' | 'dataview'`

**state 追加**:
```js
const dataviewDate  = ref('');       // 選択中 MMDD ('0330' / '__common')
const dataviewGrade = ref('');       // 選択中学年 ('3年'...)
const dataviewSym   = ref('');       // 選択中シンボル ('①'...)
const showSummaryModal = ref(false); // 部数集計モーダル表示フラグ
```

**computed: `dataviewRows`**
```js
// 選択中 date+sym の全教室データを返す
// [{ code, name, area, qty, hasData }]
// ソート: エリアマスタ sort_order → route/stop_order → Excel 行順
```

**UI レイアウト**（AppLayout のデフォルトスロット内）:
```
[戻る]  [学年ボタン群: 3年 4年 5年 6年]  [シンボルボタン群: ① ②...]  [部数集計]
─────────────────────────────────────────────────────────────────
コード | 教室名印刷 | エリア | 部数
─────────────────────────────────────────────────────────────────
DL    | 赤羽校     | 本部   | 30          ← qty > 0: 通常表示
DX    | 春日部校   | 本部   | [赤セル]   ← qty = 0: bg-red-400
...
```

**「データ照会」ボタン**の配置: Excel ロード後に表示されるボタン群（config view の下部）に追加。
`currentView.value = 'dataview'` で遷移。

---

### Phase 6-3: 部数集計モーダル

**`showSummaryModal`** で制御。

**集計データ構造 (`summaryData` computed)**:
```js
// 選択中 date + grade に対して:
// {
//   areas: [
//     { name: '本部', syms: { '①': 100, '②': 80 }, total: 180 },
//     ...
//   ],
//   symTotals: { '①': 4100, '②': 3500 },
//   grandTotal: 7600,
// }
```

**エリアの並び**: `areaMaster` の `sort_order` 順。スクール master に存在しないエリアは末尾。

**モーダル UI**:
```
エリア別部数集計          学年: [3年] [4年] [5年] [6年]
──────────────────────────────────────────────
エリア    | ①  | ②  | ③  | 合計
──────────────────────────────────────────────
本部      | 100| 80 | 60 | 240
東海本部  | 40 | 35 | 25 | 100
...
──────────────────────────────────────────────
総計      | 4100|3500|2800| 10400
```

**シートの総合計** = 各シンボルのエリア合計の sum（= symTotals[sym]）

---

## 変更ファイル一覧

| ファイル | 変更内容 |
|---|---|
| `database/migrations/2026_06_12_*_create_label_area_masters_table.php` | 新規 |
| `database/seeders/LabelAreaSeeder.php` | 新規 |
| `app/Models/LabelAreaMaster.php` | 新規 |
| `app/Http/Controllers/LabelMasterController.php` | エリアCRUD + reorder分岐追加 |
| `routes/web.php` | エリアルート追加 |
| `resources/js/Components/Scripts/LabelGeneratorV2.vue` | 大規模追加（～300行） |
| `z_instructions/LABEL_ALGORITHM.md` | エリア解析アルゴリズム追記 |

---

## データ・部数の違いについて

**ユーザーの質問**: 「データと部数は同じだと思うが、アルゴリズムなどのデータで違いがありそうなら指摘してほしい」

**回答**: V2 では同じです。
- `データ` = Excel から読み取った生部数 (qty)
- `部数` = データと同じ (マスタ調整なし)
- `splitBoxes(qty, maxBox)` は箱を分けるだけで合計部数は変わらない

→ 「部数」1列のみ表示で十分。

---

## 未解決・確認事項

1. **「エクセルの表の再分析が必要」** — 現在の `parseSchoolRows` はエリア情報を取得しない。Phase 6-2 の2パス方式で対応予定。

2. **エリアマスタの名前 vs Excel の小計テキスト** — 例: Excel に「東海本部小計」→ area='東海本部'、エリアマスタに '東海本部' → 一致。現在の school master の area='東海' は修正対象（Seeder 更新 or 無視してExcel解析値を使う）。

3. **データ照会のシンボル切替** — シンボルが多い場合（①〜⑥など）はボタン群として表示。

4. **部数集計のグラニュラリティ** — 選択中の date+grade について、全シンボルを横に並べてエリア別集計を表示する方式を採用。

