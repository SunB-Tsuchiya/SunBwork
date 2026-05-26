# TRAINS_PLAN1.md — 交通費申請・管理機能 詳細設計

作成日: 2026-05-24  
担当: Claude Code + H. Tsuchiya  
対応MANAGER: TRAINS_MANAGER1.md  
継続プロンプト: TRAINS1_PROMPT.md

---

## 1. 概要・目的

印刷・組版会社（サン・プレーン）向けの交通費申請・精算機能を追加する。
現状は紙Excel（交通費金銭請求伝票）で運用しているものをシステム化する。

**最終ロール配置:**
- 交通費入力 → **User**（現フェーズはSuperAdminでテスト）
- 交通費一覧 → **Clerk**（現フェーズはSuperAdminでテスト）

**親ディレクトリ:** 請求伝票管理（Billing）— 将来的に交通費以外の伝票も収容する総合管理ディレクトリ

---

## 2. DB設計

### テーブル: `transport_expenses`（申請ヘッダー）

| カラム | 型 | 説明 |
|--------|-----|------|
| id | bigint PK | |
| user_id | bigint FK(users) | 申請者 |
| team_id | bigint FK(teams) nullable | 部署ID |
| department_code | tinyint | 0/10/20/30/50 |
| billing_date | date | 請求日 |
| billing_month | char(7) | 対象月 YYYY-MM（一覧絞り込み用） |
| total_amount | int default 0 | 合計金額（明細の合算） |
| status | enum('draft','submitted') default 'draft' | 状態 |
| created_at / updated_at | timestamp | |

### テーブル: `transport_expense_items`（明細行）

| カラム | 型 | 説明 |
|--------|-----|------|
| id | bigint PK | |
| transport_expense_id | bigint FK | ヘッダーID |
| sort_order | tinyint | 行の並び順 |
| occurrence_date | date nullable | 発生日 |
| destination | varchar(100) nullable | 行先 |
| purpose | enum('round_trip','outbound','return','direct_home','other') | 用件 |
| purpose_text | varchar(100) nullable | 自由入力時のテキスト |
| station_from | varchar(100) nullable | 出発駅名 |
| station_to | varchar(100) nullable | 到着駅名 |
| fare_type | enum('ic','ticket') default 'ic' | IC or 切符 |
| amount | int default 0 | 金額（円） |
| created_at / updated_at | timestamp | |

### 部門コード定数（Enum or 定数配列）

```php
const DEPARTMENT_CODES = [
    0  => '共通',
    10 => '情報出版',
    20 => '制作',
    30 => '製版',
    50 => 'オンデマンド',
];
```

---

## 3. 外部API設計（駅すぱあと）

### フェーズ1: フリープラン

**使えるAPI:**
- `/v1/json/station/light` — 駅名補完（部分一致検索）

**制限事項:**
- ルート探索はURL生成のみ（レスポンスで運賃取得不可）
- IC運賃・切符運賃の自動取得: **不可**

**フェーズ1の対応方針:**
- 出発駅・到着駅の入力欄に駅名補完（autocomplete）
- 「駅すぱあとで確認」ボタン → 外部サイトを新タブで開く
- 金額は**手入力**

### フェーズ2: 買い切りプラン（¥5,500〜/5,000件）

**追加で使えるAPI（予定）:**
- `/v1/json/search/course` — ルート探索（複数ルート・IC/切符運賃付き）

**フェーズ2の追加対応:**
- ルート選択モーダルを実装
- IC/切符の運賃を選んで自動入力

### サービスクラス

`app/Services/Billing/EkispertService.php`

```php
class EkispertService
{
    // フェーズ1
    public function searchStation(string $keyword): array  // 駅名補完

    // フェーズ2（APIキー取得後に追加）
    public function searchRoute(string $from, string $to): array  // ルート・運賃取得
}
```

APIキーは `.env` に `EKISPERT_API_KEY=` として保持。

---

## 4. ディレクトリ構造

### Backend

```
app/
  Http/Controllers/Billing/
    Transport/
      ExpenseController.php       # SuperAdmin: 交通費入力 CRUD
      ExpenseListController.php   # SuperAdmin: 交通費一覧
  Models/
    TransportExpense.php
    TransportExpenseItem.php
  Services/Billing/
    EkispertService.php
database/migrations/
  xxxx_create_transport_expenses_table.php
  xxxx_create_transport_expense_items_table.php
```

