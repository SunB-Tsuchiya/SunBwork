# SCHED1_PROMPT.md — 予定表機能 新セッション開始用プロンプト

最終更新: 2026-06-21（Codex R3 修正・動作確認・テスト中バナー追加 完了後）

---

## このプロンプトの使い方

新しいセッションでこの機能の続きを作業するときは、このファイルの内容をそのままセッション冒頭に貼り付ける。

---

## セッション開始プロンプト（ここから貼り付け）

---

予定表機能（SCHED）の作業を続けてください。

まず以下のファイルを読んで現状を把握してください：
- `z_instructions/SCHED_PLAN1.md` — 設計仕様・DB定義・ルート・コンポーネント一覧
- `z_instructions/SCHED_MANAGER1.md` — フェーズ別タスク・進捗状況・直近バグ修正履歴

---

## 現在の状況（2026-06-21 時点）

### 全フェーズ完了・Codex R3 修正・動作確認済み

| フェーズ | 内容 | 状態 |
|--------|------|:----:|
| Phase 1〜8 | DB・基盤〜Admin権限設定 | ✅ 完了 |
| R4〜R7 | 週/月ビュー会議室表示〜サイドバー | ✅ 完了 |
| Codex R1〜R3 | 3回のコードレビュー指摘修正 | ✅ 完了 |
| UX Fix | 二重処理廃止・EventModal 統合フロー | ✅ 完了 |
| BugFix | 参加者保存・会議室認識・権限チェック・予約時間 | ✅ 完了 |
| 動作確認 | 承認バッジ・辞退除外・辞退者通知・pending継続 | ✅ 完了 |
| バナー | 「会議室予約はテスト機能・Outlook を使用」バナー追加 | ✅ 完了 |

### 残タスク（優先順）

1. **さくら本番デプロイ** — `z_instructions/DEPLOY_SAKURA.md` の手順に従う（migration `2026_06_21_000001_add_event_owned_to_room_reservations` を含む）
2. **リリース解除** — 現在 SuperAdmin のみに制限中を全ユーザーに開放（バナー表示中のため当面不要かもしれない）
   - `routes/web.php`: 予定表ルートグループの `'superadmin'` ミドルウェアを削除
   - `resources/js/layouts/AppLayout.vue`: カレンダーアイコンの `user_role === 'superadmin'` 条件を削除

---

## 機能概要

会社全体の会議・面談・打合せ・イベントを把握できる Outlook ライクな共有カレンダー。

### 設計の核心

- **データ:** 既存 `events` テーブルを拡張（`is_company_event=true` のものが表示）
- **カレンダー実装:** 完全カスタム（FullCalendar 不使用）。月/週/日 3ビュー自作、ドラッグ選択・移動・リサイズ対応
- **会議室予約フロー:** EventModal でイベントと会議室を1回の操作で同時作成（二重処理なし）
  - タイムテーブルで部屋を「選択」→ EventModal の「作成」ボタンで event + room_reservation を一括保存
  - 補償パターン: 会議室予約失敗時に作成済み event を削除してロールバック

### 権限構造

- 全ユーザー: 自分のイベント作成・会議室予約・参加者追加（自分のイベントのみ）
- Coordinator以上（設定変更可）: 他者のイベントに参加者を追加
- Admin以上: 会議室マスタ管理・権限設定（`schedule_permission_settings`）
- SuperAdmin: グループ会社設定

### 通知

毎朝8時に当日の予定を通知（さくら cron + `php artisan schedule:run`）

---

## 実装済みファイル一覧

### PHP

| ファイル | 内容 |
|---------|------|
| `app/Models/CompanyGroup.php` | モデル |
| `app/Models/CompanyGroupMember.php` | モデル |
| `app/Models/MeetingRoom.php` | モデル（scopeActive 付き） |
| `app/Models/RoomReservation.php` | モデル |
| `app/Models/ScheduleAttendee.php` | モデル |
| `app/Models/ScheduleCalendarOverlay.php` | モデル |
| `app/Models/ScheduleNotification.php` | モデル（scopeUnread 付き） |
| `app/Models/SchedulePermissionSetting.php` | モデル |
| `app/Http/Controllers/Schedule/ScheduleController.php` | index（ページ）+ rooms + users + clients（JSON）|
| `app/Http/Controllers/Schedule/ScheduleEventController.php` | range / show / store / update / destroy |
| `app/Http/Controllers/Schedule/ScheduleAttendeeController.php` | store / destroy（自分のイベントは無条件許可） |
| `app/Http/Controllers/Schedule/ScheduleRoomReservationController.php` | store / update / destroy（link_event_id 対応） |
| `app/Http/Controllers/Schedule/ScheduleOverlayController.php` | index / store / destroy |
| `app/Http/Controllers/Schedule/ScheduleNotificationController.php` | index / read |
| `app/Http/Controllers/Admin/MeetingRoomController.php` | CRUD |
| `app/Http/Controllers/Admin/SchedulePermissionController.php` | edit / update |
| `app/Console/Commands/SendScheduleNotifications.php` | 毎朝8時の通知コマンド |

### Vue

