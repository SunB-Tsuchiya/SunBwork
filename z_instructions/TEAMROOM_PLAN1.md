# TEAMROOM_PLAN1.md — チームルーム 詳細仕様・設計

---

## 概要

`team_type = 'unit'` のチームに対して、チーム専用のコラボレーションスペース（チームルーム）を提供する。  
URL: `/team-rooms` / `/team-rooms/{team}`  
アクセス制御: `team_user` ピボットに登録されているメンバーのみ（ロール問わず）

---

## タブ構成

| タブキー    | ラベル           | 流用元 |
|-----------|-----------------|--------|
| `overview`  | 概要・メンバー   | ProjectJob Show.vue |
| `schedule`  | スケジュール     | ProjectCalendar コンポーネント |
| `board`     | プロジェクトボード | Prepress/Board.vue（カスタマイズ版）|
| `minutes`   | 会議記録         | Diaries（Quill + コメント）|

---

## DB 設計

### 1. team_events（チームイベント／スケジュール）

```sql
CREATE TABLE team_events (
  id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  team_id        BIGINT UNSIGNED NOT NULL,
  user_id        BIGINT UNSIGNED NOT NULL,   -- 作成者
  title          VARCHAR(255) NOT NULL,
  description    TEXT NULL,
  starts_at      DATETIME NOT NULL,
  ends_at        DATETIME NULL,
  all_day        TINYINT(1) NOT NULL DEFAULT 0,
  created_at     TIMESTAMP NULL,
  updated_at     TIMESTAMP NULL,
  FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### 2. team_boards（ボード本体）

```sql
CREATE TABLE team_boards (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  team_id    BIGINT UNSIGNED NOT NULL UNIQUE,  -- チームに1つ
  name       VARCHAR(255) NOT NULL DEFAULT 'プロジェクトボード',
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
);
```

### 3. team_board_columns（ボードカラム：カスタマイズ可能）

```sql
CREATE TABLE team_board_columns (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  team_board_id BIGINT UNSIGNED NOT NULL,
  name          VARCHAR(100) NOT NULL,
  color         VARCHAR(50)  NOT NULL DEFAULT 'blue',   -- Tailwind カラーキー
  sort_order    INT NOT NULL DEFAULT 0,
  created_at    TIMESTAMP NULL,
  updated_at    TIMESTAMP NULL,
  FOREIGN KEY (team_board_id) REFERENCES team_boards(id) ON DELETE CASCADE
);
-- デフォルト3列: 予定/作業中/完了
```

### 4. team_board_cards（ボードカード）

```sql
CREATE TABLE team_board_cards (
  id                   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  team_board_id        BIGINT UNSIGNED NOT NULL,
  team_board_column_id BIGINT UNSIGNED NOT NULL,
  title                VARCHAR(255) NOT NULL,
  description          TEXT NULL,
  sort_order           INT NOT NULL DEFAULT 0,
  created_by           BIGINT UNSIGNED NOT NULL,
  created_at           TIMESTAMP NULL,
  updated_at           TIMESTAMP NULL,
  deleted_at           TIMESTAMP NULL,
  FOREIGN KEY (team_board_id)        REFERENCES team_boards(id)        ON DELETE CASCADE,
  FOREIGN KEY (team_board_column_id) REFERENCES team_board_columns(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by)           REFERENCES users(id)              ON DELETE CASCADE
);
-- 添付: attachmentables ポリモーフィック（attachable_type = 'App\Models\TeamBoardCard'）
```

### 5. team_meeting_minutes（会議記録）

```sql
CREATE TABLE team_meeting_minutes (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  team_id    BIGINT UNSIGNED NOT NULL,
  user_id    BIGINT UNSIGNED NOT NULL,   -- 作成者
  title      VARCHAR(255) NOT NULL,
  content    LONGTEXT NULL,              -- Quill HTML
  held_at    DATE NOT NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  FOREIGN KEY (team_id)  REFERENCES teams(id)  ON DELETE CASCADE,
  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE
);
-- 添付: attachmentables ポリモーフィック（attachable_type = 'App\Models\TeamMeetingMinute'）
```

### 6. team_meeting_attendees（会議参加者）

```sql
CREATE TABLE team_meeting_attendees (
  id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  team_meeting_minute_id  BIGINT UNSIGNED NOT NULL,
  user_id                 BIGINT UNSIGNED NOT NULL,
  created_at              TIMESTAMP NULL,
  updated_at              TIMESTAMP NULL,
  UNIQUE KEY uq_attendee (team_meeting_minute_id, user_id),
  FOREIGN KEY (team_meeting_minute_id) REFERENCES team_meeting_minutes(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)                REFERENCES users(id)                ON DELETE CASCADE
);
```

### 7. team_meeting_comments（会議記録コメント）

```sql
CREATE TABLE team_meeting_comments (
  id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  team_meeting_minute_id  BIGINT UNSIGNED NOT NULL,
  user_id                 BIGINT UNSIGNED NOT NULL,
  user_name               VARCHAR(255) NOT NULL,
  comment                 TEXT NOT NULL,
  created_at              TIMESTAMP NULL,
  updated_at              TIMESTAMP NULL,
  FOREIGN KEY (team_meeting_minute_id) REFERENCES team_meeting_minutes(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)                REFERENCES users(id)                ON DELETE CASCADE
);
```

---

## ルート設計

```php
// routes/web.php — ミドルウェア: auth, verified

