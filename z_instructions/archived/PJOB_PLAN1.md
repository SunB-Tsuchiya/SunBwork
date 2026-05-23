# PJOB_PLAN1.md — coordinator 案件作成フォーム改修 + CSV一括登録

## 概要
coordinator の案件新規作成フォームを prepress 伝票フォームに準拠した形に改修し、
coordinator/prepress 両方に CSV 一括登録機能を整備する。

---

## DB 変更（Migration）

### 追加カラム（`project_jobs` テーブル）
| カラム名 | 型 | 制約 | 説明 |
|---|---|---|---|
| `sales_rep` | string(100) | nullable | 担当営業テキスト（フリー入力） |
| `sales_rep_id` | bigint unsigned | nullable, FK → `prepress_sales_reps.id` | 担当営業（DB選択） |
| `plate_submission_date` | date | nullable | 製版入稿日 |
| `plate_down_date` | date | nullable | 下版日 |

**注意:** `project_jobs.schedule` カラムはさくら本番に存在しないため、migration の `down()` では safe に処理すること。

---

## フェーズ別タスク

### Phase 1: DB Migration
- ファイル: `database/migrations/2026_05_23_100001_add_sales_and_dates_to_project_jobs.php`
- 4カラムを `project_jobs` に追加

### Phase 2: coordinator 新規作成フォーム改修
変更内容:
- **"案件タイトル" → "案件名"** （ラベルのみ）
- **ステータス欄を削除**（フォームから除去、バリデーションも除去）
- **"詳細" ラベル → "メモ"**（DB カラム名 `detail` は変更しない）
- **担当営業追加**: `prepress_sales_reps` の dropdown + フリーテキスト（prepressの Create.vue と同じ UI）
- **製版入稿日 / 下版日追加**: `DateInput` コンポーネント（`@/Components/Prepress/DateInput.vue`）を使用

変更ファイル:
1. `app/Models/ProjectJob.php` — fillable に 4 カラム追加、`plate_submission_date`/`plate_down_date` を date キャスト追加
2. `app/Http/Controllers/Coordinator/ProjectJobController.php`
   - `create()`: `salesReps` prop を追加（`PrepresSalesRep::orderBy('sort_order')->get(['id','name','company'])`）
   - `store()`: `sales_rep`, `sales_rep_id`, `plate_submission_date`, `plate_down_date` バリデーション追加、`schedule` カラムは `Arr::pull` で除外維持
3. `resources/js/Pages/Coordinator/ProjectJobs/Create.vue` — フォーム改修

### Phase 3: coordinator CSV 一括登録
#### 新規コントローラー: `Coordinator/ProjectJobCsvController.php`
メソッド:
- `analyzeCsv(Request)` — CSV 解析・クライアント/営業担当マッチング → JSON
- `importCsv(Request)` — 確認済みデータを一括保存 → JSON
- `downloadSample()` — サンプル CSV ダウンロード

CSV 列構成（coordinator）:
```
行番号,伝票番号,クライアント名,案件名,担当営業,製版入稿日,下版日
```
- `line[0]`: 行番号（無視）
- `line[1]`: jobcode（伝票番号）
- `line[2]`: クライアント名
- `line[3]`: title（案件名）
- `line[4]`: 担当営業（フリーテキスト）
- `line[5]`: plate_submission_date（YYYY-MM-DD or 空）
- `line[6]`: plate_down_date（YYYY-MM-DD or 空）

**登録時の user_id**: ログイン中の coordinator を自動設定（`$request->user()->id`）

依存:
- `NormalizesCsvEncoding` trait（Shift-JIS 対応）
- `PrepressClientMatcher` サービス（クライアントマッチング共通ロジック）
- `PrepresSalesRep` モデル（営業担当マッチング）

#### 営業担当 API（coordinator 用）
`Coordinator/SalesRepController.php` に `apiList()` メソッドを追加（prepress SalesRepController と同様）

#### 新規ルート（coordinator prefix 内）
```php
Route::get('project-jobs/csv/sample', [ProjectJobCsvController::class, 'downloadSample'])->name('project_jobs.csv.sample');
Route::post('project-jobs/csv/analyze', [ProjectJobCsvController::class, 'analyzeCsv'])->name('project_jobs.csv.analyze');
Route::post('project-jobs/csv/import', [ProjectJobCsvController::class, 'importCsv'])->name('project_jobs.csv.import');
Route::get('sales-reps/api/list', [SalesRepController::class, 'apiList'])->name('sales_reps.api.list');
```
※ これらは `{projectJob}` パラメータルートより **前**に配置すること（既存の静的ルート配置ルールに倣う）

#### coordinator Index.vue 改修
`#headerExtras` に CSV読み込みボタンを追加:
```
[テンプレートから一括作成]  [CSV読み込み]  [新規作成]
```
- CSV 読み込みクリックで モーダル表示（prepress Index.vue の CSV モーダルを流用・coordinator 用に調整）

### Phase 4: prepress CSV サンプルダウンロード
- `TicketController.php` に `downloadSample()` 追加
- ルート追加: `Route::get('tickets/csv/sample', [..., 'downloadSample'])->name('tickets.csv.sample')`
- `Prepress/Tickets/Index.vue` の CSV モーダルに「サンプルCSVをダウンロード」ボタンを追加

prepress サンプル CSV 列構成:
```
行番号,伝票番号,クライアント名,案件名,担当営業
```

---

## 変更ファイル一覧

| # | ファイル | 種別 | 内容 |
|---|---|---|---|
| 1 | `database/migrations/2026_05_23_100001_add_sales_and_dates_to_project_jobs.php` | 新規 | DB マイグレーション |
| 2 | `app/Models/ProjectJob.php` | 修正 | fillable・casts 追加 |
| 3 | `app/Http/Controllers/Coordinator/ProjectJobController.php` | 修正 | create/store 更新 |
| 4 | `app/Http/Controllers/Coordinator/ProjectJobCsvController.php` | 新規 | CSV 処理コントローラー |
| 5 | `app/Http/Controllers/Coordinator/SalesRepController.php` | 修正 | apiList メソッド追加 |
| 6 | `routes/web.php` | 修正 | coordinator CSV ルート追加、prepress sample ルート追加 |
| 7 | `resources/js/Pages/Coordinator/ProjectJobs/Create.vue` | 修正 | フォーム改修 |
| 8 | `resources/js/Pages/Coordinator/ProjectJobs/Index.vue` | 修正 | CSV ボタン + モーダル追加 |
| 9 | `app/Http/Controllers/Prepress/TicketController.php` | 修正 | downloadSample 追加 |
| 10 | `resources/js/Pages/Prepress/Tickets/Index.vue` | 修正 | サンプルダウンロードボタン追加 |

---

## 注意事項

- coordinator と prepress の CSV ルートは **完全に分離**（ルート名・コントローラーが別）
- Shift-JIS 対応は `NormalizesCsvEncoding` trait で実施済み。新コントローラーも `use` すること
- coordinator CSV 登録時の `jobcode` 重複チェック対象は `project_jobs` テーブル（prepress は `prepress_tickets`）
- `project_jobs.schedule` カラムの `Arr::pull` は既存 store() で実施済み。改修後も維持すること
- `plate_submission_date` / `plate_down_date` は空文字を `null` として保存（date バリデーション `nullable|date`）
- `sales_rep_id` の FK は `prepress_sales_reps` テーブル（leader/coordinator/prepress 共通のマスタ）
