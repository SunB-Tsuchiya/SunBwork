# LABEL5_PROMPT.md — 宛先ラベルPDF生成ツール V2 引継ぎプロンプト

作成日: 2026-06-11  
対象ファイル: `resources/js/Components/Scripts/LabelGeneratorV2.vue`（約1700行）

新セッション開始時、このファイルを読み込んでから作業に入ること。  
併せて `z_instructions/LABEL_ALGORITHM.md`（アルゴリズム・DB設計）・`z_instructions/LABEL_PLAN5.md`（V2設計書）も参照すること。

---

## プロジェクト概要

清水製版の発送業務で使う宛先ラベルPDFを、ブラウザ内で自動生成するツール。  
日能研テストの教室別・アイテム別・学年別にPDFを出力する。

- V2ページ: `resources/js/Components/Scripts/LabelGeneratorV2.vue`
- V1ページ: `resources/js/Components/Scripts/LabelGenerator.vue`（OCR機能付き。V2と独立）
- ルート: `scripts.show` → `Show.vue` の componentMap に `'LabelGeneratorV2'` で登録済み
- スクリプトDB: `scripts` テーブルの `component_name = 'LabelGeneratorV2'`

---

## V2 の動作概要

```
1. Excelファイル (s1_*.xls) を選択
   → parseWorkbook でシート解析
   → 教室コード・部数・学年・アイテム記号・実施日を抽出
2. pdfGroups (実施日×学年 の一覧) が表示される
3. 「PDF生成」ボタン → generatePdfs → A5横ラベルPDF（jsPDF）
4. ダウンロードまたはフォルダ保存
```

---

## V2 に実装済みの機能（2026-06-11 時点）

### マスタ管理モーダル（4タブ）

ヘッダーの「マスタ」エリアにボタンが並ぶ。各ボタンから直接そのタブを開く。

| ボタン | タブ | 件数 | 特記 |
|---|---|---|---|
| 試験名マスタ | testNames | 52件 | チェックOFFで非表示（セレクター・PDF生成に出ない） |
| 教室マスタ | schools | 104件 | ヘッダークリックでソート。デフォルト=stop_order順 |
| アイテムマスタ | itemTypes | 35件 | チェックOFFで非表示 |
| 社内便マスタ | routes | 20ルート | Excelグリッド形式。セルクリックで編集パネル |

### 社内便マスタ（label_routes + label_route_stops）

- 20ルート / 122停留所。コース1（A1〜I2）/ コース2（A2〜G土便）
- セル色: honbu(amber) / kanto(green) / busho(yellow) / henkou(pink) / kakunin(red) / ng(sky)
- 凡例を表示中
- セル編集: 教室名・コード・時刻・備考・色カテゴリ
- 挿入: ↑挿入（上に新セル）/ ↓挿入（下に新セル）← ORDER BY DESC で重複キー回避
- 削除: 削除↑詰（下の行が上にシフト）

---

## DB テーブル（label_* 系）

```
label_school_masters   - 教室コード・名前・ルート・stop_order
label_test_names       - テスト名・is_active
label_item_types       - アイテム種別・is_active（z_shimizu_seihan/アイテムマスタ.txt から投入済み）
label_subjects         - 科目名（現状未使用）
label_routes           - 社内便ルート（code, course, day1, sort_order...）
label_route_stops      - 停留所（route_id, stop_order, school_code FK, color_category...）
```

**stop_order エンコード:** `route.sort_order * 100 + stop.stop_order`  
→ label_school_masters.stop_order がこの値に同期されている

---

## 主要ファイル

| ファイル | 役割 |
|---|---|
| `resources/js/Components/Scripts/LabelGeneratorV2.vue` | メイン（約1700行） |
| `app/Http/Controllers/LabelMasterController.php` | マスタCRUD API |
| `app/Models/LabelRoute.php` | 社内便ルートモデル |
| `app/Models/LabelRouteStop.php` | 社内便停留所モデル |
| `app/Models/LabelSchoolMaster.php` | 教室マスタモデル |
| `database/seeders/LabelMasterSeeder.php` | マスタ初期データ投入 |
| `z_shimizu_seihan/社内便_ルート一覧_2025.1001～.xlsx` | 社内便ルート元データ |
| `z_shimizu_seihan/アイテムマスタ.txt` | アイテム種別リスト |
| `z_shimizu_seihan/test名一覧.txt` | テスト名リスト |
| `z_instructions/LABEL_ALGORITHM.md` | アルゴリズム詳細・DB設計（必読） |

---

## API ルート（routes/web.php, label-masters グループ）

```
GET    /label-masters/schools
POST   /label-masters/schools
PUT    /label-masters/schools/{school}
DELETE /label-masters/schools/{school}

GET    /label-masters/test-names
POST   /label-masters/test-names
PUT    /label-masters/test-names/{testName}
DELETE /label-masters/test-names/{testName}

GET    /label-masters/item-types
POST   /label-masters/item-types
PUT    /label-masters/item-types/{itemType}
DELETE /label-masters/item-types/{itemType}

GET    /label-masters/routes
POST   /label-masters/routes
PUT    /label-masters/routes/{route}
DELETE /label-masters/routes/{route}

POST   /label-masters/routes/{route}/stops          (追加 or updateOrCreate)
PUT    /label-masters/route-stops/{routeStop}       (更新)
POST   /label-masters/routes/{route}/stops/insert-at (挿入+シフト)
DELETE /label-masters/route-stops/{routeStop}/shift  (削除+シフト)
DELETE /label-masters/route-stops/{routeStop}        (単純削除)
```

---

## 既知の注意事項

1. **社内便マスタ挿入の重複キー**: `increment()` は昇順実行 → `UNIQUE(route_id, stop_order)` 違反。
   `LabelMasterController::stopsInsertAt` では `DB::statement` + `ORDER BY stop_order DESC` で解決済み。

2. **教室マスタの area カラム**: バリデーションは `nullable`、DB保存時は `$data['area'] ?? ''` で空文字に変換（NOT NULL制約あり）。

3. **社内便Excelは setReadDataOnly(true) 不可**: セル背景色を読むためスタイルが必要。巨大Excelには使えないが、社内便ファイルは20ルート分の小ファイルなので問題なし。

4. **アイテム記号スキャン時は NFKC 禁止**: `normalize('NFKC')` を適用すると `①` が `1` に変換される。`ITEM_SYMBOLS.includes(raw[0])` で検出する部分では生文字列を使うこと（LABEL_ALGORITHM.md §14-4 参照）。

5. **さくら本番**: CSRF トークンは `meta[name="csrf-token"]` から取得すること（XSRF-TOKEN クッキーは発行されない）。

---

## 未実装・今後の課題

- 一式PDFの内容生成（一式シートの読み取り）
- V2 ラベル生成時に label_route_stops DB から routeCode/stop を動的取得（現状: FALLBACK_ROUTE_MAP固定）
- 学年ラベルオーバーライド（マイファースト → 新3年 等）
- 教室表示名オーバーライド（GA → 日能研小田原 等）
