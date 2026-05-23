# 複合ジョブ作成機能 設計仕様書

> 対象実装者への注意: 本書は実装者（人間・AI問わず）が迷わず作業できるよう、
> 設計の意図・理由・制約を詳細に記載している。実装前に全文を読むこと。

---

## 1. 機能概要

### 目的
数十件のファイルを一度に処理する作業（PDF校正・画像加工・ファイル整理等）を
CoordinatorまたはUserが **ファイルをドラッグ＆ドロップするだけで** 素早く記録・依頼できるようにする。

### 通常ジョブとの違い

| 項目 | 通常ジョブ | 複合ジョブ |
|------|-----------|-----------|
| 作業対象 | 1件の作業 | 複数ファイルをまとめた1つの作業 |
| 数量入力 | 手動でページ数/ファイル数を入力 | ファイル読み込みで自動集計 |
| ファイル情報 | なし | JSON形式でDBに保存 |
| job_type | null or 'proof' | **'composite'** |

### アクセスロール
- **Coordinator**: 他ユーザーへの割当・自己割当両方
- **User**: 自己割当（MyJobBox経由）
- Leader / Clerk: 将来検討（今回は対象外）

---

## 2. 技術方針：クライアントサイドJS完結

**ファイルはサーバーに送信しない。** ブラウザのFile APIでバイナリを読み込み、
メタデータのみをフォームデータとして送信する。

### 使用ライブラリ（npm install が必要）

```bash
npm install pdfjs-dist jszip
```

> ag-psd は使用しない。PSDはヘッダー26バイト直読みで対応（後述）。

| ライブラリ | 用途 |
|-----------|------|
| `pdfjs-dist` | PDF / AI のページ数・ページサイズ取得 |
| `jszip` | DOCX / INDD のZIP解析（ページ数・ドキュメントサイズ取得） |

### ファイル種別ごとの取得情報

| 種別 | 拡張子 | 取得情報 | 手法 |
|------|--------|---------|------|
| PDF | .pdf | ページ数・ドキュメントサイズ・ファイルサイズ | pdfjs-dist |
| Word | .docx | ページ数・ドキュメントサイズ・ファイルサイズ | jszip（w:pgSz） |
| InDesign | .indd | ページ数・ドキュメントサイズ・ファイルサイズ | jszip（CS4以降） |
| Illustrator | .ai | ページ数・ドキュメントサイズ・ファイルサイズ | pdfjs-dist（CS以降はPDFベース） |
| Photoshop | .psd / .psb | 幅×高さ px・カラーモード・ビット深度・ファイルサイズ | ヘッダー26バイト直読み |
| 画像 | .jpg .png .tiff .gif | 幅×高さ px・ファイルサイズ | Image要素 / ヘッダー読み |
| EPS | .eps | BoundingBox・ファイルサイズ | テキスト先頭解析 |
| その他 | .zip .xlsx等 | ファイル名・拡張子・ファイルサイズのみ | ─ |

### ドキュメントサイズの判定（PDF / INDD / AI / Word 共通）

ページ寸法をmm換算後、以下の標準サイズと照合（許容誤差±2mm）。
一致しない場合は「210×297mm」のように数値で表示。

| 名称 | 幅mm | 高さmm |
|------|------|-------|
| A3 | 297 | 420 |
| A4 | 210 | 297 |
| A5 | 148 | 210 |
| B4(JIS) | 257 | 364 |
| B5(JIS) | 182 | 257 |
| B6(JIS) | 128 | 182 |
| Letter | 216 | 279 |

- PDF / AI: `pdfjs-dist` の `page.getViewport({scale:1})` → ポイント → mm（÷72×25.4）
- Word: `word/document.xml` の `<w:pgSz w:w="..." w:h="..."/>` → twips → mm（÷1440×25.4）
- INDD: スプレッドXMLのページ寸法 → ポイント → mm

### ファイルタイプ判別（拡張子 + マジックバイト）
拡張子偽装対策として、先頭バイトで実際の形式を確認する。

```
PDF: 先頭4バイト = 25 50 44 46 (%PDF)
PSD: 先頭4バイト = 38 42 50 53 (8BPS)
ZIP系(INDD/DOCX): 先頭4バイト = 50 4B 03 04 (PK\x03\x04)
AI(旧形式): 先頭2バイト = 25 21 (%!)
```

