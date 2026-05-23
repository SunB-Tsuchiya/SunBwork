# PREPRESS_PLAN2.md — 製版ボード 修繕計画２

作成日: 2026-05-22

---

## 概要

製版ボード（`Prepress/Board.vue`）および周辺機能の拡張。  
変更ファイル数: 9〜11ファイル + 新規マイグレーション1件 + 新規サービス1件。

---

## 変更要件一覧

| # | 機能 | 優先度 |
|---|------|--------|
| R1 | グローバル検索ボックス（ボード全体フィルター） | 高 |
| R2 | CSV一括登録（確認モーダル付き） | 高 |
| R3 | 担当営業（sales_rep）カラム追加 + 手動フォーム対応 | 高 |
| R4 | 出稿中（outputting）ステータス列追加 | 中 |
| R5 | 列同時展開数上限 2→4 に拡張 | 中 |
| R6 | 準備列 リスト/カード切替タブ | 中 |

---

## フェーズ別実装計画

### Phase 1: DB・モデル・ステータス拡張

**目的:** バックエンドの土台を固める

**変更ファイル:**

| ファイル | 種別 | 変更内容 |
|---------|------|---------|
| `database/migrations/2026_05_22_000001_add_sales_rep_and_outputting_to_prepress_tickets.php` | 新規 | `sales_rep VARCHAR(100) nullable` 追加 |
| `app/Models/PrepressTicket.php` | 変更 | `sales_rep` を fillable に追加、`STATUS_LABELS` に `'outputting' => '出稿中'` 追加、`STATUS_OUTPUTTING` 定数追加 |
| `app/Http/Controllers/Prepress/TicketController.php` | 変更 | `store()` / `update()` に `sales_rep` バリデーション・保存追加 |
| `app/Http/Controllers/Prepress/BoardController.php` | 変更 | `updateStatus()` の許可ステータスに `outputting` 追加 |

**成果物:** マイグレーション実行後、新ステータスが動作する状態

---

### Phase 2: 手動フォームへの担当営業フィールド追加

**変更ファイル:**

| ファイル | 変更内容 |
|---------|---------|
| `resources/js/Pages/Prepress/Tickets/Create.vue` | 担当営業 入力欄追加（任意項目） |
| `resources/js/Pages/Prepress/Tickets/Edit.vue` | 担当営業 入力欄追加 |
| `resources/js/Pages/Prepress/Tickets/Show.vue` | 担当営業 表示項目追加 |
| `resources/js/Pages/Prepress/Tickets/Index.vue` | 担当営業 列追加 |

---

### Phase 3: Board.vue 基本改修

**変更ファイル:**

| ファイル | 変更内容 |
|---------|---------|
| `resources/js/Pages/Prepress/Board.vue` | 下記 a〜d を追加 |

**a) 出稿中列追加**
```js
const COLUMNS = [
  { key: 'pending',     label: '準備',    color: 'border-yellow-400 bg-yellow-50',  header: 'bg-yellow-100 text-yellow-800',  barText: 'text-yellow-800'  },
  { key: 'submitting',  label: '入稿予定', color: 'border-purple-400 bg-purple-50',  header: 'bg-purple-100 text-purple-800',  barText: 'text-purple-800'  },
  { key: 'in_progress', label: '作業中',  color: 'border-blue-400 bg-blue-50',      header: 'bg-blue-100 text-blue-800',      barText: 'text-blue-800'    },
  { key: 'outputting',  label: '出稿中',  color: 'border-orange-400 bg-orange-50',  header: 'bg-orange-100 text-orange-800',  barText: 'text-orange-800'  }, // ← 新規
  { key: 'completed',   label: '完了',    color: 'border-green-500 bg-green-50',    header: 'bg-green-100 text-green-800',    barText: 'text-green-800'   },
];
```

**b) 列展開上限 2→4**
```js
function toggleColumn(key) {
    const s = new Set(openColumns.value);
    if (s.has(key)) {
        s.delete(key);
    } else {
        if (s.size >= 4) { // ← 2 から 4 に変更
            const rightmost = COLUMNS.map(c => c.key).filter(k => s.has(k)).at(-1);
            if (rightmost) s.delete(rightmost);
        }
        s.add(key);
    }
    openColumns.value = s;
}
// デフォルト open: 入稿予定・作業中・出稿中 の3列
const openColumns = ref(new Set(['submitting', 'in_progress', 'outputting']));
```

**c) グローバル検索ボックス**
- ボードヘッダー右側（「＋ 伝票登録」ボタンの左横）に配置
- `ref` `boardSearch` で管理
- 検索対象: `jobcode`, `client_name`, `client_code`(clientId), `sales_rep`, `title` — 部分一致
- `ticketsByStatus` computed内でフィルタリング（サーバーリクエスト不要、クライアント側）

