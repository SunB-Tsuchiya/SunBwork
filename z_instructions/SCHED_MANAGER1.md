# SCHED_MANAGER1.md — 予定表機能 進捗管理

最終更新: 2026-06-13  
担当: Claude + SunB-Tsuchiya

---

## 進捗サマリー

| フェーズ | 内容 | 状態 | 備考 |
|--------|------|:----:|------|
| Phase 1 | DB・基盤（migrations / Models / Routes） | ✅ 完了 | |
| Phase 2 | カレンダー本体（月/週/日ビュー・自分の予定） | ✅ 完了 | |
| Phase 3 | 参加者・他者予定追加 | ✅ 完了 | 3-5（招待通知）はPhase 6で追加 |
| Phase 4 | 会議室予約 | ✅ 完了 | |
| Phase 5 | オーバーレイカレンダー | ✅ 完了 | |
| Phase 6 | 通知 | ⬜ 未着手 | |
| Phase 7 | グループ会社設定 | ⬜ 未着手 | |
| Phase 8 | Admin権限設定 | ⬜ 未着手 | |

状態凡例: ⬜ 未着手 / 🔄 作業中 / ✅ 完了 / ⚠️ 問題あり

---

## Phase 1: DB・基盤

### タスク一覧

| # | タスク | 状態 |
|---|--------|:----:|
| 1-1 | migration: `company_groups` | ✅ |
| 1-2 | migration: `company_group_members` | ✅ |
| 1-3 | migration: `meeting_rooms` | ✅ |
| 1-4 | migration: `room_reservations` | ✅ |
| 1-5 | migration: `schedule_attendees` | ✅ |
| 1-6 | migration: `schedule_calendar_overlays` | ✅ |
| 1-7 | migration: `schedule_notifications` | ✅ |
| 1-8 | migration: `schedule_permission_settings` | ✅ |
| 1-9 | migration: events テーブルに `is_company_event` / `visibility` / `organizer_id` / `room_reservation_id` を追加 | ✅ |
| 1-10 | Model: CompanyGroup / CompanyGroupMember | ✅ |
| 1-11 | Model: MeetingRoom / RoomReservation | ✅ |
| 1-12 | Model: ScheduleAttendee / ScheduleCalendarOverlay | ✅ |
| 1-13 | Model: ScheduleNotification / SchedulePermissionSetting | ✅ |
| 1-14 | Event モデルに新カラムの fillable / casts 追加 | ✅ |
| 1-15 | routes/web.php に予定表ルート追加（全ルート骨格） | ✅ |
| 1-16 | docker migrate 実行（ローカル） | ✅ |

---

## Phase 2: カレンダー本体

### タスク一覧

| # | タスク | 状態 |
|---|--------|:----:|
| 2-1 | ScheduleController (index・events.range JSON) | ✅ |
| 2-2 | ScheduleEventController (store / update / destroy) | ✅ |
| 2-3 | Pages/Schedule/Index.vue 骨格 | ✅ |
| 2-4 | Components/Schedule/MonthView.vue | ✅ |
| 2-5 | Components/Schedule/WeekView.vue | ✅ |
| 2-6 | Components/Schedule/DayView.vue（横並びタイムライン） | ✅ |
| 2-7 | Components/Schedule/EventModal.vue（作成・編集） | ✅ |
| 2-8 | Components/Schedule/EventDetailModal.vue（詳細・自分/他人出し分け） | ✅ |
| 2-9 | AppLayout にカレンダーアイコン追加（SuperAdmin限定） | ✅ |
| 2-10 | npm run build・動作確認 | ✅ |

---

## Phase 3: 参加者・他者予定追加

| # | タスク | 状態 |
|---|--------|:----:|
| 3-1 | ScheduleAttendeeController (store / destroy・権限チェック) | ✅ |
| 3-2 | Components/Schedule/AttendeeSelector.vue | ✅ |
| 3-3 | EventModal に参加者追加UI統合 | ✅ |
| 3-4 | EventDetailModal に参加者一覧表示 | ✅ |
| 3-5 | 他者予定追加時の招待通知（DB insert） | ⚠️ スキップ（schedule_notifications enum に invitation 型なし。Phase 6 通知実装時に追加） |
| 3-6 | npm run build・動作確認 | 🔄 |

---

## Phase 4: 会議室予約

| # | タスク | 状態 |
|---|--------|:----:|
| 4-1 | Admin/MeetingRoomController (index / create / store / edit / update / destroy) | ✅ |
| 4-2 | Pages/Admin/MeetingRooms/ (Index / Create / Edit).vue | ✅ |
| 4-3 | MeetingRooms を Admin ナビゲーションタブに追加 | ✅ |
| 4-4 | ScheduleRoomReservationController (store / update / destroy) | ✅ |
| 4-5 | Components/Schedule/RoomReservationModal.vue | ✅ |
| 4-6 | カレンダーに会議室予約を表示（DayView 会議室カラムに予約ブロック・クリックで新規/編集） | ✅ |
| 4-7 | MeetingRoomsSeeder（田端3室）作成 | ✅ |
| 4-8 | npm run build・動作確認 | ✅ |

---

## Phase 5: オーバーレイカレンダー