Route::prefix('team-rooms')->name('team-rooms.')->group(function () {

    // チームルーム一覧・詳細
    Route::get('/',          [TeamRoomController::class, 'index'])->name('index');
    Route::get('/{team}',    [TeamRoomController::class, 'show'])->name('show');

    // スケジュール（イベント）— JSON API
    Route::prefix('{team}/events')->name('events.')->group(function () {
        Route::get('/',          [TeamEventController::class, 'index'])->name('index');
        Route::post('/',         [TeamEventController::class, 'store'])->name('store');
        Route::put('/{event}',   [TeamEventController::class, 'update'])->name('update');
        Route::delete('/{event}',[TeamEventController::class, 'destroy'])->name('destroy');
    });

    // ボード
    Route::prefix('{team}/board')->name('board.')->group(function () {
        Route::post('/',              [TeamBoardController::class, 'store'])->name('store');
        Route::put('/columns',        [TeamBoardController::class, 'updateColumns'])->name('columns.update');
        Route::post('/cards',         [TeamBoardCardController::class, 'store'])->name('cards.store');
        Route::put('/cards/{card}',   [TeamBoardCardController::class, 'update'])->name('cards.update');
        Route::delete('/cards/{card}',[TeamBoardCardController::class, 'destroy'])->name('cards.destroy');
    });

    // 会議記録
    Route::prefix('{team}/minutes')->name('minutes.')->group(function () {
        Route::get('/',              [TeamMeetingMinuteController::class, 'index'])->name('index');
        Route::post('/',             [TeamMeetingMinuteController::class, 'store'])->name('store');
        Route::get('/create',        [TeamMeetingMinuteController::class, 'create'])->name('create');
        Route::get('/{minute}',      [TeamMeetingMinuteController::class, 'show'])->name('show');
        Route::get('/{minute}/edit', [TeamMeetingMinuteController::class, 'edit'])->name('edit');
        Route::put('/{minute}',      [TeamMeetingMinuteController::class, 'update'])->name('update');
        Route::delete('/{minute}',   [TeamMeetingMinuteController::class, 'destroy'])->name('destroy');
        Route::post('/{minute}/comments', [TeamMeetingCommentController::class, 'store'])->name('comments.store');
        Route::delete('/{minute}/comments/{comment}', [TeamMeetingCommentController::class, 'destroy'])->name('comments.destroy');
    });
});
```

---

## コントローラー設計

### TeamRoomController
- `index()`: `team_user` ピボットで自分が所属する unit チームを取得 → `TeamRoom/Index.vue`
- `show($team)`: メンバー確認後、チーム情報・メンバー・ボード状態を渡す → `TeamRoom/Show.vue`
- 認可: `team_user` に `auth()->id()` が存在するか確認（全ロール共通）

### TeamEventController
- JSON API形式。認可は TeamRoom と同じメンバーシップチェック
- `index()`: チームのイベント一覧（FullCalendar 形式）
- `store()`, `update()`, `destroy()`: CRUD

### TeamBoardController
- `store()`: ボード新規作成 + デフォルト3列挿入（予定/作業中/完了）
- `updateColumns()`: 編集モードでのカラム追加・削除・順序変更・名前変更

### TeamBoardCardController
- `store()`: 新規カード作成（一覧から）
- `update()`: タイトル・説明・カラム移動（ステータス変更）を一括更新
- `destroy()`: ソフトデリート

### TeamMeetingMinuteController
- `create()`: チームメンバー一覧も渡す（参加者チェックボックス用）
- `store()`, `update()`: attendees の sync も実行
- 権限チェック: edit/update/destroy は `user_id === auth()->id()` OR `team.leader_id === auth()->id()`

### TeamMeetingCommentController
- `store()`: 全メンバーが投稿可能
- `destroy()`: 自分のコメントのみ削除可（日報と同仕様）

---

## モデル一覧

```
app/Models/
  TeamEvent.php              fillable: team_id, user_id, title, description, starts_at, ends_at, all_day
  TeamBoard.php              fillable: team_id, name / hasMany: columns, cards
  TeamBoardColumn.php        fillable: team_board_id, name, color, sort_order / hasMany: cards
  TeamBoardCard.php          fillable: team_board_id, team_board_column_id, title, description, sort_order, created_by
                             morphToMany: attachments (via attachmentables)
                             SoftDeletes
  TeamMeetingMinute.php      fillable: team_id, user_id, title, content, held_at
                             hasMany: attendees, comments
                             morphToMany: attachments (via attachmentables)
  TeamMeetingAttendee.php    fillable: team_meeting_minute_id, user_id
  TeamMeetingComment.php     fillable: team_meeting_minute_id, user_id, user_name, comment
