# OPCAL_PLAN1.md — オペレーターカレンダー 詳細仕様・設計

---

## 概要

進行管理（Coordinator/Clerk/Admin/SuperAdmin）が、作業者（オペレーター）の一日の予定を横断して把握し、
「仮の空き予約」を入れられるカレンダー機能。

URL: `/coordinator/operator-calendar`（Coordinator タブメニューに新規ボタン追加）

**最重要方針:** この予約は「進行管理が空き状況を管理するための仮予約」であり、オペレーター本人の
実際のカレンダー・スケジュール（`events` テーブル）には一切反映されない。校正カレンダー
（`ProofCoordinator/Calendar.vue`）とは異なり、`events` への同期は行わない。

参考実装:
- 横断タイムラインUI・ドラッグ操作 → `app/Http/Controllers/ProofCoordinator/CalendarController.php` / `resources/js/Pages/ProofCoordinator/Calendar.vue`
- 担当色システム → `app/Http/Controllers/Prepress/BoardController.php` / `app/Models/PrepressColorAssignment.php`
- 共有メンバーリスト → `app/Models/ProofTeamMember.php`

---

## 確定した設計方針（ユーザーとの確認事項）

| 項目 | 決定内容 |
|---|---|
| 予約データの保存先 | `events` テーブルとは完全に別の新テーブル。本人の予定表には一切表示しない |
| 対象メンバー | 全ユーザーから自由に選択・追加（ロール不問） |
| メンバー一覧の共有範囲 | 全 Coordinator/Clerk で共有される1つのリスト（`ProofTeamMember` と同じ設計） |
| 案件名入力 | 自由テキスト（ProjectJob との紐付けなし） |
| 予約ブロックの色 | オペレーターカレンダー専用の新しい色割当テーブル。色は「予約者（Coordinator側）」の色であり、対象メンバー（オペレーター）の色ではない |
| 二重予約の扱い（Phase 1） | 許容し、警告なし。ブロックが重なって表示されるだけ |
| 二重予約の扱い（Phase 2・将来） | リクエスト→承諾/拒否→通知のワークフローを追加（下記「将来計画」参照） |
| 利用ロール | Coordinator + Clerk + Admin + SuperAdmin（**Leader は含まない**） |
| 編集・削除権限 | 誰でも（利用ロール内の全員）編集・削除可能 |
| 案件一覧トグルテーブルの範囲 | 全オペレーター・全期間の予約を全件表示（開始日・終了日・予約者・案件名） |

### ⚠️ ロールアクセスに関する注意
既存の `coordinator` ミドルウェア（`app/Http/Middleware/CoordinatorMiddleware.php`）は
Coordinator/Clerk/Leader/Admin/SuperAdmin を許可しているが、本機能は **Leader を含めない**
と決定した。そのため `OperatorCalendarController` 内で追加のロールチェックを行う
（`assertAccess()` で `isLeader()` の場合は 403）。

---

## UTC/JST 方針（重要）

新テーブル `operator_reservations` は `events` テーブルと無関係の独立テーブルなので、
校正カレンダー（`proof_schedules`）で発生している UTC/JST 混在問題を踏襲しない。

**`starts_at` / `ends_at` は通常イベントと同じ「JST文字列をそのまま格納」方式を採用する。**
Carbon の `datetime` キャストは `config('app.timezone')`（Asia/Tokyo）で解釈されるため、
DB には `Y-m-d H:i:s` の JST 値をそのまま保存し、`->utc()` 変換や `getRawOriginal()` の
特殊処理は行わない。フロント⇔バック間のやり取りは ISO8601（UTC表記）で統一し、
表示直前に `Asia/Tokyo` に変換する。

---

## DB 設計

### 1. operator_calendar_members（共有メンバー一覧）

```sql
CREATE TABLE operator_calendar_members (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     BIGINT UNSIGNED NOT NULL UNIQUE,
  sort_order  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at  TIMESTAMP NULL,
  updated_at  TIMESTAMP NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```
`ProofTeamMember` と同一設計。「+メンバー」で追加、行の×ボタンで削除、並び替え可。

