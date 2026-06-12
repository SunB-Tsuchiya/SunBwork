# SCHED1_PROMPT.md — 予定表機能 新セッション開始用プロンプト

最終更新: 2026-06-13（Phase 4・5 実装後・未確認）

---

## このプロンプトの使い方

新しいセッションでこの機能の続きを実装するときは、このファイルの内容をそのままセッション冒頭に貼り付ける。

---

## セッション開始プロンプト（ここから貼り付け）

---

予定表機能（SCHED）の実装を続けてください。

まず以下のファイルを読んで現状を把握してください：
- `z_instructions/SCHED_PLAN1.md` — 設計仕様・DB定義・ルート・コンポーネント一覧
- `z_instructions/SCHED_MANAGER1.md` — フェーズ別タスク・進捗状況

---

## 機能概要（サマリー）

会社全体の会議・面談・打合せ・イベントを把握できる「予定表」機能。  
既存の `events` テーブルに `is_company_event` フラグを追加して個人カレンダーと連動させる。

### 設計の核心ポイント

**データ:** 既存 `events` テーブルを拡張（`is_company_event=true` のものが予定表に表示される）

**カレンダー実装:** **完全カスタム実装（FullCalendar 不使用）**  
→ `Components/Schedule/ScheduleCalendar.vue` がラッパー  
→ `MonthView.vue` / `WeekView.vue` / `DayView.vue` の3ビューを自作  
→ WeekView・DayView はドラッグ選択作成・ドラッグ移動・上下端リサイズ対応済み

**権限:**
- 全ユーザー: 自分の予定を作成
- Coordinator以上: 他者の予定に追加可（`schedule_permission_settings` でAdmin変更可）
- Admin以上: 会議室マスタ管理・権限設定
- SuperAdmin: グループ会社設定

**通知:** 毎朝8時に当日の予定を通知（さくらcron + `php artisan schedule:notifications`）  
AppLayoutの通知エリアにカレンダーアイコン追加済み（現在 SuperAdmin のみ表示）。

**他者閲覧:** デフォルト自分のみ。`schedule_calendar_overlays` で会社/部署/個人単位のオーバーレイ追加可。

### 新規テーブル（migrate 済み）
1. `company_groups` — グループ会社管理
2. `company_group_members` — グループ所属会社
3. `meeting_rooms` — 会議室マスタ
4. `room_reservations` — 会議室予約（events と任意紐付け）
5. `schedule_attendees` — 予定参加者（event_id + user_id）
6. `schedule_calendar_overlays` — ユーザーのオーバーレイ設定
7. `schedule_notifications` — 通知管理
8. `schedule_permission_settings` — 会社別権限設定

### eventsテーブルに追加済みカラム
- `is_company_event` boolean default false
- `visibility` enum(private, company, group, public) default private
- `organizer_id` FK users nullable
- `room_reservation_id` FK room_reservations nullable

---

## ⚠️ 開発中フィルター（リリース前に必ず外すこと）

**現在 SuperAdmin のみに表示制限中。**

- ルート: `routes/web.php` 末尾の予定表ルートグループに `'superadmin'` ミドルウェア適用済み
- AppLayout: `authUser?.user_role === 'superadmin'` 条件でカレンダーアイコン表示

### リリース時の解除手順
1. ルートのミドルウェアを `'superadmin'` → `'verified'` に変更
2. AppLayout の `v-if="authUser?.user_role === 'superadmin'"` を削除
3. `npm run build` → さくらデプロイ

---

## 実装済みファイル一覧