```html
<!-- ヘッダー内 -->
<input v-model="boardSearch" type="text" placeholder="ID、担当営業など"
  class="rounded border border-gray-300 px-3 py-1.5 text-sm w-48" />
```

```js
const boardSearch = ref('');

const ticketsByStatus = computed(() => {
    const map = {};
    COLUMNS.forEach(c => { map[c.key] = []; });
    let list = localTickets.value;
    if (boardSearch.value.trim()) {
        const q = boardSearch.value.trim().toLowerCase();
        list = list.filter(t =>
            [t.jobcode, t.client_name, String(t.client_id ?? ''), t.sales_rep, t.title]
                .some(v => v && String(v).toLowerCase().includes(q))
        );
    }
    list.forEach(t => { if (map[t.status]) map[t.status].push(t); });
    return map;
});
```

**d) 伝票登録ボタン → モーダル統合（ドロップダウン廃止）**
- 「＋ 伝票登録」クリックで直接「登録方法選択モーダル」を開く（ドロップダウン UI は使わない）
- モーダル内に 4 択ボタン: OCR読み込み / 手動登録 / 担当営業CSV / CSV一括登録
- `Board.vue` の `showRegisterMenu` 変数・ドロップダウン HTML は削除済み
- `openCreateModal()` / `closeCreateModal()` で管理

**BoardController の変更:** `index()` で `sales_rep` も取得カラムに追加

---

### Phase 4: 準備列 リスト/カード切替タブ

**変更ファイル:** `resources/js/Pages/Prepress/Board.vue`

`pending` 列のみタブを表示（カード表示 / リスト表示）。

**リスト表示仕様:**
- `<table>` 形式（overflow-y: auto でスクロール）
- カラム: 伝票番号 / クライアント名(ID) / 案件名 / 担当営業 / 入稿日 / 下版日
- 並べ替え: ヘッダークリックで昇降順切替（独立した sort state）
- 行クリックで詳細モーダルを開く（既存 `openDetail(ticket)`）

```
| 伝票番号  | クライアント       | 案件名          | 担当営業  | 入稿日 | 下版日 |
|-----------|-------------------|----------------|----------|--------|--------|
| 4600152   | ABC(株) [ID:5]    | B2ポスター 728号 | 山田太郎 | 5/28   | 6/02   |
```

**状態保持:** `localStorage` で `prepress_board_pending_view` に `'list'` / `'card'` を保存

---

### Phase 5: CSV一括登録

#### 5-1. サーバーサイド

**新規ファイル:** `app/Services/PrepressClientMatcher.php`

```php
class PrepressClientMatcher
{
    // クライアント名の正規化
    public static function normalize(string $str): string
    {
        // ①-⑳, ㉑-㉟ 等の丸付き数字を削除（Unicode範囲）
        $str = preg_replace('/[\x{2460}-\x{2473}\x{3251}-\x{325F}\x{3280}-\x{32B0}]/u', '', $str);
        // シングルクォートを削除
        $str = str_replace("'", '', $str);
        // mb_convert_kana: 全角英数字→半角、全角スペース→半角、半角カナ→全角カナ、濁点結合
        $str = mb_convert_kana($str, 'aKVs', 'UTF-8');
        // 括弧正規化: （）→()
        $str = strtr($str, ['（' => '(', '）' => ')']);
        // 前後スペース・全角スペース削除
        $str = trim($str);
        return $str;
    }

    // DB全クライアントと照合（製版部署フィルタ有り）
    // 戻り値: ['status' => 'matched'|'candidates'|'unmatched', 'client' => ..., 'candidates' => [...]]
    public static function match(string $rawName, Collection $dbClients): array
    {
        $normalized = self::normalize($rawName);
        // 完全一致
        $exact = $dbClients->first(fn($c) => self::normalize($c->name) === $normalized);
        if ($exact) return ['status' => 'matched', 'client' => $exact];
        // 部分一致（正規化後）
        $partial = $dbClients->filter(fn($c) => str_contains(self::normalize($c->name), $normalized)
            || str_contains($normalized, self::normalize($c->name)))->values();
        if ($partial->isNotEmpty()) return ['status' => 'candidates', 'candidates' => $partial->take(5)->toArray()];
        return ['status' => 'unmatched'];
    }
}
```

**TicketController に追加 `analyzeCsv()`:**
- `POST prepress/tickets/analyze-csv` (JSON レスポンス)
- CSV をパース（CP932対応: `NormalizesCsvEncoding` トレイト使用）
- 各行をクレンジング（丸数字・シングルクォート除外）
- `PrepressClientMatcher::match()` で照合
- 結果JSON を返す（確認画面用）