### 2. operator_calendar_color_assignments（予約者の色）

```sql
CREATE TABLE operator_calendar_color_assignments (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  color_key   VARCHAR(20) NOT NULL UNIQUE,
  user_id     BIGINT UNSIGNED NULL,
  sort_order  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at  TIMESTAMP NULL,
  updated_at  TIMESTAMP NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
```
`prepress_color_assignments` と同一設計・同一11色パレットを初期投入
（indigo, blue, teal, green, yellow, orange, red, pink, purple, gray, cyan）。
**製版ボードとは別テーブル**（同じ人でも製版ボードとオペレーターカレンダーで違う色になり得る）。

### 3. operator_reservations（予約本体）

```sql
CREATE TABLE operator_reservations (
  id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  operator_user_id      BIGINT UNSIGNED NOT NULL,   -- 予約対象（行＝オペレーター本人）
  reserved_by_user_id   BIGINT UNSIGNED NOT NULL,   -- 予約者（モーダルのセレクターで選択、色に使用）
  created_by_user_id    BIGINT UNSIGNED NOT NULL,   -- 実際に操作したユーザー（監査用）
  job_name              VARCHAR(255) NOT NULL,
  memo                  TEXT NULL,
  starts_at             DATETIME NOT NULL,          -- JST文字列そのまま格納
  ends_at               DATETIME NOT NULL,
  created_at            TIMESTAMP NULL,
  updated_at            TIMESTAMP NULL,
  FOREIGN KEY (operator_user_id)    REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (reserved_by_user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by_user_id)  REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_operator_time (operator_user_id, starts_at, ends_at)
);
```

---

## モデル一覧

```
app/Models/
  OperatorCalendarMember.php            fillable: user_id, sort_order / belongsTo: user
  OperatorCalendarColorAssignment.php   fillable: color_key, user_id, sort_order / belongsTo: user
  OperatorReservation.php               fillable: operator_user_id, reserved_by_user_id, created_by_user_id,
                                                    job_name, memo, starts_at, ends_at
                                         casts: starts_at/ends_at => datetime
                                         belongsTo: operatorUser, reservedByUser, createdByUser（すべて User::class）
```

---

## ルート設計

```php
// routes/web.php — 既存の Route::middleware([...,'coordinator'])->prefix('coordinator') グループ内に追記

Route::prefix('operator-calendar')->name('operator_calendar.')->group(function () {
    Route::get('/',                [OperatorCalendarController::class, 'index'])->name('index');
    Route::get('/data',            [OperatorCalendarController::class, 'data'])->name('data');           // 日付切替
    Route::get('/all',             [OperatorCalendarController::class, 'all'])->name('all');              // 案件一覧トグル用（全件）

    Route::post('/members',                 [OperatorCalendarController::class, 'storeMember'])->name('members.store');
    Route::delete('/members/{user}',        [OperatorCalendarController::class, 'destroyMember'])->name('members.destroy');
    Route::patch('/members/reorder',        [OperatorCalendarController::class, 'reorderMembers'])->name('members.reorder');

    Route::post('/reservations',                       [OperatorCalendarController::class, 'store'])->name('reservations.store');
    Route::put('/reservations/{operatorReservation}',  [OperatorCalendarController::class, 'update'])->name('reservations.update');
    Route::delete('/reservations/{operatorReservation}',[OperatorCalendarController::class, 'destroy'])->name('reservations.destroy');

    Route::patch('/color-assignments/{colorKey}', [OperatorCalendarController::class, 'updateColorAssignment'])->name('color_assignments.update');
});
```

---

## コントローラー設計（`app/Http/Controllers/Coordinator/OperatorCalendarController.php`）

### 共通
- 全メソッド冒頭で `assertAccess()` を呼ぶ：`isCoordinator() || isClerk() || isAdmin() || isSuperAdmin()` が false なら 403（**Leader除外**）

