# SCHED_PLAN1.md — 予定表機能 詳細設計

最終更新: 2026-06-12  
ステータス: 設計確定・実装待ち

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

### Phase 7: グループ会社設定
- `CompanyGroupController`（SuperAdmin）
- `CompanyGroups/` Vue Pages
- オーバーレイパネルへのグループ会社絞り込み追加

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
