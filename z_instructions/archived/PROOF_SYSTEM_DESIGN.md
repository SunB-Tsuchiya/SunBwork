# 校正依頼システム 設計書

作成日: 2026-04-11
ステータス: 設計確定・実装前

---

## 1. 概要・背景

組版会社のワークフローにおいて、進行管理（Coordinator）・オペレーター（User）が校正員に作業を依頼する場面がある。しかし、校正員は全員PCを持つわけではないため、従来の JobBox（Coordinator → User への一方的な push）では対応できない。

そこで「校正窓口」（ProofCoordinator）という新ロールを設け、依頼を受け取り、校正員に割り振り、完了管理を行う仕組みを構築する。

### ワークフロー概要

```
Coordinator / User
  └─ ジョブ詳細から「校正依頼」ボタン
        ↓
  proof_requests（pending）
        ↓
  ProofCoordinator（窓口）が受信
  └─ 校正カレンダー・作業量を確認しながら校正員を割り当て
        ↓
  proof_requests（assigned → in_progress）
        ↓
  校正員（PCあり）: MyJobとして自己登録・完了
  校正員（PCなし）: ProofCoordinatorが代わりに完了マーク
        ↓
  proof_requests（completed）
        ↓
  job_notifications で依頼者に完了通知
```

---

## 2. 新ロール定義

### `proof_coordinator`（校正コーディネーター）

| 項目 | 値 |
|---|---|
| `user_role` 値 | `'proof_coordinator'` |
| バッジカラー | `bg-pink-100 text-pink-800` |
| 表示名 | 「校正コーディネーター」 |
| 初期人数 | 2名（リーダー・副リーダー、DB上の区別なし） |
| 所属 | 校正チーム（`teams` テーブル） |

### ロール階層における位置

```
SuperAdmin > Admin > Leader（部署リーダー）
                   > Coordinator（進行管理）
                   > ProofCoordinator（校正窓口）← 新規
                   > Clerk
                   > User（オペレーター・校正員 両方）
```

### 操作権限（上位互換）

ProofCoordinator 専用画面を操作できるロール:
- ProofCoordinator（本人）
- Admin
- SuperAdmin
- Leader（部署リーダーのみ、ユニットリーダー・サブリーダーは不可）

---

## 3. 既存テーブルへの変更

### 3-1. `users.user_role` enum 追加

```php
// User モデルおよびバリデーション全箇所に 'proof_coordinator' を追加
'in:superadmin,admin,leader,coordinator,proof_coordinator,clerk,user'
```

**変更が必要なファイル:**
- `app/Models/User.php` — `isProofCoordinator()` メソッド追加
- `app/Http/Controllers/Admin/UserController.php` — バリデーション
- `app/Http/Controllers/Admin/CsvUpload/UserCsvController.php` — CSV ロール変換マップ
- `resources/js/Components/UserTable.vue` — バッジ表示
- `resources/js/Pages/Admin/Users/Index.vue` — `getAssignmentBadgeClass()` / `getAssignmentText()`

### 3-2. `assignments` テーブル

`code = 'kousei'`（校正）は **AssignmentSeeder にすでに存在**。追加不要。

校正員の識別条件:
```php
// 校正員候補 = 担当コードが 'kousei' のUser
User::whereHas('assignment', fn($q) => $q->where('code', 'kousei'))
```

### 3-3. `teams` テーブル

「校正チーム」を本番 Seeder で追加（冪等性必須: `firstOrCreate`）。

```php
Team::firstOrCreate(
    ['name' => '校正チーム', 'team_type' => 'department'],
    ['company_id' => $company->id, ...]
);
```

---

## 4. 新規テーブル: `proof_requests`

### マイグレーションファイル名

`2026_04_XX_200001_create_proof_requests_table.php`

### カラム定義