| # | タスク | 状態 |
|---|--------|:----:|
| 5-1 | ScheduleOverlayController (index / store / destroy) | ✅ |
| 5-2 | Components/Schedule/OverlayPanel.vue | ✅ |
| 5-3 | 会社・部署・個人での検索・追加UI（3タブ: 個人/会社/部署） | ✅ |
| 5-4 | DayView/WeekView にオーバーレイユーザーの予定を統合表示（個人=カラム追加・会社/部署=events に混在） | ✅ |
| 5-5 | npm run build・動作確認 | ✅ |

---

## Phase 6: 通知

| # | タスク | 状態 |
|---|--------|:----:|
| 6-1 | App\Console\Commands\SendScheduleNotifications 作成 | ⬜ |
| 6-2 | Console/Kernel に毎朝8時スケジュール登録 | ⬜ |
| 6-3 | Components/Schedule/NotificationPanel.vue（開閉可・デフォルト閉） | ⬜ |
| 6-4 | ScheduleNotificationController (index / read) | ⬜ |
| 6-5 | AppLayout カレンダーアイコンに未読バッジ連動 | ⬜ |
| 6-6 | 開始前リマインダー（さくら対応確認後に実装） | ⬜ |
| 6-7 | npm run build・動作確認 | ⬜ |

---

## Phase 7: グループ会社設定

| # | タスク | 状態 |
|---|--------|:----:|
| 7-1 | SuperAdmin/CompanyGroupController (Resource) | ⬜ |
| 7-2 | Pages/SuperAdmin/CompanyGroups/ (Index / Create / Edit).vue | ⬜ |
| 7-3 | SuperAdmin ナビゲーションタブにグループ会社設定を追加 | ⬜ |
| 7-4 | OverlayPanel にグループ会社での絞り込みを追加 | ⬜ |
| 7-5 | npm run build・動作確認 | ⬜ |

---

## Phase 8: Admin権限設定

| # | タスク | 状態 |
|---|--------|:----:|
| 8-1 | Admin/SchedulePermissionController (edit / update) | ⬜ |
| 8-2 | Pages/Admin/SchedulePermissions/Edit.vue | ⬜ |
| 8-3 | Admin 設定メニューに追加 | ⬜ |
| 8-4 | ScheduleAttendeeController で設定値を参照するよう変更 | ⬜ |
| 8-5 | npm run build・動作確認 | ⬜ |

---

## さくらデプロイ チェックリスト（各 Phase 完了時）

```
□ VITE_APP_BASE_PATH=/members に切り替えて npm run build
□ git push origin main
□ ssh: git pull
□ ssh: php artisan migrate --force
□ ssh: php artisan config:clear && cache:clear && route:clear && view:clear
□ ブラウザで動作確認
□ VITE_APP_BASE_PATH= に戻す
```

---

## 作業ログ

| 日付 | 内容 |
|------|------|
| 2026-06-12 | 設計ヒアリング完了・PLAN / MANAGER / PROMPT ファイル作成 |
| 2026-06-12 | Phase 1 完了: migrations 9本・Models 8本・Event モデル拡張・routes 追加・docker migrate 実行 |
| 2026-06-12 | Phase 2 完了: ScheduleController / ScheduleEventController / MonthView / WeekView / DayView / EventModal / EventDetailModal / ScheduleCalendar / Schedule/Index.vue / AppLayout カレンダーアイコン追加・npm run build 成功 |
| 2026-06-12 | Phase 2 追加修正: UTC/JST ずれ修正（`new Date(isoStr).getHours()` でローカル時刻取得）・ドラッグ選択作成・ドラッグ移動・上下端リサイズ実装（WeekView / DayView）・FullCalendar 不使用のカスタム実装に確定・ツールバーを既存カレンダーと同スタイルに統一 |
| 2026-06-12 | Phase 3 完了: ScheduleController に users() 検索エンドポイント追加・schedule.users.search ルート追加・ScheduleEventController.store() に attendee_ids 対応・AttendeeSelector.vue 新規作成（フォームモード/ライブAPIモード切り替え）・EventModal に参加者UI統合・ScheduleCalendar で編集後のライブ変更反映（onModalClose）。3-5招待通知はPhase 6時に実装。 |
| 2026-06-12 | Phase 5 完了: OverlayPanel.vue 新規作成（個人/会社/部署の3タブピッカー・追加/削除チップUI）・ScheduleEventController.range() を会社/部署オーバーレイまで展開（company_id/department_id のユーザーを収集）・ScheduleCalendar.vue の overlays を reactive ref 化（initialOverlays prop + onOverlayAdd/Remove ハンドラ + watch で loadEvents 自動再取得）・Schedule/Index.vue を :initial-overlays 渡し形式に変更・npm run build 成功 |
| 2026-06-12 | Phase 4 完了: MeetingRooms Admin CRUD Pages（Index/Create/Edit.vue）・AdminNavigationTabs に会議室管理タブ追加・AppLayout アクティブタブ検出追加・RoomReservationModal.vue 新規作成・ScheduleEventController.range() に会議室予約を追加（reservations フィールド）・ScheduleCalendar に RoomReservationModal 統合・DayView 会議室カラムで予約ブロック表示＋クリック新規/編集・MeetingRoomsSeeder（田端3室）作成・npm run build 成功 |