```

---

## Vue ページ・コンポーネント一覧

```
resources/js/Pages/TeamRoom/
  Index.vue                  所属チームルーム一覧
  Show.vue                   タブ付きメイン画面（overview/schedule/board/minutes）
  Minutes/
    Create.vue               会議記録作成（Quill + 参加者チェックボックス + 添付）
    Show.vue                 会議記録詳細（本文 + 参加者 + コメント）
    Edit.vue                 会議記録編集

resources/js/Components/TeamRoom/
  TeamOverview.vue           概要・メンバー表示（ProjectJob流用）
  TeamSchedule.vue           スケジュールタブ（ProjectCalendar流用）
  TeamBoard.vue              ボードタブ（Kanban + 一覧 切り替え）
  TeamBoardCard.vue          カード表示コンポーネント
  TeamBoardEditMode.vue      ボード編集モード（カラム管理）
  TeamMinutesList.vue        会議記録一覧（タブ内埋め込み）
  MeetingCommentSection.vue  コメントセクション（Diary流用）
```

---

## Vue ページ レイアウト仕様（CONSOLIDATED_01 準拠）

### Index.vue（一覧）

```vue
<AppLayout title="チームルーム">
  <template #header>
    <h2 class="text-xl font-semibold leading-tight text-gray-800">チームルーム</h2>
  </template>

  <!-- カード一覧 -->
  <div class="rounded bg-white p-6 shadow">
    ...
  </div>
</AppLayout>
```

### Show.vue（詳細 + タブ）

```vue
<AppLayout :title="team.name">
  <template #header>
    <div class="flex items-center gap-3">
      <Link :href="route('team-rooms.index')"
        class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300 whitespace-nowrap"
      >← 一覧に戻る</Link>
      <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ team.name }}</h2>
    </div>
  </template>

  <template #tabs>
    <!-- タブナビゲーション（ProjectJob Show.vue 流用） -->
  </template>

  <!-- タブコンテンツ -->
  <div class="rounded bg-white p-6 shadow">
    ...
  </div>
</AppLayout>
```

### Minutes/Create.vue・Edit.vue

```vue
<AppLayout title="会議記録 作成">
  <template #header>
    <div class="flex items-center gap-3">
      <Link :href="route('team-rooms.show', { team: teamId })"
        class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300 whitespace-nowrap"
      >← チームルームに戻る</Link>
      <h2 class="text-xl font-semibold leading-tight text-gray-800">会議記録 作成</h2>
    </div>
  </template>

  <div class="rounded bg-white p-6 shadow">
    ...
  </div>