### ファイル件数制限
- 最大 **50ファイル** まで。超過時は処理せずアラートを表示:
  `「一度に読み込めるファイルは50件までです。${count}件選択されています。」`

### PSDヘッダー直読み実装

```js
async function readPsdHeader(file) {
  const buf = await file.slice(0, 26).arrayBuffer()
  const view = new DataView(buf)
  const magic = String.fromCharCode(
    view.getUint8(0), view.getUint8(1), view.getUint8(2), view.getUint8(3)
  )
  if (magic !== '8BPS') return null
  const colorModeMap = { 1:'グレースケール', 3:'RGB', 4:'CMYK', 9:'Lab' }
  return {
    version:   view.getUint16(4),       // 1=PSD, 2=PSB
    height:    view.getUint32(14),
    width:     view.getUint32(18),
    bitDepth:  view.getUint16(22),
    colorMode: colorModeMap[view.getUint16(24)] ?? `mode${view.getUint16(24)}`,
  }
}
```

---

## 3. DBスキーマ変更

### 3-1. マイグレーション（新規作成）

```
database/migrations/YYYY_MM_DD_xxxxxx_add_file_info_to_project_job_assignments.php
```

```php
Schema::table('project_job_assignments', function (Blueprint $table) {
    $table->json('file_info')->nullable()->after('job_type');
});
```

### 3-2. file_info のJSON構造

```json
{
  "total_files": 25,
  "total_pages": 142,
  "total_size_bytes": 52428800,
  "summary": "PDF×10(42p/A4) / INDD×8(68p/B5) / PSD×7",
  "groups": {
    "pdf":  { "count": 10, "pages": 42, "size_bytes": 10485760, "doc_size": "A4" },
    "indd": { "count": 8,  "pages": 68, "size_bytes": 20971520, "doc_size": "B5" },
    "psd":  { "count": 7,  "pages": null, "size_bytes": 21474836, "doc_size": null }
  },
  "files": [
    {
      "name": "catalog_vol3.pdf",
      "ext": "pdf",
      "size": 5242880,
      "pages": 12,
      "doc_size": "A4",
      "width": null,
      "height": null,
      "extra": null
    },
    {
      "name": "layout_cover.indd",
      "ext": "indd",
      "size": 10485760,
      "pages": 4,
      "doc_size": "B5",
      "width": null,
      "height": null,
      "extra": null
    },
    {
      "name": "photo_001.psd",
      "ext": "psd",
      "size": 20971520,
      "pages": null,
      "doc_size": null,
      "width": 3508,
      "height": 2480,
      "extra": "CMYK 8bit"
    }
  ]
}
```

### 3-3. ProjectJobAssignment モデルへの追記

`$fillable` に `'file_info'` を追加。
`$casts` に `'file_info' => 'array'` を追加。

---

## 4. ルーティング

### Coordinator側（`routes/web.php` のCoordinatorグループに追加）

```php
Route::get('/project-jobs/{projectJob}/assignments/composite/create',
    [CompositeJobAssignmentController::class, 'create'])
    ->name('coordinator.project_jobs.assignments.composite.create');

Route::post('/project-jobs/{projectJob}/assignments/composite',
    [CompositeJobAssignmentController::class, 'store'])
    ->name('coordinator.project_jobs.assignments.composite.store');
```

### Userルートは第2フェーズ
User側は自己割当フォーム（`Create_user.vue`）から遷移する別ページとして後日追加。
今回はCoordinatorルートのみ実装する。

---

## 5. コントローラー

### 5-1. 新規作成

```
app/Http/Controllers/Coordinator/CompositeJobAssignmentController.php
```

#### create() メソッド
- `ProjectJob` を取得し、クライアント・プロジェクト情報をInertiaで渡す
- 既存の `ProjectJobAssignmentsController@create` と同様のprops構成（members, difficulties, workItemTypes, stages, sizes, statuses等）