### PHP
| ファイル | 内容 |
|---------|------|
| `app/Models/CompanyGroup.php` | モデル |
| `app/Models/CompanyGroupMember.php` | モデル |
| `app/Models/MeetingRoom.php` | モデル（`scopeActive` 付き） |
| `app/Models/RoomReservation.php` | モデル |
| `app/Models/ScheduleAttendee.php` | モデル |
| `app/Models/ScheduleCalendarOverlay.php` | モデル |
| `app/Models/ScheduleNotification.php` | モデル（`scopeUnread` 付き） |
| `app/Models/SchedulePermissionSetting.php` | モデル |
| `app/Http/Controllers/Schedule/ScheduleController.php` | index（ページ）+ rooms（JSON）+ users（JSON） |
| `app/Http/Controllers/Schedule/ScheduleEventController.php` | range / show / store / update / destroy（会議室予約も含むレスポンス） |
| `app/Http/Controllers/Schedule/ScheduleAttendeeController.php` | store / destroy |
| `app/Http/Controllers/Schedule/ScheduleRoomReservationController.php` | store / update / destroy |
| `app/Http/Controllers/Schedule/ScheduleOverlayController.php` | index / store / destroy |
| `app/Http/Controllers/Schedule/ScheduleNotificationController.php` | index / read（ルート定義済み・実装骨格のみ） |
| `app/Http/Controllers/Admin/MeetingRoomController.php` | CRUD |
| `app/Http/Controllers/Admin/SchedulePermissionController.php` | edit / update（ルート定義済み・実装骨格のみ） |
| `app/Http/Controllers/SuperAdmin/CompanyGroupController.php` | Resource CRUD（ルート定義済み・実装骨格のみ） |
| `database/seeders/MeetingRoomsSeeder.php` | 田端3室の初期データ（ローカルで実行済み） |

### Vue
| ファイル | 内容 |
|---------|------|
| `resources/js/Pages/Schedule/Index.vue` | 予定表ページ |
| `resources/js/Pages/Admin/MeetingRooms/Index.vue` | 会議室一覧（Admin） |
| `resources/js/Pages/Admin/MeetingRooms/Create.vue` | 会議室登録 |
| `resources/js/Pages/Admin/MeetingRooms/Edit.vue` | 会議室編集 |
| `resources/js/Components/Schedule/ScheduleCalendar.vue` | カレンダーラッパー（月/週/日切り替え・API呼び出し・overlay/room/notification状態管理） |
| `resources/js/Components/Schedule/MonthView.vue` | 月ビュー（クリック→日ビューへ） |
| `resources/js/Components/Schedule/WeekView.vue` | 週ビュー（ドラッグ選択・移動・リサイズ対応） |
| `resources/js/Components/Schedule/DayView.vue` | 日ビュー（自分/オーバーレイユーザー/会議室 の横並びカラム） |
| `resources/js/Components/Schedule/EventModal.vue` | 予定作成・編集モーダル（参加者選択付き） |
| `resources/js/Components/Schedule/EventDetailModal.vue` | 予定詳細モーダル |
| `resources/js/Components/Schedule/AttendeeSelector.vue` | 参加者選択コンポーネント |
| `resources/js/Components/Schedule/RoomReservationModal.vue` | 会議室予約 作成・編集・削除モーダル |
| `resources/js/Components/Schedule/OverlayPanel.vue` | オーバーレイ管理パネル（個人/会社/部署の3タブ） |
| `resources/js/Components/Tabs/AdminNavigationTabs.vue` | 会議室管理タブ追加済み |

---

## 現在の進捗

| フェーズ | 状態 |
|--------|:----:|
| Phase 1: DB・基盤 | ✅ 完了 |
| Phase 2: カレンダー本体（月/週/日ビュー・自分の予定） | ✅ 完了 |
| Phase 3: 参加者・他者予定追加 | ✅ 完了 |
| Phase 4: 会議室予約 | ✅ 実装完了・**ユーザー未確認** |
| Phase 5: オーバーレイカレンダー | ✅ 実装完了・**ユーザー未確認** |
| Phase 6: 通知 | ⬜ 未着手 |
| Phase 7: グループ会社設定 | ⬜ 未着手 |
| Phase 8: Admin権限設定 | ⬜ 未着手 |

---

## ⚠️ ユーザーが確認すること（Phase 4・5 の動作確認）

Phase 4・5 は実装・ビルド完了だがユーザーによる動作確認がまだ。  
**以下をブラウザで確認してから Phase 6 に進むこと。**

### Phase 4（会議室予約）確認項目
1. **Day ビューに会議室カラムが3本表示されること**
   - 田端会議室 / 田端多目的ルーム / 田端応接室
   - ← ローカルでは `MeetingRoomsSeeder` 実行済み。さくら本番では未実行（後述）