**TicketController に追加 `importCsv()`:**
- `POST prepress/tickets/import-csv`
- フロントから確認済みデータ（client_id解決済み行リスト）を受取り
- バルクインサート
- `inertia redirect` or JSON response

#### 5-2. フロントエンド

**CSV確認モーダル構成:**

```
┌─────────────────────────────────────────────────────┐
│  CSV一括登録 確認 (15件)                              │
├───────────────────────────────────────────────────── │
│  ✅ 一致 (10件)                                       │
│     ... 折りたたみ / 展開                             │
├─────────────────────────────────────────────────────┤
│  ⚠️ 候補あり (3件)                                    │
│  行2 | 4600153 | ABC商事                             │
│       候補: ○ ABC株式会社   ○ ABC商事(東京)           │
│             ○ 候補なし(新規登録) ○ 一覧から選択       │
├─────────────────────────────────────────────────────┤
│  ❌ 未マッチ (2件)                                    │
│  行5 | 4600157 | 新規商会                             │
│       [新規登録] [クライアント一覧から選択]            │
├─────────────────────────────────────────────────────┤
│              [キャンセル]  [一括保存 (15件)]          │
└─────────────────────────────────────────────────────┘
```

- 「クライアント一覧から選択」クリック → インライン検索ボックスを展開（`apiClients` API 使用）
- 「新規登録」クリック → インライン最小フォーム（client_code, name 入力 → POST `/admin/clients`）
- ⚠️候補と❌未マッチが全て解決されるまで「一括保存」ボタンは disabled

**ルート追加（web.php）:**
```php
Route::post('tickets/analyze-csv', [TicketController::class, 'analyzeCsv'])->name('tickets.analyzeCsv');
Route::post('tickets/import-csv',  [TicketController::class, 'importCsv'])->name('tickets.importCsv');
```
※ `tickets/{ticket}` より先に記述すること

---

## DB設計

### 追加マイグレーション

```php
// 2026_05_22_000001_add_sales_rep_and_outputting_to_prepress_tickets.php
Schema::table('prepress_tickets', function (Blueprint $table) {
    $table->string('sales_rep', 100)->nullable()->after('client_name');
});
```

`outputting` ステータスはDB列でなくモデル定数・STATUS_LABELSへの追加のみ。

### ERD影響

`prepress_tickets` テーブルに `sales_rep VARCHAR(100) NULL` が追加される。

---

## CSV仕様

### 入力形式

| 列名 | マッピング先 | 備考 |
|------|------------|------|
| No | （使用しない） | 行番号 |
| 受注No. | `jobcode` | 先頭の `'` を除去 |
| 得意先 | `client_name` / クライアントマッチング対象 | 丸数字・クォート除去後 |
| 品名 | `title` | 丸数字・クォート除去後 |
| 営業担当 | `sales_rep` | 先頭の `'` を除去 |

### エンコーディング対応

CP932（Windows Shift-JIS）で保存されたファイルを `NormalizesCsvEncoding` トレイトで UTF-8 変換してから処理。

### クレンジングルール

1. `'` (シングルクォート) を全箇所から削除
2. ①-⑳, ㉑-㉟, ㊀-㊰ などの丸付き数字を削除（Unicode regex）
3. 前後の空白・全角スペースを trim

### クライアント名正規化ロジック

```php
mb_convert_kana($str, 'aKVs', 'UTF-8')
// a: 全角英数字 → 半角英数字
// K: 半角カタカナ → 全角カタカナ
// V: 半角濁点・半濁点を統合
// s: 全角スペース → 半角スペース
```

その後 `（）` → `()` の括弧統一。

---

## 変更ファイル一覧（フェーズ別）

### Phase 1
- `database/migrations/2026_05_22_000001_add_sales_rep_...php` ★新規
- `app/Models/PrepressTicket.php`
- `app/Http/Controllers/Prepress/TicketController.php`
- `app/Http/Controllers/Prepress/BoardController.php`

### Phase 2
- `resources/js/Pages/Prepress/Tickets/Create.vue`
- `resources/js/Pages/Prepress/Tickets/Edit.vue`
- `resources/js/Pages/Prepress/Tickets/Show.vue`
- `resources/js/Pages/Prepress/Tickets/Index.vue`

### Phase 3
- `resources/js/Pages/Prepress/Board.vue`（大規模改修）
- `app/Http/Controllers/Prepress/BoardController.php`

### Phase 4
- `resources/js/Pages/Prepress/Board.vue`（準備列タブ追加）