</AppLayout>
```

### 共通 NG パターン（このプロジェクトで禁止）

- `py-12` / `max-w-7xl mx-auto` をページ側に書かない（AppLayout 内部に実装済み）
- `<main>` タグ使用禁止
- `ToastUnified` をページ内で重複配置しない
- `route()` 呼び出し時は必ずオブジェクト形式: `route('team-rooms.show', { team: team.id })`

---

## フェーズ別タスク

### Phase 1: 基盤 + 概要・メンバータブ（約12ファイル）

**目標:** チームルームにアクセスできる最小構成を作る

| # | タスク | ファイル |
|---|-------|---------|
| 1 | マイグレーション作成（全7テーブル） | `database/migrations/` × 7 |
| 2 | モデル作成（全7モデル） | `app/Models/` × 7 |
| 3 | TeamRoomController 作成 | `app/Http/Controllers/TeamRoom/TeamRoomController.php` |
| 4 | ルート追加 | `routes/web.php` |
| 5 | TeamRoom/Index.vue 作成 | 所属チーム一覧カード表示 |
| 6 | TeamRoom/Show.vue 作成（タブシェル + overview タブ） | ProjectJob Show.vue の overview/members 流用 |
| 7 | TeamOverview.vue 作成 | チーム概要・メンバーリスト |
| 8 | サイドバーナビにリンク追加 | `AppLayout.vue` or `Sidebar.vue` |
| 9 | `npm run build` | — |

### Phase 2: スケジュールタブ（約3ファイル）

**目標:** チーム専用イベントの作成・表示

| # | タスク | ファイル |
|---|-------|---------|
| 1 | TeamEventController 作成 | `app/Http/Controllers/TeamRoom/TeamEventController.php` |
| 2 | TeamSchedule.vue 作成 | ProjectCalendar 流用、axios でイベント取得 |
| 3 | Show.vue に schedule タブ追加 | — |
| 4 | `npm run build` | — |

### Phase 3: 会議記録（約6ファイル）

**目標:** 会議記録の CRUD + コメント + 添付

| # | タスク | ファイル |
|---|-------|---------|
| 1 | TeamMeetingMinuteController 作成 | index, create, store, show, edit, update, destroy |
| 2 | TeamMeetingCommentController 作成 | store, destroy |
| 3 | Minutes/Create.vue 作成 | Quill + 参加者チェックボックス + 添付 |
| 4 | Minutes/Show.vue 作成 | 本文・参加者・コメント・添付 |
| 5 | Minutes/Edit.vue 作成 | Create 流用 |
| 6 | TeamMinutesList.vue 作成（タブ内一覧） | 日付・会議名・操作ボタン |
| 7 | Show.vue に minutes タブ追加 | — |
| 8 | `npm run build` | — |

### Phase 4: プロジェクトボード（約5ファイル）

**目標:** Kanban ボード + 一覧 + 編集モード

| # | タスク | ファイル |
|---|-------|---------|
| 1 | TeamBoardController 作成 | store, updateColumns |
| 2 | TeamBoardCardController 作成 | store, update, destroy |
| 3 | TeamBoard.vue 作成 | ボード/一覧 切り替え、新規作成ボタン |
| 4 | TeamBoardCard.vue 作成 | カード表示 |
| 5 | TeamBoardEditMode.vue 作成 | カラム管理 UI |
| 6 | カード添付 API 追加（既存 AttachmentController 流用） | — |
| 7 | Show.vue に board タブ追加 | — |
| 8 | `npm run build` | — |

---

## 認可ポリシー

```php
// TeamRoom 共通メンバーシップチェック
private function assertMember(Team $team): void
{
    $isMember = DB::table('team_user')
        ->where('team_id', $team->id)
        ->where('user_id', auth()->id())
        ->exists();

    if (! $isMember && ! auth()->user()->isSuperAdmin()) {
        abort(403);
    }
}

// team_type も確認
if ($team->team_type !== 'unit') abort(404);
```

---

## ボード編集モード 仕様

- 「編集」ボタンで編集モードに切り替え（保存/キャンセル）
- カラムの追加（名前・カラー入力）
- カラムの削除（カードがある場合は警告 → 強制削除 or キャンセル）
- カラムのタイトル変更
- カラムの並び替え（drag or ▲▼ ボタン）
- 保存時に `PUT /team-rooms/{team}/board/columns` で一括送信

---

## 変更ファイル一覧（全体）

### Laravel
```
database/migrations/
  xxxx_create_team_events_table.php
  xxxx_create_team_boards_table.php
  xxxx_create_team_board_columns_table.php
  xxxx_create_team_board_cards_table.php
  xxxx_create_team_meeting_minutes_table.php
  xxxx_create_team_meeting_attendees_table.php
  xxxx_create_team_meeting_comments_table.php

app/Models/
  TeamEvent.php
  TeamBoard.php
  TeamBoardColumn.php
  TeamBoardCard.php
  TeamMeetingMinute.php
  TeamMeetingAttendee.php
  TeamMeetingComment.php

app/Http/Controllers/TeamRoom/
  TeamRoomController.php
  TeamEventController.php
  TeamBoardController.php
  TeamBoardCardController.php
  TeamMeetingMinuteController.php
  TeamMeetingCommentController.php

routes/web.php                          （追記）
```

### Vue / Frontend
```
resources/js/Pages/TeamRoom/
  Index.vue
  Show.vue
  Minutes/Create.vue
  Minutes/Show.vue
  Minutes/Edit.vue

resources/js/Components/TeamRoom/
  TeamOverview.vue
  TeamSchedule.vue
  TeamBoard.vue
  TeamBoardCard.vue
  TeamBoardEditMode.vue
  TeamMinutesList.vue
  MeetingCommentSection.vue

resources/js/layouts/AppLayout.vue       （ナビ追記）
```

**合計: 約35ファイル（新規・追記）**