#### store() メソッド
バリデーション項目:
```php
'user_id'            => 'nullable|exists:users,id',
'title_suffix'       => 'nullable|string|max:255',
'detail'             => 'nullable|string',
'work_item_type_id'  => 'nullable|exists:work_item_types,id',
'stage_id'           => 'nullable|exists:stages,id',
'difficulty_id'      => 'nullable|exists:difficulties,id',
'desired_end_date'   => 'nullable|date',
'desired_time_hour'  => 'nullable|string',
'desired_time_min'   => 'nullable|string',
'estimated_hours'    => 'nullable|numeric|min:0',
'file_info'          => 'nullable|string',   // JSON文字列として受信
'amounts'            => 'nullable|integer|min:0',
'amounts_unit'       => 'nullable|in:page,file',
```

保存時の固定値:
```php
'job_type'  => 'composite',
'sender_id' => Auth::id(),
'user_id'   => $validated['user_id'] ?: Auth::id(),  // 空なら自己割当
```

`file_info` はJSON文字列を `json_decode` してから保存する。

保存後は `coordinator.project_jobs.assignments.show`（割当詳細ページ）にリダイレクト。

---

## 6. フロントエンド構成

### 6-1. 「複合ジョブ作成」ボタンの配置

#### Coordinator側
**`resources/js/Pages/Coordinator/ProjectJobs/JobAssign/Edit.vue`** のヘッダー行に追加。
「過去データから流用」ボタンの**左側**に配置する。

```vue
<!-- 追加するボタン（既存の「過去データから流用」ボタンの左） -->
<button
    type="button"
    @click="goToComposite"
    class="rounded border border-green-400 bg-green-50 px-3 py-1.5 text-sm text-green-700 hover:bg-green-100"
>複合ジョブ作成</button>
```

```js
function goToComposite() {
    router.get(route('coordinator.project_jobs.assignments.composite.create', {
        projectJob: props.projectJob.id
    }))
}
```

#### User側（第2フェーズ）
**`resources/js/Pages/MyJobBox/Create_user.vue`** のヘッダー行に追加。
「過去データから流用」ボタンの**左側**に配置する。

### 6-2. 新規ページ（Inertiaページ）

```
resources/js/Pages/Coordinator/ProjectJobs/JobAssign/CompositeCreate.vue
```

- AppLayoutを使用、`#header` に「複合ジョブ作成」を表示
- `CompositeAssignmentForm` コンポーネントを配置

### 6-3. 新規コンポーネント（フォーム）

```
resources/js/Components/CompositeAssignmentForm.vue
```

AssignmentForm.vue からの**流用部分**（coordinatorモードのレイアウトを参考に）:
- クライアント表示（読み取り専用）
- プロジェクト名表示（読み取り専用）
- ジョブ名入力 (`title_suffix`)
- 概要テキストエリア (`detail`)
- 割当ユーザー選択（雇用形態バッジ含む）
- 作業種別・ステージ選択
- 難易度選択
- 締め切り日時（date + hour + min）
- 見積時間

**新規追加部分**（ファイル情報セクション。ジョブ名・概要の直後に配置）:
- ドラッグ＆ドロップゾーン
- フォルダ選択ボタン（`<input type="file" webkitdirectory multiple>`）
- ファイル選択ボタン（`<input type="file" multiple>`）
- ファイルリストテーブル（種別ごとに分割）
- 集計サマリー

### 6-4. ファイル解析コンポーザブル（新規）

```
resources/js/composables/useFileAnalyzer.js
```

主要な公開インターフェース:
```js
const {
    analyzeFiles,   // (FileList) => void  ファイル解析を開始
    analyzing,      // Ref<boolean>        解析中フラグ
    results,        // Ref<FileResult[]>   解析済みファイル一覧
    grouped,        // Computed            拡張子種別ごとにグループ化した結果
    summary,        // Computed            合計ファイル数・ページ数・サイズ
    removeFile,     // (index) => void     リストから1件削除
    buildFileInfo,  // () => Object        保存用JSONオブジェクト生成
} = useFileAnalyzer()
```

---

## 7. UIフロー詳細

### 7-1. 画面レイアウト（上から順）

