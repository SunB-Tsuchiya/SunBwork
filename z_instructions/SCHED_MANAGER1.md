# SCHED_MANAGER1.md — 予定表機能 進捗管理

最終更新: 2026-06-21  
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
| Round 1-3 改修 | ブラウザテスト後フィードバック対応 | ✅ 完了 | SCHED_PLAN1.md 修正履歴参照 |
| Phase 6 | 通知 | ✅ 完了 | |
| R4 | 週・月ビューへの会議室予約表示 | ✅ 完了 | |
| R5 | 会議設定（MeetingDefinition）連動 | ✅ 完了 | |
| R6 | イベント色の統一（slug-based color map） | ✅ 完了 | |
| R7 | 会議室予約一覧（左サイドバー） | ✅ 完了 | オーナーOR参加者のみ表示 |
| Phase 7 | グループ会社設定 | ✅ 完了 | |
| Phase 8 | Admin権限設定 | ✅ 完了 | |
| Codex R1 | 第1回コードレビュー修正 | ✅ 完了 | whereHas→whereIn・start<end ガード |
| Codex R2 | 第2回コードレビュー修正 | ✅ 完了 | 補償・権限・diff更新・errors配列 |
| UX Fix | 二重処理廃止・EventModal 統合フロー | ✅ 完了 | |
| BugFix | 参加者保存・会議室認識・予約可能時間 | ✅ 完了 | 2026-06-18 |
| 動作確認 | 4項目ブラウザ動作確認（承認バッジ・辞退除外・通知・pending継続） | ✅ 完了 | 2026-06-21 |
| Codex R3 | 第3回コードレビュー指摘修正（5件） | ✅ 完了 | 2026-06-21 |
| バナー | 会議室予約テスト中バナーを予定表ページ上部に表示 | ✅ 完了 | 2026-06-21 |
| **デプロイ** | **さくら本番デプロイ** | 🔄 作業中 | DEPLOY_SAKURA.md 手順 |
| **リリース** | **SuperAdmin 限定解除・全ユーザー開放** | ⬜ 未着手 | routes/web.php + AppLayout.vue |

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
| 6-1 | App\Console\Commands\SendScheduleNotifications 作成 | ✅ |
| 6-2 | routes/console.php に毎朝8時スケジュール登録 | ✅ |
| 6-3 | Components/Schedule/NotificationPanel.vue（開閉可・デフォルト閉） | ✅ |
| 6-4 | ScheduleNotificationController (index / read) | ✅ |
| 6-5 | AppLayout カレンダーアイコンに未読バッジ連動（全ユーザー表示に拡張） | ✅ |
| 6-6 | 開始前リマインダー（5分間隔 cron が必要なため未実装、さくら確認後） | ⚠️ 保留 |
| 6-7 | npm run build・動作確認 | ✅ |

---

## Phase 7: グループ会社設定

| # | タスク | 状態 |
|---|--------|:----:|
| 7-1 | SuperAdmin/CompanyGroupController (Resource) | ✅ |
| 7-2 | Pages/SuperAdmin/CompanyGroups/ (Index / Create / Edit).vue | ✅ |
| 7-3 | SuperAdmin ナビゲーションタブにグループ会社設定を追加 | ✅ |
| 7-4 | OverlayPanel にグループ会社での絞り込みを追加 | ⬜ 保留（Phase8以降で検討） |
| 7-5 | npm run build・動作確認 | ✅ |

---

## Phase 8: Admin権限設定

| # | タスク | 状態 |
|---|--------|:----:|
| 8-1 | Admin/SchedulePermissionController (edit / update) | ✅ |
| 8-2 | Pages/Admin/SchedulePermissions/Edit.vue | ✅ |
| 8-3 | Admin 設定メニューに追加（AdminNavigationTabs.vue テンプレートにも Link 追加） | ✅ |
| 8-4 | ScheduleEventController に filterAttendeesByPermission() 追加・store() で参照 | ✅ | ※ Codex R3 でデッドコードと判定し削除済み |
| 8-5 | npm run build・動作確認 | ✅ |

---

---

## R4: 週・月ビューへの会議室予約表示