```php
Schema::create('proof_requests', function (Blueprint $table) {
    $table->id();

    // 依頼元ジョブ（nullable: ジョブ外からの依頼も将来対応できるよう）
    $table->foreignId('project_job_assignment_id')
          ->nullable()
          ->constrained('project_job_assignments')
          ->nullOnDelete();

    // 関連案件（参照用）
    $table->foreignId('project_job_id')
          ->nullable()
          ->constrained('project_jobs')
          ->nullOnDelete();

    // 依頼者
    $table->foreignId('requester_id')
          ->constrained('users')
          ->cascadeOnDelete();

    // 担当窓口（ProofCoordinator が受け取ると自分のIDがセット）
    $table->foreignId('proof_coordinator_id')
          ->nullable()
          ->constrained('users')
          ->nullOnDelete();

    // 担当校正員
    $table->foreignId('proofreader_id')
          ->nullable()
          ->constrained('users')
          ->nullOnDelete();

    // 校正依頼タイトル（ジョブタイトルから引き継ぎ、編集可）
    $table->string('title');

    // 校正専用締め切り（依頼者が設定、ジョブの締め切りとは別）
    $table->date('deadline')->nullable();

    // ステータス
    $table->enum('status', ['pending', 'assigned', 'in_progress', 'completed'])
          ->default('pending');

    // 依頼備考
    $table->text('note')->nullable();

    // 完了日時
    $table->timestamp('completed_at')->nullable();

    $table->timestamps();
});
```

### ステータス遷移

```
pending（依頼受信待ち）
  ↓ ProofCoordinator が proofreader_id をセット
assigned（校正員割り当て済み）
  ↓ ProofCoordinator または校正員が開始マーク
in_progress（校正中）
  ↓ ProofCoordinator または校正員（PCあり）が完了マーク
completed（完了）
```

差し戻しは行わない。再依頼が必要な場合はMyJobで新規ジョブを作成し、改めて校正依頼する。

---

## 5. `job_notifications` テーブルへの対応

既存テーブルを流用。新しい `type` 値を追加:

| type | 発火タイミング | 送信先 |
|---|---|---|
| `proof_requested` | 校正依頼が作成された | ProofCoordinator 全員 |
| `proof_assigned` | ProofCoordinator が校正員を割り当てた | 担当校正員（PCあり時） |
| `proof_completed` | 校正完了 | 依頼者（requester） |

`assignment_id` カラムは `proof_request_id` の代わりに使用する（既存カラムで代替）。

**`JobNotificationService` に追加するstaticメソッド:**
```php
static function proofRequested(ProofRequest $req): void
static function proofAssigned(ProofRequest $req): void
static function proofCompleted(ProofRequest $req): void
```

---

## 6. 新規ファイル一覧

### Backend

```
app/
├── Http/
│   ├── Controllers/
│   │   └── ProofCoordinator/
│   │       └── ProofRequestController.php   ← メインコントローラ
│   └── Middleware/
│       └── ProofCoordinatorMiddleware.php
├── Models/
│   └── ProofRequest.php
database/
└── migrations/
    └── 2026_04_XX_200001_create_proof_requests_table.php
```

### Frontend

```
resources/js/
├── Pages/
│   └── ProofCoordinator/
│       ├── Dashboard.vue           ← ダッシュボード（タブ共通ラッパー）
│       ├── Inbox/
│       │   └── Index.vue           ← 校正依頼受信ボックス
│       ├── Assignments/
│       │   └── Index.vue           ← 割り振り管理表
│       ├── Calendar/
│       │   └── Index.vue           ← 校正カレンダー
│       ├── Workload/
│       │   └── Index.vue           ← 校正員作業量
│       └── History/
│           └── Index.vue           ← 案件校正履歴
└── Components/
    └── Tabs/
        └── ProofCoordinatorNavigationTabs.vue
```

---

## 7. 既存ファイルの変更一覧

### Backend

| ファイル | 変更内容 |
|---|---|
| `app/Models/User.php` | `isProofCoordinator()` メソッド追加 |
| `app/Http/Middleware/HandleInertiaRequests.php` | `unreadProofRequests`（未対応依頼数）を共有データに追加 |
| `app/Http/Controllers/Admin/UserController.php` | バリデーション enum に `proof_coordinator` 追加 |
| `app/Http/Controllers/Admin/CsvUpload/UserCsvController.php` | CSV 変換マップに `'校正コーディネーター' => 'proof_coordinator'` 追加 |
| `app/Services/JobNotificationService.php` | proof 系 static メソッド追加 |
| `routes/web.php` | proof_coordinator ルートグループ追加 |
| `bootstrap/app.php` | ProofCoordinatorMiddleware を `'proof_coordinator'` エイリアスで登録 |