| ファイル | 内容 |
|---------|------|
| `resources/js/Pages/Schedule/Index.vue` | 予定表ページ |
| `resources/js/Pages/Admin/MeetingRooms/Index.vue` | 会議室一覧（Admin） |
| `resources/js/Pages/Admin/MeetingRooms/Create.vue` | 会議室登録 |
| `resources/js/Pages/Admin/MeetingRooms/Edit.vue` | 会議室編集 |
| `resources/js/Pages/Admin/SchedulePermissions/Edit.vue` | 予定表権限設定（Admin） |
| `resources/js/Pages/SuperAdmin/CompanyGroups/Index.vue` | グループ会社一覧 |
| `resources/js/Pages/SuperAdmin/CompanyGroups/Create.vue` | グループ作成 |
| `resources/js/Pages/SuperAdmin/CompanyGroups/Edit.vue` | グループ編集 |
| `resources/js/Components/Schedule/ScheduleCalendar.vue` | カレンダーラッパー（2ペインレイアウト・今日の予約サイドバー） |
| `resources/js/Components/Schedule/MonthView.vue` | 月ビュー |
| `resources/js/Components/Schedule/WeekView.vue` | 週ビュー（ドラッグ対応・slug色マップ） |
| `resources/js/Components/Schedule/DayView.vue` | 日ビュー（自分/オーバーレイ/会議室 横並びカラム・slug色マップ） |
| `resources/js/Components/Schedule/EventModal.vue` | 予定作成・編集（タイムテーブル会議室選択内蔵・一括保存） |
| `resources/js/Components/Schedule/EventDetailModal.vue` | 予定詳細・「会議室を予約」ボタン付き |
| `resources/js/Components/Schedule/AttendeeSelector.vue` | 参加者選択（フォームモード/ライブAPIモード） |
| `resources/js/Components/Schedule/RoomReservationModal.vue` | 会議室予約モーダル（link_event_id・defaultTitle等 pre-fill 対応） |
| `resources/js/Components/Schedule/OverlayPanel.vue` | オーバーレイ管理パネル |
| `resources/js/Components/Schedule/MiniCalendar.vue` | 左サイドバーミニカレンダー |
| `resources/js/Components/Schedule/NotificationPanel.vue` | 通知パネル（ベルアイコン・既読管理） |

---

## 重要な実装上の注意点（引き継ぎ事項）

### EventModal → 会議室予約の統合フロー（重要）

```
EventModal でイベント作成時:
  1. タイムテーブルで会議室をドラッグ選択 → 「選択」ボタンで selectedRoomId に記録
  2. 「作成」ボタン押下 → EventController.store() でイベント作成
  3. selectedRoomId があれば → RoomReservationController.store() に link_event_id 付きで POST
     - attendee_ids は送らない（EventController が既に設定済み）
  4. 失敗時は補償: 作成済みイベントを DELETE してロールバック
```

- **link_event_id ブランチ**: `attendee_ids` が送られた場合のみ参加者差分更新。送られていなければスキップ（既存参加者を保持）
- 保存済みイベントに後から会議室を紐づける: EventDetailModal「会議室を予約」→ RoomReservationModal が pre-fill 済みで開く

### 会議室予約とイベントの二重性

- 会議室予約 → `room_reservations` + `events` 両方に同時作成
- `events.room_reservation_id` で紐付け
- 予約に紐づくイベントはドラッグ移動不可
- クリックすると RoomReservationModal が開く（EventDetailModal ではない）
- ScheduleEventController の update/destroy は `room_reservation_id` があると 422（M-2ガード）

### 参加者権限

- **自分のイベント**: 全ユーザーが無条件で参加者追加・削除可能
- **他人のイベント**: `can_add_to_others_min_role`（デフォルト coordinator）以上のロールのみ
- ScheduleAttendeeController と ScheduleEventController.store の両方で判定

### 会議室予約可能時間チェック

- **開始時刻のみ** チェック。終了時刻が超えていても開始が範囲内なら OK
- 例: available_to=17:00 → 16:00〜18:00 は OK、17:00〜18:00 は NG
- サーバー: `ScheduleRoomReservationController.checkAvailableHours`
- フロント: `EventModal.roomAvailability` computed の `withinHours` チェック

### イベント色（slug ベース）

```js
const EVENT_TYPE_COLORS = {
    conference:       blue,
    meeting_internal: green,
    meeting_client:   cyan,
    client_visit:     purple,
    customer_visit:   orange,
    outing:           amber,
    other:            gray,
};
// 種別なし → blue(デフォルト)、他人の予定 → lightgray
```

### 種別と会議室タイムテーブルの表示ロジック

- `NO_ROOM_SLUGS = ['outing', 'customer_visit']` → これら以外の種別でタイムテーブル表示（EventModal）
- `showDestination`: `['customer_visit', 'meeting_client', 'client_visit']` → 取引先フィールド表示
- RoomReservationModal の `filteredEventItemTypes`: 外出/顧客訪問/打合せ顧客を除外

### AttendeeSelector の2モード

- `event-id = null` → フォームモード（`@change` で親に通知）
- `event-id != null` → ライブAPIモード（直接エンドポイントを叩く: `schedule.attendees.store/destroy`）

### JST 注意

- 日付取得: `new Date().toLocaleDateString('sv-SE')` を使う
- DB保存: JST文字列そのまま（`YYYY-MM-DD HH:mm:ss`）
- API から返る `starts_at` は UTC ISO 文字列 → `new Date(isoStr).toLocaleTimeString('ja-JP', ...)` でローカル変換
- `isoToJstMinutes(isoStr)` ヘルパー: `new Date(new Date(isoStr).toLocaleString('ja-JP', { timeZone: 'Asia/Tokyo' }))` で JST 変換

### 開発中フィルター（リリース前に解除が必要）

- `routes/web.php`: 予定表ルートグループに `'superadmin'` ミドルウェア
- `resources/js/layouts/AppLayout.vue`: カレンダーアイコンの `user_role === 'superadmin'` 条件

### さくらデプロイ時の追加作業

```bash
php artisan migrate --force   # 2026_06_21_000001_add_event_owned_to_room_reservations を含む
php artisan db:seed --class=MeetingRoomsSeeder --force
php artisan config:clear && php artisan cache:clear && php artisan route:clear && php artisan view:clear
```