### index(Request $request): Response
`Inertia::render('Coordinator/OperatorCalendar', [...])`
- `members`: `operator_calendar_members` 一覧（user名付き）
- `colorAssignments`: `operator_calendar_color_assignments` 一覧（user名付き）
- `reservations`: 指定日（デフォルト今日）の予約一覧
- `date`
- `assignableUsers`: 予約者セレクター用（Coordinator/Clerk/Admin/SuperAdmin ロールのユーザー一覧）

### data(Request $request): JsonResponse
日付切替用。`reservations`（指定日分）を返す。

### all(): JsonResponse
案件一覧トグルテーブル用。全期間・全オペレーターの `operator_reservations` を
`starts_at` 昇順で全件返す（開始日・終了日・予約者名・案件名）。

### storeMember / destroyMember / reorderMembers
`ProofTeamMember` の CRUD と同じパターン。`storeMember` は `user_id` を受け取り重複チェック。

### store(Request $request): JsonResponse
```php
$data = $request->validate([
    'operator_user_id'    => 'required|exists:users,id',
    'reserved_by_user_id' => 'required|exists:users,id',
    'job_name'             => 'required|string|max:255',
    'memo'                 => 'nullable|string',
    'starts_at'            => 'required|date',
    'ends_at'              => 'required|date|after:starts_at',
]);
$data['created_by_user_id'] = auth()->id();
OperatorReservation::create($data);
```

### update / destroy
校正カレンダーの `update`/`destroy` と同様（ドラッグ移動・リサイズ・内容編集・削除）。
`events` への同期処理は一切行わない。

### updateColorAssignment(Request $request, string $colorKey)
`Prepress\BoardController::updateColorAssignment()` と同一パターン
（`color_key` で `operator_calendar_color_assignments` を検索し `user_id` を更新）。

---

## Vue ページ・コンポーネント一覧

```
resources/js/Pages/Coordinator/
  OperatorCalendar.vue     オペレーターカレンダー本体（新規）
```

### OperatorCalendar.vue 仕様

`ProofCoordinator/Calendar.vue` の日表示タイムライン実装（メンバー行 × 時間軸グリッド、
`%` 絶対配置、mousedown/mousemove/mouseup によるドラッグ選択）を流用・簡略化する。
FullCalendar は使わず自作タイムラインで統一する。

**主要UI要素:**
1. **日付ナビゲーション**: 前日/翌日ボタン + 日付ピッカー（校正カレンダーと同じ）
2. **「+メンバー」ボタン**: 全ユーザー検索・選択できるモーダル/ドロップダウン → `members.store`
   - 各メンバー行に×ボタン（削除）→ `members.destroy`
3. **色設定パネル**（トグルで開閉、デフォルトOFF）: 製版ボードの「担当色変更」パネルと同UI。
   色スウォッチ一覧 → 各色にユーザーをセレクターで割当 → `color_assignments.update`
4. **タイムライングリッド**: `members` を行としてループ、8:00〜19:00 を列として時間軸描画
   （時間範囲は定数化し調整可能にする）
5. **ドラッグして時間範囲選択 → 予約作成モーダル**:
   - 「予約者」セレクター（`assignableUsers` から選択、デフォルト = 自分）
   - 「案件名」テキスト入力（1行、必須）
   - 「メモ」テキストエリア（任意）
   - 保存ボタン → `reservations.store`
6. **既存予約ブロック**: 色 = `reserved_by_user_id` の色（`colorAssignments` から解決）。
   クリックで編集モーダル（内容編集＋削除ボタン）。ドラッグ移動・端リサイズ対応（校正カレンダー同様）。
7. **「案件一覧」トグルボタン**（デフォルトOFF、`ProjectCalendar.vue` のスケジュールパネルと同UI）:
   開くと `all` エンドポイントを叩き、開始日・終了日・予約者・案件名の4列テーブルを表示。

---

## メニュー統合

`resources/js/Components/Tabs/CoordinatorNavigationTabs.vue` の `tabs` computed とデスクトップ `<nav>` に
以下を追加（案件カレンダーの次あたりに配置）:

```js
{ key: 'operator_calendar', href: route('coordinator.operator_calendar.index'), label: 'オペレーターカレンダー' }
```