### Frontend

```
resources/js/
  Pages/
    SuperAdmin/
      Billing/
        Transport/
          Index.vue       # 交通費入力フォーム
          List.vue        # 交通費一覧
  Components/
    Billing/
      Transport/
        RouteSearchModal.vue   # ルート検索モーダル（フェーズ2）
        ExpenseRow.vue         # 明細1行コンポーネント
        ExpenseSummary.vue     # 合計・出力ボタン
```

### Routes

```
routes/web.php 内 superadmin ミドルウェアグループ:

Route::prefix('billing/transport')->name('superadmin.billing.transport.')->group(function () {
    Route::get('/',           [ExpenseController::class, 'index'])->name('index');
    Route::post('/',          [ExpenseController::class, 'store'])->name('store');
    Route::get('/{expense}',  [ExpenseController::class, 'show'])->name('show');
    Route::put('/{expense}',  [ExpenseController::class, 'update'])->name('update');
    Route::delete('/{expense}',[ExpenseController::class, 'destroy'])->name('destroy');
    Route::get('/list',       [ExpenseListController::class, 'index'])->name('list');
    Route::get('/api/station-search', [ExpenseController::class, 'stationSearch'])->name('station_search');
});
```

---

## 5. 変更ファイル一覧

| # | ファイル | 種別 | 説明 |
|---|---------|------|------|
| 1 | `database/migrations/*_create_transport_expenses_table.php` | 新規 | ヘッダーテーブル |
| 2 | `database/migrations/*_create_transport_expense_items_table.php` | 新規 | 明細テーブル |
| 3 | `app/Models/TransportExpense.php` | 新規 | ヘッダーModel |
| 4 | `app/Models/TransportExpenseItem.php` | 新規 | 明細Model |
| 5 | `app/Services/Billing/EkispertService.php` | 新規 | 駅すぱあとAPIサービス |
| 6 | `app/Http/Controllers/Billing/Transport/ExpenseController.php` | 新規 | 入力コントローラー |
| 7 | `app/Http/Controllers/Billing/Transport/ExpenseListController.php` | 新規 | 一覧コントローラー |
| 8 | `routes/web.php` | 変更 | ルート追加 |
| 9 | `resources/js/Pages/SuperAdmin/Billing/Transport/Index.vue` | 新規 | 入力ページ |
| 10 | `resources/js/Pages/SuperAdmin/Billing/Transport/List.vue` | 新規 | 一覧ページ |
| 11 | `resources/js/Components/Billing/Transport/ExpenseRow.vue` | 新規 | 明細行 |
| 12 | `resources/js/Components/Billing/Transport/RouteSearchModal.vue` | 新規(Ph2) | ルート検索モーダル |
| 13 | `resources/js/Components/Tabs/SuperAdminNavigationTabs.vue` | 変更 | タブ2件追加 |
| 14 | `.env` / `.env.example` | 変更 | EKISPERT_API_KEY 追加 |

---

## 6. フェーズ別タスク

### フェーズ1: 基本実装（フリープラン対応）

**Step 1: DB・モデル**
- [ ] migration 2ファイル作成
- [ ] TransportExpense モデル（with, fillable, casts）
- [ ] TransportExpenseItem モデル
- [ ] docker内でmigrate実行

**Step 2: サービス・コントローラー**
- [ ] EkispertService（駅名検索のみ）
- [ ] ExpenseController（index/store/update/destroy/stationSearch）
- [ ] ExpenseListController（index）
- [ ] routes/web.php 追記

**Step 3: 入力フォームUI（Index.vue）**
- [ ] ヘッダー部（請求日ピッカー・部門コードセレクター・所属自動入力・氏名自動入力）
- [ ] 明細テーブル（ExpenseRow.vue × n行）
  - 発生日: テキスト入力 → "4/22" or "0422" → Date型に変換
  - 行先: テキスト入力
  - 用件: セレクター（5択）
  - 区間: 出発駅・到着駅（autocomplete）+ 「確認」ボタン
  - IC/切符トグル
  - 金額: 手入力（フェーズ1）