### Phase 5
- `app/Services/PrepressClientMatcher.php` ★新規
- `app/Http/Controllers/Prepress/TicketController.php`（analyzeCsv, importCsv追加）
- `resources/js/Pages/Prepress/Board.vue`（CSVモーダル追加）
- `routes/web.php`（2ルート追加）

**合計:** 新規2ファイル + 変更9ファイル

---

## 注意事項

- `outputting` ステータスは既存データに影響しない（新規ステータスの追加のみ）
- `route('prepress.tickets.analyzeCsv')` は `route('prepress.tickets.{ticket}')` パターンより前に宣言する
- CSV インポートのデフォルトステータスは `pending`（準備）
- クライアント新規登録は既存の Admin/ClientController を使用。製版部署への自動紐付け処理を追加する
- ボードの `sales_rep` カラムは BoardController の `index()` の `get([...])` 列リストにも追加する

---

## Phase 6: 営業担当テーブル・管理機能（追加実装 2026-05-22）

### 追加マイグレーション

```sql
-- prepress_sales_reps
id, name VARCHAR(100), company VARCHAR(200) nullable, timestamps

-- prepress_sales_rep_department (pivot)
sales_rep_id FK, department_id FK
```

### 追加ファイル

| ファイル | 種別 | 内容 |
|---------|------|------|
| `database/migrations/..._create_prepress_sales_reps_table.php` | 新規 | sales_reps + pivot テーブル |
| `app/Models/PrepresSalesRep.php` | 新規 | belongsToMany(Department)、fillable |
| `app/Http/Controllers/Prepress/SalesRepController.php` | 新規 | index/store/update/destroy/apiList/apiCreate/bulkStore |
| `resources/js/Pages/Prepress/SalesReps/Index.vue` | 新規 | 営業担当一覧・登録・編集・削除・一括登録 UI |
| `routes/web.php` | 変更 | `prepress/sales-reps/*` ルート群追加 |

### 変更ファイル（Phase 6）

| ファイル | 変更内容 |
|---------|---------|
| `app/Models/PrepressTicket.php` | `sales_rep_id` fillable追加、`salesRepEntry` belongsTo追加 |
| `app/Http/Controllers/Prepress/TicketController.php` | CSV import で sales_rep_id を保存、store/update に sales_rep_id 追加 |
| `app/Http/Controllers/Prepress/BoardController.php` | index() に salesRepEntry eager load、apiClients() 追加 |
| `resources/js/Pages/Prepress/Board.vue` | CSV モーダルに担当営業選択 UI 追加、salesReps prop 受取 |
| `resources/js/Pages/Prepress/Tickets/Index.vue` | CSV モーダルに担当営業選択 UI 追加（Board.vue と同等実装） |

### 営業担当一括登録

- `SalesReps/Index.vue` に「一括挿入」トグルボタン
- テキストエリアに改行区切りで名前入力
- リアルタイムチップ表示: 緑=新規OK / 赤=DB重複 / 黄=テキスト内重複
- `POST prepress/sales-reps/bulk` → `SalesRepController.bulkStore()`
- PHP 側: `normalizeName()` + `whereIn` 事前チェック + ループ作成

---

## Phase 7: モーダル統合・インラインクライアント修正（追加実装 2026-05-23）

### 変更内容

| ファイル | 変更内容 |
|---------|---------|
| `resources/js/Pages/Prepress/Board.vue` | showRegisterMenu 削除、＋ボタンを直接 openCreateModal() に、CSV を4択目として追加 |
| `resources/js/Pages/Prepress/Tickets/Index.vue` | Board.vue と同等のCSVモーダル全実装追加（インライン登録含む） |
| `app/Http/Controllers/Prepress/BoardController.php` | apiClientCreate(): 名前完全一致の重複チェック追加、was_existing フラグ返却 |
| `resources/js/Pages/Prepress/Board.vue` | saveInlineClient(): triggeredRawName 方式でCSV内同名行への一括反映、was_existing 時のメッセージ表示 |
| `resources/js/Pages/Prepress/Tickets/Index.vue` | 同上（Board.vue と同等の修正） |

### インラインクライアント登録の仕様（確定）

- CSV内に同一クライアント名が複数行ある場合、1回の登録で全行に反映する
- 反映キーは `raw_client_name`（CSVデータ原文）— モーダル内で名前編集しても正しく反映される
- DB に同名クライアントがすでに存在する場合（`was_existing: true`）:
  - 新規作成せず既存レコードを返す
  - モーダルを自動で閉じず、青い info ボックスでメッセージを表示
  - ユーザーが「OK」クリックで閉じる
  - バックエンドで製版部署への紐付けは自動で行う（syncWithoutDetaching）