### Frontend

| ファイル | 変更内容 |
|---|---|
| `resources/js/layouts/AppLayout.vue` | `currentRouteContext` に `proof_coordinator.*` → `'proof_coordinator'` 追加 / ナビリンク・タブ追加 / ロールカラーマップ追加 |
| `resources/js/Components/UserTable.vue` | `proof_coordinator` バッジ追加 |
| `resources/js/Pages/Admin/Users/Index.vue` | `getAssignmentBadgeClass()` / `getAssignmentText()` に追加 |
| `resources/js/Pages/User/MyJobBox/Show.vue` | 「校正依頼」ボタン追加 |
| `resources/js/Pages/Coordinator/ProgressSheets/Show.vue` | 「校正依頼」ボタン追加（ProgressCell の joblink セルに統合） |
| `resources/js/Pages/User/ProjectJobs/Show.vue` | 「校正依頼」ボタン追加 |
| `resources/js/Pages/Coordinator/ProjectJobs/Show.vue` | 案件詳細ページ内に「校正履歴」セクションを追加（読み取り専用） |
| `resources/js/Pages/User/ProjectJobs/Show.vue` | 同上（User向け案件詳細ページ内に「校正履歴」セクションを追加） |

---

## 8. ルート設計

```php
// routes/web.php

// ========== ProofCoordinator ルート ==========
Route::middleware(['auth', 'proof_coordinator'])->prefix('proof-coordinator')->name('proof_coordinator.')->group(function () {
    Route::get('dashboard', [ProofRequestController::class, 'dashboard'])->name('dashboard');

    // 依頼受信ボックス
    Route::get('inbox', [ProofRequestController::class, 'inbox'])->name('inbox');
    Route::post('inbox/{proofRequest}/accept', [ProofRequestController::class, 'accept'])->name('inbox.accept');

    // 割り振り管理
    Route::get('assignments', [ProofRequestController::class, 'assignments'])->name('assignments');
    Route::put('assignments/{proofRequest}/assign', [ProofRequestController::class, 'assign'])->name('assignments.assign');
    Route::put('assignments/{proofRequest}/start', [ProofRequestController::class, 'start'])->name('assignments.start');
    Route::put('assignments/{proofRequest}/complete', [ProofRequestController::class, 'complete'])->name('assignments.complete');

    // 校正カレンダー
    Route::get('calendar', [ProofRequestController::class, 'calendar'])->name('calendar');

    // 校正員作業量
    Route::get('workload', [ProofRequestController::class, 'workload'])->name('workload');

    // 案件校正履歴
    Route::get('history', [ProofRequestController::class, 'history'])->name('history');
});

// ========== 全ロール共通（読み取り専用）==========
Route::middleware('auth')->prefix('proof')->name('proof.')->group(function () {
    // 校正カレンダー（全員閲覧可）
    Route::get('calendar', [ProofRequestController::class, 'calendarPublic'])->name('calendar');
    // 校正ステータス一覧（全員閲覧可）
    Route::get('status', [ProofRequestController::class, 'statusPublic'])->name('status');
});

// ========== 校正依頼作成（全ロール）==========
// ジョブ詳細から校正依頼
Route::middleware('auth')->post('proof-requests', [ProofRequestController::class, 'store'])->name('proof_requests.store');
Route::middleware('auth')->delete('proof-requests/{proofRequest}', [ProofRequestController::class, 'destroy'])->name('proof_requests.destroy');
```

### ProofCoordinatorMiddleware

```php
// app/Http/Middleware/ProofCoordinatorMiddleware.php
public function handle(Request $request, Closure $next): Response
{
    $user = Auth::user();
    if (!$user) abort(403);

    $allowed = $user->isProofCoordinator()
        || $user->isAdmin()
        || $user->isSuperAdmin()
        || ($user->isLeader() && $user->isDepartmentLeader()); // 部署リーダーのみ

    if (!$allowed) abort(403, 'ProofCoordinator access required.');

    return $next($request);
}
```

---

## 9. コントローラ設計

### `ProofRequestController.php`