---

## 別件: メンバー予定表のフィルタ変更

`app/Http/Controllers/Coordinator/ProjectJobMemberScheduleController.php` の
`getEventsForDate()` 内、Event クエリに以下を追加する:

```php
$eventModels = Event::whereIn('user_id', $memberIds)
    ->whereNotNull('event_item_type_id')   // ← 追加：会議/外出/打合せ等のブロッキング予定のみ
    ->where(function ($q) use ($dayStart, $dayEnd) { ... })
    ->get();
```

**理由:** マイジョブ・Coordinator割当ジョブ等は `event_item_type_id` を持たず
`project_job_assignment_id` で紐づくため、この条件だけで「絶対に作業できない予定
（打合せ社内/打合せ顧客/会議/外出/顧客訪問/そのほか）」のみに絞り込める。
色分けロジック（予定=ティール等）は変更不要（該当ケースが実質発生しなくなるだけ）。

このファイルは既存機能の修正であり、オペレーターカレンダー本体とは独立して着手できる
（Phase 1 の一部として実施）。

---

## フェーズ別タスク

### Phase 1: オペレーターカレンダー本体 + メンバー予定表フィルタ修正（約11ファイル）

| # | タスク | ファイル |
|---|-------|---------|
| 1 | マイグレーション: operator_calendar_members | `database/migrations/` |
| 2 | マイグレーション: operator_calendar_color_assignments（11色初期投入） | `database/migrations/` |
| 3 | マイグレーション: operator_reservations | `database/migrations/` |
| 4 | `php artisan migrate` | — |
| 5 | Model: OperatorCalendarMember | `app/Models/` |
| 6 | Model: OperatorCalendarColorAssignment | `app/Models/` |
| 7 | Model: OperatorReservation | `app/Models/` |
| 8 | OperatorCalendarController 作成（index/data/all/members系/reservations系/color系） | `app/Http/Controllers/Coordinator/` |
| 9 | ルート追加 | `routes/web.php` |
| 10 | OperatorCalendar.vue 作成 | `resources/js/Pages/Coordinator/` |
| 11 | CoordinatorNavigationTabs.vue にタブ追加 | 既存修正 |
| 12 | ProjectJobMemberScheduleController フィルタ修正 | 既存修正 |
| 13 | `npm run build` | — |

### Phase 2（将来計画・未着手）: 二重予約リクエスト機能

Phase 1 完了後、Claude から改めて詳細設計を提案する。現時点でのアイデアメモ:
- 同一オペレーター・重複時間帯での新規予約時、既存予約と衝突する場合は「リクエストを出す」導線を表示
- `operator_reservation_requests` テーブル（or `operator_reservations.status` 拡張）でリクエスト状態管理（pending/approved/rejected）
- 通知: 既存の `ScheduleNotification` パターンを流用し、
  - リクエスト発行時 → 既存予約の `reserved_by_user_id` に通知
  - 承諾/拒否時 → リクエスト発行者に通知
- カレンダー上でリクエスト中の枠を点滅表示 or 専用配色
- 承諾/拒否 UI（カレンダー上のブロック、または通知一覧から）

---

## 変更ファイル一覧（全体・Phase 1）

### Laravel
```
database/migrations/
  xxxx_create_operator_calendar_members_table.php
  xxxx_create_operator_calendar_color_assignments_table.php
  xxxx_create_operator_reservations_table.php

app/Models/
  OperatorCalendarMember.php
  OperatorCalendarColorAssignment.php
  OperatorReservation.php

app/Http/Controllers/Coordinator/
  OperatorCalendarController.php                 （新規）
  ProjectJobMemberScheduleController.php         （既存修正: 1行追加）

routes/web.php                                    （追記）
```

### Vue / Frontend
```
resources/js/Pages/Coordinator/
  OperatorCalendar.vue                            （新規）

resources/js/Components/Tabs/
  CoordinatorNavigationTabs.vue                   （既存修正: タブ1件追加）
```

**合計: 約11ファイル（新規7・修正4）**