```
┌─────────────────────────────────────────────────┐
│ クライアント: ○○印刷株式会社（読み取り専用）        │
│ プロジェクト: 商品カタログ2026（読み取り専用）      │
├─────────────────────────────────────────────────┤
│ ジョブ名: [_________________________________]   │
│ 概要:     [_________________________________]   │
│           [_________________________________]   │
├─────────────────────────────────────────────────┤
│ ▼ 作業ファイル情報                               │
│ ┌───────────────────────────────────────────┐  │
│ │     ここにファイル・フォルダをドラッグ＆ドロップ  │  │
│ │     [フォルダを選択]  [ファイルを選択]          │  │
│ └───────────────────────────────────────────┘  │
│                                                 │
│ ── PDF（10ファイル / 42ページ / 50MB） ──────── │
│ ファイル名          サイズ  ページ数  ドキュメント │
│ catalog_vol3.pdf    5MB    12p      A4      [✕] │
│ …                                               │
│                                                 │
│ ── InDesign（8ファイル / 68ページ / 80MB） ──── │
│ ファイル名          サイズ  ページ数  ドキュメント │
│ layout_cover.indd   10MB   4p       B5      [✕] │
│ …                                               │
│                                                 │
│ ── Photoshop（7ファイル / 140MB） ────────────  │
│ ファイル名          サイズ  幅×高さ   カラー      │
│ photo_001.psd       20MB   3508×2480 CMYK 8bit [✕]│
│ …                                               │
│                                                 │
│ 【合計】25ファイル / 110ページ / 270MB           │
├─────────────────────────────────────────────────┤
│ 割当ユーザー: [▼ 選択 ___________________]     │
│ 作業種別: [▼]    ステージ: [▼]               │
│ 難易度:   [▼]                                 │
├─────────────────────────────────────────────────┤
│ 締め切り: [日付____] [時▼] [分▼]             │
│ 見積時間: [▼]                                 │
├─────────────────────────────────────────────────┤
│                        [保存する]               │
└─────────────────────────────────────────────────┘
```

### 7-2. ファイルリストテーブル列構成

**ページ系（PDF / AI / INDD / Word）**

| ファイル名 | ファイルサイズ | ページ数 | ドキュメントサイズ | ✕ |
|-----------|------------|---------|----------------|---|
| catalog.pdf | 5MB | 12p | A4 | ✕ |
| layout.indd | 10MB | 4p | B5 | ✕ |
| **合計** | **15MB** | **16p** | ─ | ─ |

**画像系（PSD / PSB / JPG / PNG / TIFF 等）**

| ファイル名 | ファイルサイズ | 幅×高さ(px) | カラーモード | ✕ |
|-----------|------------|-----------|-----------|---|
| photo.psd | 20MB | 3508×2480 | CMYK 8bit | ✕ |
| **合計** | **20MB** | ─ | ─ | ─ |

**その他（ZIP / XLSX / 不明形式等）**

| ファイル名 | ファイルサイズ | 種別 | ✕ |
|-----------|------------|-----|---|
| data.xlsx | 1MB | xlsx | ✕ |
| **合計** | **1MB** | ─ | ─ |

- テーブルのグループヘッダー（例: `── PDF（10ファイル / 42ページ / 50MB） ──`）はファイルがある種別のみ表示
- 各行の「✕」でそのファイルをリストから除外
- 解析中のファイルはスピナー表示（1ファイルずつ非同期処理）
- 未対応形式は「その他」グループへ

### 7-3. 数量フィールドの自動入力

ファイル解析完了後、以下を自動セット（ユーザーが上書き可）:
- `amounts` ← `total_pages`（ページ数がある場合）or `total_files`
- `amounts_unit` ← ページがある場合 `'page'`、なければ `'file'`

---

## 8. JobBox / MyJobBoxでの表示

### バッジ表示（B）
`job_type === 'composite'` のジョブには「複合」バッジを表示。
既存の `proof` バッジと同様の方法で実装する（MyJobBox/Index.vue 等を参照）。

### 詳細ページでのファイルリスト展開（C）
**既存の割当詳細ページ（Show.vue）に追記する。**

`assignment.file_info` が存在する場合、詳細ページの末尾に
「作業ファイル一覧」セクションを表示する。
表示内容はUIの7-2と同じ種別別テーブル（削除ボタンなし・読み取り専用）。