```php
namespace App\Http\Controllers\ProofCoordinator;

class ProofRequestController extends Controller
{
    // ---- 管理側 ----

    // GET /proof-coordinator/inbox
    // pending 状態の依頼一覧を返す
    public function inbox(): Response

    // POST /proof-coordinator/inbox/{proofRequest}/accept
    // proof_coordinator_id を自分にセット（受理）
    public function accept(ProofRequest $proofRequest): Response

    // GET /proof-coordinator/assignments
    // assigned / in_progress の依頼＋割り当て管理表データ
    public function assignments(): Response

    // PUT /proof-coordinator/assignments/{proofRequest}/assign
    // proofreader_id をセット → status = assigned
    public function assign(Request $request, ProofRequest $proofRequest): Response

    // PUT /proof-coordinator/assignments/{proofRequest}/start
    // status = in_progress
    public function start(ProofRequest $proofRequest): Response

    // PUT /proof-coordinator/assignments/{proofRequest}/complete
    // status = completed、completed_at セット、通知送信
    public function complete(ProofRequest $proofRequest): Response

    // GET /proof-coordinator/calendar
    // 管理者向け詳細カレンダーデータ（担当者情報含む）
    public function calendar(): Response

    // GET /proof-coordinator/workload
    // 校正員ごとの作業量（月別、担当件数・完了件数）
    public function workload(): Response

    // GET /proof-coordinator/history
    // 案件ごとの校正履歴（完了・進行中含む全依頼）
    public function history(): Response

    // ---- 全員共通 ----

    // POST /proof-requests
    // 校正依頼作成（全ロール）
    public function store(Request $request): Response

    // DELETE /proof-requests/{proofRequest}
    // 校正依頼キャンセル（requester 本人のみ、pending 時のみ）
    public function destroy(ProofRequest $proofRequest): Response

    // GET /proof/calendar
    // 全員向け読み取り専用カレンダー（担当者名は表示、詳細は非表示）
    public function calendarPublic(): Response

    // GET /proof/status
    // 全員向けステータス一覧
    public function statusPublic(): Response
}
```

---

## 10. フロントエンド画面仕様

### 10-0. 全ロール共通タブへの追加

「校正カレンダー」と「校正状況」を各ロールのナビゲーションタブに追加する。

| ファイル | 追加タブ |
|---|---|
| `resources/js/Components/Tabs/UserNavigationTabs.vue` | 「校正カレンダー」`proof.calendar` / 「校正状況」`proof.status` |
| `resources/js/layouts/AppLayout.vue`（Coordinatorタブ） | 同上 |
| `resources/js/Components/Tabs/LeaderNavigationTabs.vue` | 同上 |

**追加タブの例（UserNavigationTabs.vue）:**
```html
<Link :href="route('proof.calendar')" :class="tab('proof_calendar')">
    校正カレンダー
</Link>
<Link :href="route('proof.status')" :class="tab('proof_status')">
    校正状況
</Link>
```

---

### 10-1. ProofCoordinatorNavigationTabs.vue

```
[ 校正依頼受信 ] [ 割り振り管理 ] [ 校正カレンダー ] [ 校正員作業量 ] [ 案件校正履歴 ]
```

- `proof_coordinator.inbox` がアクティブ → 「校正依頼受信」ハイライト
- 未対応依頼数バッジを「校正依頼受信」タブに表示

### 10-2. Inbox/Index.vue（校正依頼受信）

| 列 | 内容 |
|---|---|
| 依頼日時 | created_at |
| タイトル | proof_requests.title |
| 依頼者 | requester.name |
| 関連案件 | project_job.title（リンク） |
| 校正締め切り | deadline |
| ステータス | pending バッジ |
| アクション | 「受理」ボタン → accept API |

### 10-3. Assignments/Index.vue（割り振り管理）

- accepted（自分が受理済み）の依頼一覧
- 各行に「校正員を選択」セレクター（校正員候補: `assignments.code = 'kousei'`）
- 「割り当て」ボタン → assign API
- 「作業開始」ボタン → start API
- 「完了」ボタン → complete API（PCなし校正員の代理完了にも使用）

### 10-4. Calendar/Index.vue（校正カレンダー）

- 既存の FullCalendar コンポーネントを流用
- イベントソース: `proof_requests` の `deadline` 日
- イベント色: ステータス別（pending=灰、assigned=青、in_progress=橙、completed=緑）
- 管理者ビュー: 担当者名・依頼者名を表示