| # | タスク | 状態 |
|---|--------|:----:|
| R4-1 | `ScheduleCalendar.vue`: `reservations` / `rooms` / `openRoomEdit` を WeekView・MonthView に props 渡し追加 | ✅ |
| R4-2 | `WeekView.vue`: 下部に会議室セクション追加（行=室・列=曜日・チップ表示・トグル付き） | ✅ |
| R4-3 | `MonthView.vue`: 日セルに会議室予約チップ（🏢 HH:MM [部屋名] タイトル）追加 | ✅ |
| R4-4 | npm run build・動作確認 | ✅ |

---

## R5: 会議設定（MeetingDefinition）連動

| # | タスク | 状態 |
|---|--------|:----:|
| R5-1 | `ScheduleController.php`: `meetingDefinitions`（自社分 + `members:id,name`）を index props に追加 | ⬜ |
| R5-2 | `ScheduleEventController.php`: store/update で `meeting_definition_id` を受け付けて保存 | ⬜ |
| R5-3 | `EventModal.vue`: 種類選択をラジオボタン形式に統一（CreateInternalEvent.vue と同じ） | ⬜ |
| R5-4 | `EventModal.vue`: conference 選択時に会議定義ピッカー表示・自動入力（title/description/time/date/attendees） | ⬜ |
| R5-5 | `RoomReservationModal.vue`: 「会議種類（任意）」ドロップダウン追加・title/attendees 自動入力 | ⬜ |
| R5-6 | npm run build・動作確認 | ⬜ |

---

## R6: イベント色の統一

| # | タスク | 状態 |
|---|--------|:----:|
| R6-1 | `ScheduleEventController.php`: `range()` で `eventItemType:id,name,slug` を with に追加（ownEvents + overlayEvents 両方） | ✅ |
| R6-2 | `DayView.vue`: `EVENT_TYPE_COLORS` 定数追加・`evColor` 関数を slug ベースに変更 | ✅ |
| R6-3 | `WeekView.vue`: 同上 | ✅ |
| R6-4 | npm run build・動作確認 | ✅ |

色マップ: 会議=blue / 打合せ社内=green / 打合せ顧客=cyan / 来客対応=purple / 顧客訪問=orange / 外出=amber / その他=gray / 種別なし=blue(デフォルト) / 他人の予定=lightgray

---

## R7: 会議室予約一覧（左サイドバー）