- [ ] 行追加ボタン
- [ ] 合計金額自動計算
- [ ] 保存ボタン

**Step 4: 一覧UI（List.vue）**
- [ ] 年月フィルター
- [ ] 部署ごとにグループ表示（部署名・合計・詳細ボタン）
- [ ] 詳細モーダル（各人の明細）

**Step 5: 出力機能**
- [ ] 印刷用スタイル（@media print CSS）
- [ ] PDF出力（barryvdh/laravel-dompdf）
- [ ] Excel出力（PhpSpreadsheet → 元のExcelに近い書式）

**Step 6: SuperAdminタブ追加**
- [ ] SuperAdminNavigationTabs.vue に「交通費入力」「交通費一覧」タブ追加

### フェーズ2: 有料API対応

- [ ] EkispertService に searchRoute() 追加
- [ ] RouteSearchModal.vue 実装（複数ルート選択→IC/切符選択→金額自動入力）

### フェーズ3: ロール移行

- [ ] User ロール用ページ・ルート作成（Pages/User/Billing/Transport/）
- [ ] Clerk ロール用ページ・ルート作成（Pages/Clerk/Billing/Transport/）
- [ ] UserNavigationTabs / ClerkNavigationTabs にタブ追加

---

## 7. 入力フォーム詳細仕様

### ヘッダー部

| 項目 | 入力方法 | 備考 |
|------|---------|------|
| 請求日 | カレンダーピッカー | デフォルト: 今日 |
| 部門コード | セレクター | 0:共通 / 10:情報出版 / 20:制作 / 30:製版 / 50:オンデマンド |
| 所属 | 自動入力（読み取り専用） | ログインユーザーのteam.name |
| 氏名 | 自動入力（読み取り専用） | ログインユーザーのname |

### 明細行

| 項目 | 入力方法 | 備考 |
|------|---------|------|
| 発生日 | テキスト | "4/22" or "0422" → 月日に自動変換して表示 |
| 行先 | テキスト自由入力 | |
| 用件 | セレクター | 打合せ（往復）/ 打合せ（往路）/ 打合せ（帰路）/ 打合せ（直帰）/ その他（自由入力） |
| 出発駅 | テキスト + autocomplete | Ekispert駅名検索API |
| 到着駅 | テキスト + autocomplete | 同上 |
| IC/切符 | トグルボタン | 行ごとに選択可能 |
| 金額 | 数値入力（フェーズ1手入力） | フェーズ2でAPI自動入力 |

**発生日の入力パース仕様:**
- `422` / `0422` → 4月22日
- `4/22` / `4-22` → 4月22日
- 年は請求日の年を使用
- 保存はdate型（YYYY-MM-DD）

### 用件セレクター値

| 表示名 | enum値 |
|--------|--------|
| 打合せ（往復） | round_trip |
| 打合せ（往路） | outbound |
| 打合せ（帰路） | return |
| 打合せ（直帰） | direct_home |
| その他 | other |

「その他」選択時は隣にテキスト入力欄を表示。

---

## 8. 一覧画面仕様（ClerkView / 現SuperAdmin）

- 年月セレクター（デフォルト: 今月）
- 部署ごとにカードまたはセクション分け
  - 部署名
  - 社員名 / 合計金額 / 「詳細」ボタン
- 詳細ボタン → スライドアウトまたはモーダルで明細表示

---

## 9. 出力仕様

| 出力先 | 実装方法 | 備考 |
|--------|---------|------|
| 印刷 | `window.print()` + print CSS | シンプル |
| PDF | `barryvdh/laravel-dompdf` | Blade view経由でサーバー生成 |
| Excel | `phpoffice/phpspreadsheet` | 元のExcel書式に近い出力 |

---

## 10. 注意事項

- `project_jobs.schedule` と同様、本番DBに存在しないカラムを `update()` しないよう注意
- Artisan は必ずコンテナ内実行: `docker compose exec laravel bash -lc "php artisan ..."`
- Vue/JSを変更したら必ず `npm run build`
- さくら本番デプロイ時は `php artisan migrate` を忘れずに実行