### 10-5. Workload/Index.vue（校正員作業量）

- 期間: 月別フィルター
- 表示: 校正員ごとの担当件数・完了件数・進行中件数
- テーブル形式（既存の WorkloadAnalyzer の UI を参考に）

### 10-6. History/Index.vue（案件校正履歴）

- 案件名・依頼日・完了日・依頼者・校正員・ステータスの一覧
- 案件名でフィルター可
- Coordinator も自案件のみ同等ビューを閲覧可（`Coordinator/ProjectJobs/Show.vue` に追加）

### 10-7. 「校正依頼」ボタンの設置箇所

以下の画面に「校正依頼」ボタンを追加:

| 画面 | ファイル | 条件 |
|---|---|---|
| MyJobBox 詳細 | `User/MyJobBox/Show.vue` | 常に表示（完了済みは非表示） |
| 進行表（User向け） | `User/ProjectJobs/Show.vue` | ProgressCell の joblink セル近傍 |
| 進行表（Coordinator向け） | `Coordinator/ProgressSheets/Show.vue` | 編集権限がある場合 |

**校正依頼モーダル（共通）:**
```
タイトル: [ジョブタイトルを引き継ぎ、編集可]
校正締め切り: [日付ピッカー]（必須）
備考: [テキストエリア]（任意）
[キャンセル] [校正依頼を送る]
```

送信: `POST /proof-requests` に以下を送信
```json
{
  "project_job_assignment_id": 123,
  "project_job_id": 45,
  "title": "○○原稿 第2章校正",
  "deadline": "2026-04-20",
  "note": ""
}
```

---

## 11. AppLayout.vue の変更点

### currentRouteContext への追加

```js
// 既存の if-chain に追加
if (r.startsWith('proof_coordinator.')) return 'proof_coordinator';
```

### ロールカラーマップへの追加

```js
proof_coordinator: 'bg-pink-600 text-white font-semibold',  // activeMap
proof_coordinator: 'text-pink-600 hover:text-pink-800',     // inactiveMap
```

### ナビゲーションリンク追加

```html
<!-- proof_coordinator ロールのメインナビ -->
<Link :href="route('proof_coordinator.dashboard')" :class="roleNavClass('proof_coordinator')">
  校正管理
</Link>
```

### タブ追加（`currentRouteContext === 'proof_coordinator'` のとき）

```html
<template v-else-if="currentRouteContext === 'proof_coordinator'">
  <ProofCoordinatorNavigationTabs />
</template>
```

---

## 12. User.php への追加

```php
public function isProofCoordinator(): bool
{
    return $this->user_role === 'proof_coordinator';
}

// 校正員候補かどうか（担当コードが kousei）
public function isProofreader(): bool
{
    return $this->assignment?->code === 'kousei';
}

// proof_requests リレーション
public function proofRequestsAsRequester()
{
    return $this->hasMany(ProofRequest::class, 'requester_id');
}

public function proofRequestsAsProofreader()
{
    return $this->hasMany(ProofRequest::class, 'proofreader_id');
}
```

---

## 13. アクセス制御マトリクス

| 機能 | SuperAdmin | Admin | 部署Leader | ProofCo | Coordinator | Clerk | User |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| 校正依頼 作成 | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| 校正依頼 キャンセル | ✓ | ✓ | ✓(自分) | ✓ | ✓(自分) | ✓(自分) | ✓(自分) |
| 受信BOX（inbox） | ✓ | ✓ | ✓ | ✓ | - | - | - |
| 割り振り管理 | ✓ | ✓ | ✓ | ✓ | - | - | - |
| 完了マーク（代理） | ✓ | ✓ | ✓ | ✓ | - | - | - |
| 校正カレンダー（全体） | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| 校正ステータス一覧 | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| 校正員作業量 | ✓ | ✓ | ✓ | ✓ | - | - | - |
| 案件校正履歴（全件） | ✓ | ✓ | ✓ | ✓ | - | - | - |
| 案件校正履歴（自案件・案件詳細内） | ✓ | ✓ | ✓ | ✓ | ✓ | - | ✓ |

---

## 14. 実装順序（推奨）

作業は必ず以下の順番で行うこと。各ステップ完了後にテストしてから次へ進む。