2. **ツールバーの「🏢 会議室予約」ボタンをクリック → 予約モーダルが開くこと**
3. **会議室カラムの空白をクリック → 予約モーダルが開くこと**
4. **予約モーダルで保存 → Day ビューのカラムに予約ブロックが表示されること**
5. **予約ブロックをクリック → 編集/削除モーダルが開くこと**
6. **Admin > 会議室管理タブ → 会議室一覧・登録・編集・削除が動作すること**

### Phase 5（オーバーレイカレンダー）確認項目
1. **カレンダー下部の「他のメンバー:（追加なし）＋ 追加」が表示されること**
2. **「＋ 追加」をクリック → ピッカーモーダルが開くこと**
3. **個人タブ：会社選択 → ユーザー一覧 → クリックして追加 → チップが表示されること**
4. **会社・部署タブ：クリックして追加 → チップが表示されること**
5. **追加後、カレンダーが自動再取得され、Day ビューに追加した個人ユーザーのカラムが表示されること**
6. **チップの × をクリック → オーバーレイ削除・カラム消滅すること**

### さくら本番デプロイ時の追加作業
```bash
# さくら SSH で実行（Phase 4・5 用）
php artisan migrate --force
php artisan db:seed --class=MeetingRoomsSeeder --force
php artisan config:clear && php artisan cache:clear && php artisan route:clear && php artisan view:clear
```

---

## 次の作業: Phase 6（通知）

MANAGER ファイルのタスクを確認して以下の順で実装する。

### 6-1. `SendScheduleNotifications` Artisanコマンド作成
ファイル: `app/Console/Commands/SendScheduleNotifications.php`
- `php artisan schedule:notifications [--date=YYYY-MM-DD]`
- 当日の `is_company_event=true` かつ `is_company_event` のイベントを持つ全ユーザーへ
  `schedule_notifications` テーブルにレコード insert（`type=morning_summary`）
- すでに `notified_at` が入っているレコードはスキップ

### 6-2. コンソールスケジュール登録
`app/Console/Kernel.php`（または `routes/console.php`）に毎朝8:00 JST で登録:
```php
$schedule->command('schedule:notifications')->dailyAt('08:00')->timezone('Asia/Tokyo');
```

### 6-3. `ScheduleNotificationController` 実装
ファイル: `app/Http/Controllers/Schedule/ScheduleNotificationController.php`
- `index()`: 自分への未読通知一覧 JSON
- `read(ScheduleNotification $notification)`: `read_at` を更新

### 6-4. `NotificationPanel.vue` 作成
ファイル: `resources/js/Components/Schedule/NotificationPanel.vue`
- AppLayout のカレンダーアイコンをクリックすると開く形式（または予定表ページ内）
- 未読通知一覧を表示・クリックで既読
- デフォルト閉

### 6-5. AppLayout カレンダーアイコンに未読バッジ連動
- 未読件数をポーリング or ページロード時に取得してバッジ表示
- ScheduleController.index() のレスポンスに `unread_count` を追加するのが最も簡単

### 6-6. Phase 3 の 3-5（招待通知）
`schedule_notifications.type` enum を確認して `invitation` を追加するか、  
別の通知方法（既存の Notification チャネル）を使う。

---

## JST 注意
- カレンダー日付: `new Date().toLocaleDateString('sv-SE')` を使う
- DB保存: JST文字列そのまま（`YYYY-MM-DD HH:mm:ss`形式で送信）
- イベント位置計算: `new Date(isoStr).getHours()` / `.getMinutes()` はブラウザのローカル時刻（JST）を返すためそのまま使用可
- ドラッグ位置 → 分の変換はピクセル計算なのでタイムゾーン非依存

---

## 重要: 実装前に確認すべきファイル

- `z_instructions/CONSOLIDATED_01_layout_and_ui.md` — UIルール
- `CLAUDE.md` — プロジェクト全体ルール（JST/UTCルール必読）
- `app/Http/Controllers/Schedule/ScheduleEventController.php` — 既存イベントロジック
- `resources/js/Components/Schedule/ScheduleCalendar.vue` — カレンダー状態管理の中心
- `resources/js/Components/Schedule/DayView.vue` — 会議室カラム実装参考