| # | タスク | 状態 |
|---|--------|:----:|
| R7-1 | `ScheduleCalendar.vue`: 左サイドバーMiniCalendar下部に今日の予約一覧セクション追加 | ✅ |
| R7-2 | クリック時の動作: `viewMode='day'` 切り替え + `openRoomEdit(reservation)` | ✅ |
| R7-3 | 表示フィルター: オーナー（user_id=自分）またはattendeeに自分が含まれる予約のみ | ✅ |
| R7-4 | 別途フェッチ（onMounted）: メインビューの表示期間に依存しない | ✅ |
| R7-5 | npm run build・動作確認 | ✅ |

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
| 2026-06-14 | Phase 7 完了: routes修正（super-admin→superadmin統一）・CompanyGroupController リダイレクト名修正・CompanyGroups/Index/Create/Edit.vue 新規作成・SuperAdminNavigationTabsにグループ会社設定タブ追加・npm run build 成功。7-4（OverlayPanel絞り込み）は保留。また同セッションにてCodexレビューによりScheduleRoomReservationControllerの重複チェックロジック修正・DB::transaction+lockForUpdate追加・authorizeRoom/authorizeEventLink認可追加も実施。 |
| 2026-06-13 | Phase 6 完了: SendScheduleNotificationsコマンド（毎朝8時スケジュール）・HandleInertiaRequests に unreadScheduleNotifications 共有・AppLayoutカレンダーアイコンを全ユーザー表示＋未読バッジ（緑）・NotificationPanel.vue（ベルボタン→ドロップダウン・クリックで既読・すべて既読ボタン）・ScheduleCalendarツールバーに統合・npm run build 成功 |
| 2026-06-16 | EventModal 拡張: 取引先オートコンプリート（schedule.clients.index）・会議室タイムテーブル（ドラッグで時間選択・部屋別ハイライト）・「会議室を予約」ボタン内蔵 |
| 2026-06-16 | RoomReservationModal 拡張: EventModalと統一スタイル・種別セレクター追加（外出/顧客訪問/打合せ顧客除外）・来客対応時のみ取引先フィールド表示・会議選択時のみ会議種類ピッカー表示 |
| 2026-06-16 | ScheduleEventController: destination フィールド保存対応・range() で slug 含む eventItemType を返すよう変更 |
| 2026-06-16 | ScheduleRoomReservationController: event_item_type_id / destination フィールド保存対応 |
| 2026-06-16 | ScheduleController: clients() メソッド追加（ProjectTeamMember join・admin権限不要） |
| 2026-06-16 | R6完了: DayView/WeekView の evColor を slug ベース COLOR MAP に変更 |
| 2026-06-16 | R7完了: ScheduleCalendar 左サイドバーに今日の会議室予約一覧（オーナーor参加者のみ・onMounted 別フェッチ） |
| 2026-06-16 | Phase 8完了: SchedulePermissionController(既存) + Edit.vue 新規作成 + AdminNavigationTabs 追加 + filterAttendeesByPermission() 実装 |
| 2026-06-18 | Codex 第1回コードレビュー実施・指摘修正: ScheduleController.users() の whereHas→whereIn 修正（500エラー修正）・ScheduleEventController に明示的 start<end チェック追加 |
| 2026-06-18 | ブラウザテスト フィードバック対応①: EventModal→RoomReservationModal へのタイトル/参加者連動・EventModal保存後の会議室予約モーダル自動クローズ |
| 2026-06-18 | ブラウザテスト フィードバック対応②: 二重処理廃止（EventModal 統合フロー）— RoomReservationModal を EventModal から取り除き、タイムテーブルで「選択」→「作成」1ボタンで event+reservation 一括作成・補償パターン（失敗時 event 削除）実装 |
| 2026-06-18 | Codex 第2回コードレビュー実施・指摘修正: (Fix①)補償処理・(Fix②)ScheduleRoomReservationController 参加者ロール権限チェック追加・(Fix③)link_event_id 参加者差分更新（全削除→差分）・(Fix④)RoomReservationModal errors.meeting_room_id 配列形式修正 |
| 2026-06-18 | バグ修正: 参加者が保存後に消える問題 — link_event_id ブランチで attendee_ids 未送信時の diff スキップ + EventModal 側から attendee_ids 削除（EventController 設定済みのため不要） |
| 2026-06-18 | バグ修正: ScheduleAttendeeController.checkPermission — 自分のイベントは無条件で参加者追加・削除を許可・null-safe 演算子不足修正（$setting?->） |
| 2026-06-18 | バグ修正: ScheduleEventController.store — 自分のイベント作成時の filterAttendeesByPermission を削除（自分の予定なら全ロールで参加者追加可） |
| 2026-06-18 | バグ修正: 会議室予約可能時間チェック — 終了時刻チェック廃止・開始時刻が available_from〜available_to の範囲内であれば終了が超えていても許可（サーバー checkAvailableHours + フロント roomAvailability computed 両方修正）|
| 2026-06-18 | RoomReservationModal タイトル変更: linkEventId がある場合「予定に会議室を紐づける」と表示 |
| 2026-06-21 | NSystem リモートコミット（14件）を git pull で取り込み。stash@{0} は削除済み。 |
| 2026-06-21 | ブラウザ動作確認（4項目）全て OK: 承認後バッジ・辞退後カレンダー除外・辞退者名つき通知・pending 継続表示 |
| 2026-06-21 | Codex 第3回コードレビュー実施・5件修正: ①destroy()が既存リンクイベントを削除するバグ（event_owned カラム追加・migration）②update()参加者リビルドが accepted をリセットするバグ（差分更新に変更）③conflicts()が参加者イベントを見落とすバグ（attendee JOIN 追加）④respond()重複通知バグ（firstOrCreate に変更）⑤filterAttendeesByPermission()デッドコード削除 |
| 2026-06-21 | Pages/Schedule/Index.vue 上部に「会議室予約はテスト機能・Outlook を使用してください」バナーを追加 |