### Phase 1: 基盤（DB・モデル・ミドルウェア）

1. **Migration**: `proof_requests` テーブル作成
2. **Model**: `ProofRequest.php` 作成（fillable, casts, relations）
3. **User モデル**: `isProofCoordinator()` / `isProofreader()` / relations 追加
4. **Middleware**: `ProofCoordinatorMiddleware.php` 作成
5. **bootstrap/app.php**: ミドルウェア `'proof_coordinator'` エイリアス登録
6. **`php artisan migrate`** 実行（コンテナ内）

### Phase 2: バックエンド（ルート・コントローラ）

7. **routes/web.php**: proof_coordinator ルートグループ追加
8. **`ProofRequestController.php`**: 全メソッド実装
9. **`JobNotificationService.php`**: proof 系メソッド追加

### Phase 3: 管理者フロントエンド

10. **`ProofCoordinatorNavigationTabs.vue`** 作成
11. **`ProofCoordinator/Inbox/Index.vue`** 作成
12. **`ProofCoordinator/Assignments/Index.vue`** 作成
13. **`ProofCoordinator/Calendar/Index.vue`** 作成（FullCalendar 流用）
14. **`ProofCoordinator/Workload/Index.vue`** 作成
15. **`ProofCoordinator/History/Index.vue`** 作成
16. **`AppLayout.vue`** 変更（currentRouteContext / カラー / ナビ / タブ）

### Phase 4: 全員向けフロントエンド

17. **「校正依頼」ボタン**を各ページに追加（モーダル共通コンポーネント化推奨）
    - `User/MyJobBox/Show.vue`
    - `User/ProjectJobs/Show.vue`
    - `Coordinator/ProgressSheets/Show.vue`
18. **全員向け閲覧ページ** (`proof/calendar`, `proof/status`) 作成

### Phase 5: ロール対応

19. **`UserTable.vue`** / **`Admin/Users/Index.vue`**: `proof_coordinator` バッジ追加
20. **`AdminUserController.php`**: バリデーション enum 追加
21. **`CsvUpload/UserCsvController.php`**: CSV 変換マップ追加

### Phase 6: 仕上げ

22. **`npm run build`** 実行
23. **さくら本番**: `php artisan migrate` 実行（DEPLOY_SAKURA.md 参照）
24. **本番 Seeder**: 校正チーム（Team）追加 Seeder を実行

---

## 15. 注意事項・制約

1. **さくら本番制約**: `user_role` の enum 変更は ALTER TABLE が必要。マイグレーションで `DB::statement("ALTER TABLE users MODIFY COLUMN user_role ENUM(...)")` を使うが、SQLite（テスト環境）には `DB::getDriverName() === 'sqlite'` ガードを必ず付ける

2. **CSRF**: フロントからの POST は必ず Inertia の `router.post()` を使うこと。`fetch` を使う場合は `<meta name="csrf-token">` から取得する（さくらでは `XSRF-TOKEN` クッキーが発行されない）

3. **FullCalendar**: reactive Proxy をそのまま渡さない。`structuredClone` または `[...arr].map(e => ({...e}))` で plain オブジェクトに変換してから渡す

4. **`proof_coordinator` ルートコンテキスト**: `AppLayout.vue` の `currentRouteContext` は文字列 `'proof_coordinator'` を返す（アンダースコア区切りでルート名プレフィックスと一致させること）

5. **User モデル `user_role` の fillable**: `proof_coordinator` を追加するだけでなく、Admin 側のバリデーションも必ず更新すること（2箇所: create/update）

6. **Clerk との混同注意**: Clerk（紫）と ProofCoordinator（ピンク）は別ロール。Clerk は経理・事務。ProofCoordinator は校正窓口。

7. **`assignments.code = 'kousei'`** は既存 AssignmentSeeder に存在する（追加不要）

---

## 16. 将来の拡張（現時点では実装しない）

- 校正員（PCなし）向けのスマホ対応簡易ビュー
- 校正員ごとのシフト・稼働状況管理
- DTP 専門窓口など他部門への同様のフローの展開
- 校正履歴の PDF 出力

---

*このドキュメントは実装担当者が参照するための指示書です。不明点は本ドキュメントを作成した会話のコンテキストを参照してください。*