---

## 9. 実装ファイル一覧

### 新規作成

| # | パス | 説明 |
|---|------|------|
| 1 | `database/migrations/YYYY_MM_DD_add_file_info_to_project_job_assignments.php` | file_infoカラム追加 |
| 2 | `app/Http/Controllers/Coordinator/CompositeJobAssignmentController.php` | コントローラー |
| 3 | `resources/js/Pages/Coordinator/ProjectJobs/JobAssign/CompositeCreate.vue` | Inertiaページ（Coordinator） |
| 4 | `resources/js/Components/CompositeAssignmentForm.vue` | フォームコンポーネント |
| 5 | `resources/js/composables/useFileAnalyzer.js` | ファイル解析ロジック |

### 変更

| # | パス | 変更内容 |
|---|------|---------|
| 6 | `routes/web.php` | compositeルート2本追加（Coordinatorグループ） |
| 7 | `app/Models/ProjectJobAssignment.php` | `file_info` を fillable / casts に追加 |
| 8 | `resources/js/Pages/Coordinator/ProjectJobs/JobAssign/Edit.vue` | 「複合ジョブ作成」ボタン追加（「過去データから流用」の左） |
| 9 | `resources/js/Pages/MyJobBox/Create_user.vue` | 「複合ジョブ作成」ボタン追加（「過去データから流用」の左）※第2フェーズ |
| 10 | `resources/js/Pages/Coordinator/ProjectJobs/JobAssign/Show.vue` | file_info がある場合にファイルリストセクション追加 |
| 11 | `resources/js/Pages/MyJobBox/Index.vue` 等 | 「複合」バッジ追加（job_type === 'composite'） |

---

## 10. 実装順序（推奨）

1. `npm install pdfjs-dist jszip`
2. DBマイグレーション作成・実行（`file_info` カラム追加）
3. `ProjectJobAssignment` モデル更新（fillable / casts）
4. `useFileAnalyzer.js` 作成（ファイル解析コアロジック）
5. `CompositeAssignmentForm.vue` 作成（ドロップゾーン＋テーブル＋フォーム）
6. `CompositeCreate.vue` 作成（Inertiaページ）
7. `CompositeJobAssignmentController.php` 作成
8. `routes/web.php` に追加
9. `Edit.vue` に「複合ジョブ作成」ボタン追加
10. `Show.vue` にファイルリストセクション追加
11. JobBox系のバッジ追加
12. `npm run build` で動作確認

---

## 11. 注意事項・制約

### さくら本番の制約
- `npm run build` は必ずローカルで実行し、`public/build/` をgit管理する
- ルートは `route()` ヘルパーを使用（ハードコード禁止）
- CSRF: `meta[name="csrf-token"]` から取得（クッキーから取得しない）

### ファイルサイズの上限
- 1ファイルあたり **100MB超** は解析をスキップし、ファイル名・サイズのみ記録する（エラーにしない）

### pdfjs-dist のワーカー設定
Viteビルドで `pdf.worker.js` を正しく解決するため、以下の設定が必要:
```js
import * as pdfjsLib from 'pdfjs-dist'
pdfjsLib.GlobalWorkerOptions.workerSrc = new URL(
  'pdfjs-dist/build/pdf.worker.mjs',
  import.meta.url
).toString()
```

### 古いINDDファイル
CS3以前（2007年以前）のINDDはZIP構造でないため解析不可。
マジックバイト `0x0606EDE0...` で INDD と判定し、ページ数・サイズは null として記録する（エラーにしない）。

---

## 12. 将来拡張（今回は対象外）

- **WorkloadAnalyzerへの複合ジョブ対応**: `file_info` のグループ別データをユーザー分析に組み込む
- **User自己割当の複合ジョブページ**: `Create_user.vue` のボタンから遷移する専用ページ
- **係数設定**: 管理者がファイル種別ごとの工数係数を設定し、見積時間を自動計算
- **テンプレート保存**: よく使うファイル構成をテンプレートとして保存
- **Excel/CSVエクスポート**: ファイル一覧を見積書として出力

---

*作成: 2026-04-15 / 最終更新: 2026-04-15*
