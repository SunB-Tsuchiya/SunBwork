# SCHED_PLAN1.md — 予定表機能 詳細設計

最終更新: 2026-06-21  
ステータス: 全フェーズ完了・さくらデプロイ準備中

---

## 修正履歴（ブラウザテスト後のフィードバック対応）

### Round 1 (2026-06-13)

| # | 内容 | 変更ファイル |
|---|------|-------------|
| R1-1 | 会議室カラムクリック時に該当会議室を自動選択 | DayView.vue / ScheduleCalendar.vue / RoomReservationModal.vue |
| R1-2 | 会議室カラムでドラッグによる時間範囲選択（自分カラムと同様） | DayView.vue |
| R1-3 | 時間ピッカーを15分単位に変更（step=900） | EventModal.vue / RoomReservationModal.vue |
| R1-4 | 同日をまたいだイベント・予約を禁止（バックエンドバリデーション追加） | ScheduleEventController.php / ScheduleRoomReservationController.php |
| R1-5 | 会議室の予約可能時間（available_from/available_to）設定・表示・検証 | migration追加 / MeetingRoom.php / MeetingRoomController.php / ScheduleRoomReservationController.php / DayView.vue / Admin/MeetingRooms/*.vue |
| R1-6 | カラムの折りたたみ（◀/▶ ボタン）・localStorage永続化 | DayView.vue |
| R1-7 | カラムのドラッグ並び替え・localStorage永続化（自分カラムは固定左） | DayView.vue |
| R1-8 | オーバーレイユーザーのカラムを右端（rooms後）に配置 | DayView.vue |

### Round 2 (2026-06-13)

| # | 内容 | 変更ファイル |
|---|------|-------------|
| R2-1 | datetime-local → date(input[type=date]) + time(step=900) 分離で15分単位を確実に | EventModal.vue / RoomReservationModal.vue |
| R2-2 | 予約不可時間クリック時のメッセージをバナー → Toast に変更 | DayView.vue |
| R2-3 | OverlayPanelから会社/部署タブを撤廃（個人ユーザー選択のみ、会社/部署は絞り込みに） | OverlayPanel.vue |
| R2-4 | Admin会議室ページのタブ二重表示を修正（AppLayout自動タブと重複していた） | Admin/MeetingRooms/{Index,Create,Edit}.vue |

### Codex R3 (2026-06-21) — 第3回コードレビュー修正

| # | 内容 | 変更ファイル |
|---|------|-------------|
| CR3-1 | `destroy()` が link_event_id で紐づけた既存イベントを削除するバグ修正 → `room_reservations.event_owned` カラム追加（migration）、destroy() で owned か否かを判定してイベント削除 or リンク解除 | migration追加 / RoomReservation.php / ScheduleRoomReservationController.php |
| CR3-2 | `update()` の参加者リビルドが accepted/declined を全員 pending にリセットするバグ修正 → delete→insert を差分更新（追加・削除のみ・既存ステータス保持）に変更 | ScheduleRoomReservationController.php |
| CR3-3 | `conflicts()` が参加者として招待されたイベントを見落とすバグ修正 → schedule_attendees を OR で参照するクエリに変更 | ScheduleEventController.php |
| CR3-4 | `respond()` の辞退通知が二重作成されるバグ修正 → `ScheduleNotification::create()` を `firstOrCreate()` に変更 | ScheduleAttendeeController.php |
| CR3-5 | `filterAttendeesByPermission()` デッドコード削除（実際には `validateAttendeeScope()` が動いており未使用） | ScheduleEventController.php |

### Calendar UX Fix (2026-06-21) — 現在時刻スクロール・固定ヘッダー

| # | 内容 | 変更ファイル |
|---|------|-------------|
| CUX-1 | 共用カレンダーシェルの高さをビューポート内に確定し、時刻グリッドが内部スクロールするよう `min-height: 0` を設定 | CalendarShell.vue |
| CUX-2 | week の初回表示・タブ遷移・day/week 切替時に、JSTの現在時刻（対象期間外は8:00）へスクロール。描画競合対策としてスクロール可能になるまで最大6フレーム再試行 | WeekView.vue |
| CUX-3 | `/calendar` day の内部タイムラインを確定高にし、初回表示・日付変更時の現在時刻スクロールを最大6フレーム再試行 | Calendar/UserDayView.vue |
| CUX-4 | week の曜日・日付・勤務形態ヘッダーを上部固定し、時刻列を左固定 | WeekView.vue |
| CUX-5 | `/calendar` day の日付ヘッダーを上部固定 | Calendar/UserDayView.vue |
| CUX-6 | `/schedule` day の各カラムヘッダーを上部固定し、日付・勤務形態・時刻列を左上固定 | DayView.vue |
| CUX-7 | `npm run build` 実行成功 | public/build/* |

### Round 3 (2026-06-13)

| # | 内容 | 変更ファイル |
|---|------|-------------|
| R3-1 | モーダルの日付をinput[type=date]で選択可能に（クリックした日がデフォルト・変更可） | EventModal.vue / RoomReservationModal.vue |
| R3-2 | 会議室予約ブロックに予約者名を表示 | ScheduleEventController.php（with user:id,name追加）/ DayView.vue |
| R3-3 | 会議室予約の編集権限を登録者・Admin・SuperAdminのみに制限（他人分は閲覧専用モーダル） | ScheduleCalendar.vue / RoomReservationModal.vue（readOnly prop追加） |
| R3-4 | Admin会議室一覧テーブルに予約可能時間列を追加 | Admin/MeetingRooms/Index.vue |
| R3-5 | 全幅Outlookスタイルレイアウト実装 | AppLayout.vue（fluid prop追加）/ Schedule/Index.vue / ScheduleCalendar.vue（2ペイン化）/ MiniCalendar.vue（新規） |

---

## 検討事項・制約事項

| 項目 | 決定内容 |
|------|----------|
| 日をまたいだイベント | このページでは禁止。出張などは別途対応予定。フロント(startTime<endTime検証)・バックエンド(checkSameDay)両方で制御 |
| 会議室予約可能時間 | Admin設定で per-room に available_from/available_to を設定。未設定=制限なし。バックエンドでバリデーション |
| 会議室予約編集権限 | 登録者・Admin・SuperAdminのみ編集可。他ユーザーには閲覧専用モーダル（予約者名表示） |
| カレンダー全幅表示 | AppLayoutにfluid propを追加（true時にmax-w-7xl制約を外す）。予定表ページのみ使用 |

---

## 未着手・今後詳細設計を行う機能

### F-1: 会議室予約のドラッグ移動・リサイズ

**概要:** 個人カラムと同様に、会議室カラムの既存予約ブロックも以下の操作を可能にする。

| 操作 | 挙動 |
|------|------|
| 上端ドラッグ | 開始時刻を変更（resize-top）|
| 下端ドラッグ | 終了時刻を変更（resize-bot）|
| ブロック全体ドラッグ（縦） | 同じ会議室内で開始〜終了時刻をずらす（move）|
| ブロック全体ドラッグ（横） | 別の会議室カラムへ移動（room 変更）|

**制約・考慮事項:**
- 移動・リサイズ後に **重複チェック**（`checkConflict`）を必ずサーバーで実行
- 移動先会議室の **予約可能時間（available_from/available_to）** を検証
- 日をまたいだ変更は禁止
- 横方向ドラッグ（会議室変更）は `meeting_room_id` の変更を伴うため、専用ルートまたは既存 PUT ルートの拡張を検討
- 権限: 変更可能なのは予約者・Admin・SuperAdmin のみ（それ以外は操作しても無視）
- **楽観的更新** → サーバー失敗時にロールバック（個人イベントと同じ方式）
- `DayView.vue` の `roomGridRefs` を活用して y 座標→時刻変換、x 座標→会議室カラム特定を実装

**変更ファイル予定:**
- `DayView.vue`（room リサイズ・移動 mousedown/mousemove/mouseup 追加）
- `ScheduleCalendar.vue`（`onRoomUpdate` ハンドラー追加）
- `ScheduleRoomReservationController.php`（`meeting_room_id` の変更を update で受け付ける）

**詳細はユーザーと相談してから設計確定する。**

---

### F-2: 参加者招待時の予定自動登録・通知

**概要:** 予定作成時・会議室予約時に参加者を追加すると、招待された側の予定にも自動登録され、通知が届く。

**現状の問題点:**
- `schedule_attendees` テーブルには招待レコードが作成される
- しかし `ScheduleEventController::range()` は `user_id = 自分` のイベントしか返さないため、**招待されたイベントが招待された側のカレンダーに表示されない**
- 招待時の通知レコード作成も未実装

**要件（詳細確認済み後に実装）:**

| 項目 | 方針（案）|
|------|-----------|
| 招待イベントの表示 | `range()` で `schedule_attendees` テーブルを JOIN し、自分が参加者に含まれるイベントも返す |
| 招待イベントの見た目 | `is_own = false` 相当（参照専用・淡色表示）。ただし参加者として招待されたイベントは削除不可 |
| 会議室予約経由 | 予約に紐づくイベントの attendees に入っていれば同様に表示 |
| 通知 | 参加者追加時に `schedule_notifications` レコードを `type = invited` で作成（朝一括とは別） |
| ステータス管理 | `schedule_attendees.status`（pending / accepted / declined）。将来的に承諾/辞退ボタンを追加可 |
| 表示対象 | `visibility` が `private` のイベントは招待された本人のみ表示（company/group/public は現行通り） |

**変更ファイル予定:**
- `ScheduleEventController.php`（`range()` に招待イベントの取得ロジック追加）
- `ScheduleRoomReservationController.php`（store/update で通知レコード作成）
- `ScheduleAttendeeController.php`（追加時に通知レコード作成）
- `DayView.vue` / `WeekView.vue`（招待イベントの表示スタイル検討）
- `NotificationPanel.vue`（invited 通知種別の対応）

**詳細（UI・承諾フロー・通知タイミング等）はユーザーと相談してから設計確定する。**

---

## 今後の追加機能設計（フィードバック対応・Phase 6以降）

### R4: 週・月ビューへの会議室予約表示

**確認済み方針（2026-06-14）:**

#### WeekView
- 個人予定グリッドの**下部**に「会議室」セクションを追加（行=会議室、列=月〜日）
- 各セル内に予約ブロックを時間比例の高さで縦表示（時間軸は縦のまま維持）
- 会議室セクションはツールバーのトグルボタンで表示/非表示を切り替え可（localStorage記憶）
- 予約ブロックをクリック → RoomReservationModal（権限に応じて編集/閲覧）

#### MonthView
- 日セルに `[田端] タイトル` 形式のチップを追加表示
- クリック → RoomReservationModal

#### バックエンド
- `ScheduleEventController.range()` は既に `reservations` を返しているためバックエンド変更不要

**変更ファイル:**
- `WeekView.vue`（下部に会議室セクション追加・トグル実装）
- `MonthView.vue`（日セルに会議室予約チップ追加）
- `ScheduleCalendar.vue`（`reservations` / `rooms` prop を WeekView・MonthView にも渡す、`openRoomEdit` を渡す）

---

### R5: 会議設定（MeetingDefinition）連動

**確認済み方針（2026-06-14）:**

#### 前提
- `MeetingDefinition` モデルに `members()` BelongsToMany（`meeting_definition_members` 中間テーブル）が実装済み
- `CreateInternalEvent.vue` に会議定義ピッカーUIが実装済み → スケジュールもこれに**揃える**
- `events.meeting_definition_id` カラムは既存

#### EventModal に追加
- 種類選択を `CreateInternalEvent.vue` と同じ**ラジオボタン形式**に統一
- `slug === 'conference'` 選択時に会議定義ピッカーを表示
- 選択時に自動入力:
  - タイトル（手動編集フラグ: `titleManuallyEdited` で保護）
  - 説明（`description`）
  - 開始・終了時刻（`start_time` / `end_time`）
  - 日付（次回開催日 `calcNextDate` / `calcNextMonthlyDate` ロジックを移植）
  - 参加者（`meeting.members` を `AttendeeSelector` の初期値に設定）

#### RoomReservationModal に追加
- 「会議種類（任意）」ドロップダウンを追加（フォーム上部）
- 選択時に自動入力:
  - タイトル（手動編集フラグで保護）
  - 参加者（`meeting.members` を `AttendeeSelector` の初期値に設定）
  - **時刻・日付は自動入力しない**（会議室の空き状況で決まるため）

#### バックエンド
- `ScheduleController.index()` で `meetingDefinitions`（自社分 + `members:id,name`）を props に追加
- `ScheduleEventController.store/update` で `meeting_definition_id` を受け付けて保存

**変更ファイル:**
- `EventModal.vue`（ラジオボタン形式統一・会議定義ピッカーUI追加・自動入力ロジック移植）
- `RoomReservationModal.vue`（会議種類ドロップダウン追加・タイトル/参加者自動入力）
- `ScheduleController.php`（`meetingDefinitions` を props に追加）
- `ScheduleEventController.php`（`meeting_definition_id` 保存対応）

---

### R6: イベント色の統一（既存カレンダーとの整合）

**背景:** 既存カレンダー（`/calendar`）の色体系:
- 案件打合せ・外出 → `bg-emerald-600` `#059669`
- 社内予定 → `bg-teal-600` `#0d9488`
- マイジョブ → `bg-indigo-600` `#4F46E5`
- 進行表・管理表 → `bg-purple-600` `#9333ea`

**event_item_type slug → 色マップ（JS定数）:**

```js
const EVENT_TYPE_COLORS = {
    customer_visit:   { bg: '#059669', text: '#fff', border: '#047857' }, // emerald（顧客訪問）
    meeting_client:   { bg: '#059669', text: '#fff', border: '#047857' }, // emerald（打合せ顧客）
    outing:           { bg: '#059669', text: '#fff', border: '#047857' }, // emerald（外出）
    meeting_internal: { bg: '#0d9488', text: '#fff', border: '#0f766e' }, // teal（打合せ社内）
    conference:       { bg: '#0d9488', text: '#fff', border: '#0f766e' }, // teal（会議）
    client_visit:     { bg: '#f59e0b', text: '#fff', border: '#d97706' }, // amber（来社対応）
    other:            { bg: '#6b7280', text: '#fff', border: '#4b5563' }, // gray（その他）
};
const DEFAULT_COLOR = { bg: '#3b82f6', text: '#fff', border: '#2563eb' }; // blue（種別なし）
```

**変更ファイル:** `DayView.vue`（evColor関数）/ `WeekView.vue`（evColor関数）
- `ev.event_item_type?.slug` を参照してマップから色を取得
- slug がない・マップにない場合は DEFAULT_COLOR にフォールバック
- ScheduleEventController は `with(['eventItemType:id,name,slug'])` を追加して slug をフロントに渡す

---

### R7: 会議室予約一覧（左サイドバー）

**確認済み方針（2026-06-14）:**
- ミニカレンダー下部に、`currentDate`（表示中の日付）の会議室予約を時系列で一覧表示
- 表示内容: 開始〜終了時刻・部屋名（色付き）・タイトル・予約者名
- `reservations` は既に取得済みのため、`currentDate` でフィルタリングするだけでよい
- クリック時の挙動:
  1. `viewMode = 'day'` に切り替え
  2. `currentDate` を予約の日付に設定（既に同じ日のはずだが念のため）
  3. `openRoomEdit(reservation)` でモーダルを開く

**変更ファイル:** `ScheduleCalendar.vue`（左サイドバーに予約一覧セクション追加）

---

## 概要

会社全体の会議・面談・打合せ・イベントを把握できる「予定表」機能を追加する。  
Outlookライクな共有カレンダー。個人カレンダー（events テーブル）と連動し、会議室予約・グループ会社間共有・通知機能を含む。

---

## 設計判断サマリー

| 項目 | 決定 |
|------|------|
| データ基盤 | 既存 `events` テーブルを拡張（`is_company_event` フラグで分離） |
| 権限 | 全ユーザー自分の予定を作成可・Coordinator以上が他者予定に追加可・Admin設定で変更可 |
| グループ会社 | `company_groups` / `company_group_members` 新テーブルで汎用管理 |
| カレンダー実装 | ProofCoordinator方式の完全自作（FullCalendar不使用） |
| 会議室予約 | `room_reservations` 独立テーブル（events と任意紐付け） |
| 通知 | 朝8時一括（さくらcron）＋開始前リマインダー（実装可能なら） |
| 他者閲覧 | デフォルト自分のみ・会社/部署/個人単位でオーバーレイ追加可 |
| ナビゲーション | AppLayout通知エリアにカレンダーアイコン追加（全ロール共通） |

---

## DB設計

### 新規テーブル一覧

#### `company_groups` — グループ会社管理

```sql
id                bigint PK
name              string          -- グループ名（例: サン・グループ）
description       text nullable
group_key         string unique   -- 識別キー（例: suna_group）
active            boolean default true
created_by        FK users
timestamps
```

#### `company_group_members` — グループ所属会社

```sql
id                bigint PK
company_group_id  FK company_groups (cascade)
company_id        FK companies (cascade)
UNIQUE(company_group_id, company_id)
timestamps
```

#### `meeting_rooms` — 会議室マスタ

```sql
id          bigint PK
company_id  FK companies (cascade)
name        string              -- 田端会議室 等
capacity    tinyint unsigned nullable
description text nullable
color       string(7) nullable  -- カレンダー色 (#rrggbb)
active      boolean default true
sort_order  int unsigned default 0
timestamps
```

初期データ（田端 = サン・ブレーン会社ID）:
- 田端会議室
- 田端多目的ルーム
- 田端応接室

#### `room_reservations` — 会議室予約

```sql
id               bigint PK
meeting_room_id  FK meeting_rooms (cascade)
user_id          FK users (cascade)     -- 予約者
event_id         FK events nullable nullOnDelete  -- 任意の関連イベント
event_owned      boolean default false  -- true=予約と同時に作成したイベント / false=既存イベントをリンク
title            string
starts_at        timestamp
ends_at          timestamp
notes            text nullable
timestamps
```

#### `schedule_attendees` — 予定参加者

```sql
id        bigint PK
event_id  FK events (cascade)
user_id   FK users (cascade)
status    enum(pending, accepted, declined) default pending
added_by  FK users nullable   -- 追加者
UNIQUE(event_id, user_id)
timestamps
```

#### `schedule_calendar_overlays` — オーバーレイカレンダー設定

```sql
id                   bigint PK
user_id              FK users (cascade)       -- 閲覧者（自分）
target_user_id       FK users nullable nullOnDelete
target_company_id    FK companies nullable nullOnDelete
target_department_id FK departments nullable nullOnDelete
sort_order           int unsigned default 0
timestamps
-- NOTE: target_* はいずれか1つのみ非null
```

#### `schedule_notifications` — 予定通知管理

```sql
id            bigint PK
event_id      FK events (cascade)
user_id       FK users (cascade)
type          enum(morning_summary, pre_event_reminder)
scheduled_at  timestamp   -- 通知予定時刻
notified_at   timestamp nullable
read_at       timestamp nullable
timestamps
```

#### `schedule_permission_settings` — 会社別権限設定

```sql
id                        bigint PK
company_id                FK companies unique (cascade)
can_add_to_others_min_role string(32) default 'coordinator'
                          -- coordinator / leader / admin / superadmin
timestamps
```

---

### 既存テーブル変更

#### `events` テーブルに追加

```sql
is_company_event  boolean default false  -- 予定表に表示するフラグ
visibility        enum(private, company, group, public) default private
organizer_id      FK users nullable nullOnDelete  -- 作成者≠自分のとき
room_reservation_id FK room_reservations nullable nullOnDelete
```

**注意:** `event_item_type_id`（会議・打合せ・外出等）は既存。予定表では同じ分類を使い回す。

---

## ルート設計

```
// 予定表（全ロール共通）
GET  /schedule                              schedule.index
POST /schedule/events                       schedule.events.store
PUT  /schedule/events/{event}              schedule.events.update
DEL  /schedule/events/{event}              schedule.events.destroy
GET  /schedule/events/{event}              schedule.events.show  (JSON)
GET  /schedule/events/range                schedule.events.range (JSON: 期間取得)

// 参加者
POST /schedule/events/{event}/attendees    schedule.attendees.store
DEL  /schedule/events/{event}/attendees/{user} schedule.attendees.destroy

// 会議室（参照は全ロール、作成・更新はAdmin+）
GET  /schedule/rooms                       schedule.rooms.index (JSON)
GET  /admin/meeting-rooms                  admin.meeting-rooms.index
POST /admin/meeting-rooms                  admin.meeting-rooms.store
PUT  /admin/meeting-rooms/{room}           admin.meeting-rooms.update
DEL  /admin/meeting-rooms/{room}           admin.meeting-rooms.destroy

// 会議室予約（全ロール）
POST /schedule/rooms/{room}/reservations   schedule.room-reservations.store
PUT  /schedule/room-reservations/{res}     schedule.room-reservations.update
DEL  /schedule/room-reservations/{res}     schedule.room-reservations.destroy

// オーバーレイ設定
GET  /schedule/overlays                    schedule.overlays.index  (JSON)
POST /schedule/overlays                    schedule.overlays.store
DEL  /schedule/overlays/{overlay}          schedule.overlays.destroy

// 通知
GET  /schedule/notifications               schedule.notifications.index
PUT  /schedule/notifications/{n}/read      schedule.notifications.read

// 管理設定
GET  /admin/schedule-settings              admin.schedule-settings.edit
PUT  /admin/schedule-settings              admin.schedule-settings.update

// グループ会社（SuperAdmin）
Resource /super-admin/company-groups       super-admin.company-groups.*
```

---

## コントローラー設計

| ファイル | 役割 |
|---------|------|
| `ScheduleController` | 予定表ページ表示・月/週/日データ取得 |
| `ScheduleEventController` | is_company_event=true の events CRUD |
| `ScheduleAttendeeController` | 参加者追加・削除（権限チェック含む） |
| `Admin/MeetingRoomController` | 会議室マスタ管理 |
| `ScheduleRoomReservationController` | 会議室予約 CRUD |
| `ScheduleOverlayController` | オーバーレイ設定 CRUD |
| `ScheduleNotificationController` | 通知一覧・既読処理 |
| `Admin/SchedulePermissionController` | 権限設定ページ |
| `SuperAdmin/CompanyGroupController` | グループ会社管理 |

---

## Vue コンポーネント設計

### Pages（新規）

| ファイル | 内容 |
|---------|------|
| `Pages/Schedule/Index.vue` | 予定表メインページ |
| `Pages/Admin/MeetingRooms/Index.vue` | 会議室一覧 |
| `Pages/Admin/MeetingRooms/Create.vue` | 会議室作成 |
| `Pages/Admin/MeetingRooms/Edit.vue` | 会議室編集 |
| `Pages/Admin/SchedulePermissions/Edit.vue` | 権限設定 |
| `Pages/SuperAdmin/CompanyGroups/Index.vue` | グループ会社一覧 |
| `Pages/SuperAdmin/CompanyGroups/Create.vue` | グループ作成 |
| `Pages/SuperAdmin/CompanyGroups/Edit.vue` | グループ編集 |

### Components（新規）

| ファイル | 内容 |
|---------|------|
| `Components/Schedule/ScheduleCalendar.vue` | メインカレンダー（月/週/日ビュー自作） |
| `Components/Schedule/DayView.vue` | 日ビュー（複数人横並びタイムライン） |
| `Components/Schedule/WeekView.vue` | 週ビュー |
| `Components/Schedule/MonthView.vue` | 月ビュー |
| `Components/Schedule/EventModal.vue` | 予定作成・編集モーダル |
| `Components/Schedule/EventDetailModal.vue` | 予定詳細（自分=全詳細/他人=タイトルのみ） |
| `Components/Schedule/RoomReservationModal.vue` | 会議室予約モーダル |
| `Components/Schedule/OverlayPanel.vue` | オーバーレイカレンダー追加パネル |
| `Components/Schedule/NotificationPanel.vue` | 通知パネル（開閉可・デフォルト閉） |
| `Components/Schedule/AttendeeSelector.vue` | 参加者選択コンポーネント |

### 既存変更

| ファイル | 変更内容 |
|---------|---------|
| `layouts/AppLayout.vue` | 通知エリアにカレンダーアイコン追加・バッジ表示 |
| `Models/Event.php` | `is_company_event`・`visibility`・`organizer_id` 追加 |

---

## カレンダービュー仕様

### 共通
- **JST固定**（`new Date().toLocaleDateString('sv-SE')` でローカル日付取得）
- 表示時刻範囲: 8:00〜20:00（設定で変更可）
- 1日のスナップ: 15分単位
- 自分の予定: フルカラー / 他人の予定: 淡色 + タイトルのみ

### 月ビュー
- ProofCoordinator月ビューを参考に自作
- 日セルに予定タイトル（短縮）を表示
- 会議室予約は別色で表示

### 週ビュー
- 月〜日の7列タイムライン
- 時刻ヘッダー左固定

### 日ビュー（メイン）
- ProofCoordinator/Calendar.vue の横並びタイムラインと同方式
- 左列: 表示中のユーザー（自分＋オーバーレイで追加した人）
- 当日ハイライト・現在時刻ライン表示

---

## 通知仕様

### 朝一括通知（必須・さくらcron対応）
- 毎朝8:00に当日の予定がある全ユーザーへ通知レコード作成
- AppLayoutの既存Echo通知チャネルまたはDBポーリングで未読バッジ表示
- `schedule_notifications.type = morning_summary`

### 開始前リマインダー（さくら対応可能な場合）
- デフォルト30分前
- `schedule_notifications.type = pre_event_reminder`
- さくらのcronが5分間隔で `php artisan schedule:run` を実行できれば実現可能

### Artisanコマンド
```
App\Console\Commands\SendScheduleNotifications
  php artisan schedule:notifications  [--date=YYYY-MM-DD]
```

---

## 権限マトリクス

| 操作 | User | Coordinator | Leader | Admin | SuperAdmin |
|------|:----:|:-----------:|:------:|:-----:|:----------:|
| 予定表閲覧 | ○ | ○ | ○ | ○ | ○ |
| 自分の予定作成 | ○ | ○ | ○ | ○ | ○ |
| 他者の予定に追加 | — | ○* | ○* | ○ | ○ |
| 会議室予約 | ○ | ○ | ○ | ○ | ○ |
| 会議室マスタ管理 | — | — | — | ○ | ○ |
| 権限設定変更 | — | — | — | ○ | ○ |
| グループ会社設定 | — | — | — | — | ○ |

*`schedule_permission_settings.can_add_to_others_min_role` で変更可

---

## 実装フェーズ

### Phase 1: DB・基盤 ★必須
- migrations 9本作成・実行
- Models 作成（リレーション定義）
- routes/web.php に予定表ルート追加
- Event モデル拡張

### Phase 2: 予定表カレンダー本体 ★必須
- `ScheduleCalendar.vue`（月/週/日ビュー）
- `ScheduleController` + `ScheduleEventController`
- 自分の予定の表示・作成・編集・削除
- AppLayout カレンダーアイコン追加

### Phase 3: 参加者・他者予定追加 ★必須
- `schedule_attendees` CRUD
- `ScheduleAttendeeController`（権限チェック）
- `AttendeeSelector.vue`
- 参加者一覧表示（EventDetailModal）

### Phase 4: 会議室予約
- `MeetingRoomController`（Admin管理）
- `ScheduleRoomReservationController`
- `RoomReservationModal.vue`
- カレンダーへの会議室予約表示

### Phase 5: オーバーレイカレンダー
- `ScheduleOverlayController`
- `OverlayPanel.vue`
- 他社・他部署・個人オーバーレイ

### Phase 6: 通知
- `SendScheduleNotifications` Artisanコマンド
- `NotificationPanel.vue`（開閉可・デフォルト閉）
- AppLayout バッジ連動

### Phase 7: グループ会社設定 ✅ 完了（2026-06-14）
- `CompanyGroupController`（SuperAdmin）— routes を `superadmin.*` グループに統一
- `CompanyGroups/Index.vue` / `Create.vue` / `Edit.vue` 新規作成
- `SuperAdminNavigationTabs` に「グループ会社設定」タブ追加
- URL: `/superadmin/company-groups`、ルート名: `superadmin.company-groups.*`
- オーバーレイパネルへのグループ会社絞り込みは保留（Phase 8 以降）

### Phase 8: Admin設定
- `SchedulePermissionController`
- `SchedulePermissions/Edit.vue`

---

## 変更ファイル一覧（推定計）

### 新規作成
- migrations: 9ファイル
- Models: 8ファイル（CompanyGroup, CompanyGroupMember, MeetingRoom, RoomReservation, ScheduleAttendee, ScheduleCalendarOverlay, ScheduleNotification, SchedulePermissionSetting）
- Controllers: 9ファイル
- Vue Pages: 8ファイル
- Vue Components: 10ファイル
- Artisan Command: 1ファイル

### 既存変更
- `routes/web.php`
- `resources/js/layouts/AppLayout.vue`
- `app/Models/Event.php`

**合計: 約50ファイル（8フェーズ分割実装）**

---

## 検討事項・制約事項

| 項目 | 方針 |
|------|------|
| 日をまたいだイベント（出張・宿泊等） | **このページでは設定不可**（フロント+バックエンド両方でバリデーション）。将来的に別機能（出張管理等）で対応予定 |
| 会議室の予約可能時間外の予約 | Admin で設定した `available_from`〜`available_to` 外への予約はバックエンドで拒否・DayView でグレー表示 |

---

## JST / UTC 注意事項

- カレンダー上の日付取得は常に `new Date().toLocaleDateString('sv-SE')` を使用
- events テーブルへの保存: `starts_at` / `ends_at` はJST文字列そのまま保存（proof以外のルール）
- カーボン処理: `Carbon::parse()` ではなく `Carbon::createFromFormat('Y-m-d H:i:s', $value, 'Asia/Tokyo')` を使用
- `event_item_type_id` の会議・打合せ系イベントは JST 保存ルールに従う

---

## 会議室初期データ（さくら migrate 後に Seeder で投入）

```php
// company_id はさくら本番の「サン・ブレーン」の ID を使う
MeetingRoom::insert([
    ['company_id' => $sunbrainId, 'name' => '田端会議室',     'sort_order' => 1],
    ['company_id' => $sunbrainId, 'name' => '田端多目的ルーム', 'sort_order' => 2],
    ['company_id' => $sunbrainId, 'name' => '田端応接室',      'sort_order' => 3],
]);
```
