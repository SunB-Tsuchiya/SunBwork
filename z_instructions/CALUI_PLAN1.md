# CALUI_PLAN1.md — Calendar UI リフレッシュ 詳細仕様

## 概要

`/calendar`（ユーザー個人カレンダー）の UI を FullCalendar から Schedule ページ（`/schedule`）と同じカスタムカレンダーに置換する。
共有コンポーネントを設計して、Schedule / Calendar どちらかを修正した際に両方に波及しないよう設計する。

---

## ゴール

| 項目 | 現状 | 完了後 |
|---|---|---|
| Calendar UIライブラリ | FullCalendar | カスタム（Schedule と同じ MonthView/WeekView/DayView） |
| ミニカレンダー | なし | あり（Schedule と同じ MiniCalendar） |
| 案件打合せ・外出 作成 | 別ページ遷移 | EventModal（インライン、参加者招待可） |
| 社内予定 作成 | 別ページ遷移 | EventModal（インライン） |
| Schedule との同期 | 壊れている（is_company_event=NULL） | 正常（is_company_event=true で統一） |
| 既存データ | 影響なし | 影響なし（過去データは触らない） |

---

## アーキテクチャ方針

### レイヤー構造

```
Schedule/CalendarShell.vue   ← 新規: MiniCalendar + ナビ + メインスロット
        │
        ├── ScheduleCalendar.vue  (既存 — Schedule 専用ロジック追加のみ)
        └── UserCalendar.vue      ← 新規: Calendar 専用ロジック
```

```
Schedule/useCalendarCore.js  ← 新規: 共通コンポーザブル（ナビ・日付計算）
```

### 共有する既存コンポーネント（変更なし）
- `Components/Schedule/MiniCalendar.vue`
- `Components/Schedule/MonthView.vue`
- `Components/Schedule/WeekView.vue`
- `Components/Schedule/DayView.vue`
- `Components/Schedule/EventModal.vue`（案件打合せ・外出 / 社内予定の作成に使用）
- `Components/Schedule/EventDetailModal.vue`

### Schedule 専用（Calendar では使わない）
- `Components/Schedule/OverlayPanel.vue`（他人の予定 — Schedule のみ）
- `Components/Schedule/RoomReservationModal.vue`（会議室予約 — Schedule のみ）
- `Components/Schedule/NotificationPanel.vue`（通知 — Schedule のみ）

---

## イベントデータのAPI設計

Calendar ページは 2 本の API を並列呼び出しする:

### API-1: 会社イベント（Schedule と共有）
```
GET /schedule/events/range?start=...&end=...
```
- 既存エンドポイント（ScheduleEventController@range）
- is_company_event=true のイベント（案件打合せ・外出、社内予定）
- 参加者として招待されたイベント
- **Calendar では** overlay・会議室予約部分は無視する

### API-2: Calendar 固有イベント
```
GET /calendar/events/range?start=...&end=...
```
- 新規エンドポイント（CalendarEventsController@range）
- ジョブイベント（project_job_assignment_id 付き）
- is_company_event=NULL または false の旧カレンダーイベント（後方互換）
- 日報マーカー（diaries テーブルから日付のみ）

---

## イベント作成UIの設計

### 新規ボタン配置（UserCalendar ヘッダー）
```
[ 予定を追加 ▼ ]  [ マイジョブ ]  [ 進行表ジョブ ]  [ 日報入力 ]  [ 日程設定 ]  [ 休憩設定 ]
```

「予定を追加」は EventModal を開き、中でイベント種別（案件打合せ・外出 / 社内予定）を選択。
その他のボタンは既存の挙動を維持。

### 作成後のイベント連動
- EventModal で保存 → `is_company_event=true` で保存（ScheduleEventController 経由）
- → Calendar の API-1 再フェッチで即時表示
- → Schedule ページでも表示される

---

## データ同期の修正

### 問題
- 旧 ClientEventController / InternalEventController は `is_company_event = NULL` で保存
- ScheduleEventController では `is_company_event = true` で保存

### 方針
- 新規イベントは全て EventModal → ScheduleEventController 経由に統一（`is_company_event=true`）
- 旧 ClientEventController / InternalEventController は「編集」用途のみ残す（既存イベントの編集）
  - ただし edit/update 側も最終的に EventModal に移行する（フェーズ2以降）
- 過去データ（`is_company_event=NULL`）は変更しない（本番の既存イベントを安全に保持）

---

## フェーズ別タスク

### Phase 1: 共有コンポーザブル + シェル作成
- [ ] `useCalendarCore.js` を新規作成（ScheduleCalendar から nav ロジックを抽出）
- [ ] `CalendarShell.vue` を新規作成（MiniCalendar + ナビ + スロット）
- [ ] ScheduleCalendar を CalendarShell を使うようにリファクタ
- [ ] 動作確認：Schedule ページが変わっていないこと

### Phase 2: UserCalendar コンポーネント作成
- [ ] `UserCalendar.vue` を新規作成（CalendarShell 使用）
- [ ] API-1（schedule.events.range）呼び出し実装
- [ ] 新規 CalendarEventsController + route 追加
- [ ] API-2（calendar.events.range）呼び出し実装
- [ ] MonthView / WeekView / DayView でのイベント描画設定
- [ ] EventModal の組み込み（案件打合せ・外出 / 社内予定）
- [ ] 既存ボタン（マイジョブ・日報等）移植

### Phase 3: ページ差し替え + CalendarController 更新
- [ ] `Pages/Calendar/Index.vue` を UserCalendar を使うように更新
- [ ] CalendarController に `event_item_types` / `meeting_definitions` / `rooms` / `companies` / `departments` props 追加
- [ ] CalendarController の初期 events フェッチを削除（動的フェッチに移行）
- [ ] 旧 `Components/Calendar.vue`（FullCalendar 版）は残しておく（後で削除）

### Phase 4: 確認・微調整
- [ ] スタイル確認（Schedule と同一レイアウト）
- [ ] 日程設定・休憩設定モーダルの移植確認
- [ ] npm run build
- [ ] さくら本番での動作確認（既存イベントが消えていないこと）

---

## 変更ファイル一覧

### 新規作成
| ファイル | 説明 |
|---|---|
| `resources/js/Components/Schedule/useCalendarCore.js` | 共通ナビコンポーザブル |
| `resources/js/Components/Schedule/CalendarShell.vue` | 共通レイアウトシェル |
| `resources/js/Components/Calendar/UserCalendar.vue` | 新 Calendar コンポーネント |
| `app/Http/Controllers/CalendarEventsController.php` | Calendar固有イベントAPI |

### 変更
| ファイル | 変更内容 |
|---|---|
| `resources/js/Components/Schedule/ScheduleCalendar.vue` | CalendarShell を使うようにリファクタ |
| `resources/js/Pages/Calendar/Index.vue` | UserCalendar に差し替え、props 拡張 |
| `app/Http/Controllers/CalendarController.php` | props 追加、events フェッチ削除 |
| `routes/web.php` | `/calendar/events/range` ルート追加 |

### そのまま（変更なし）
- `Components/Schedule/MiniCalendar.vue`
- `Components/Schedule/MonthView.vue`
- `Components/Schedule/WeekView.vue`
- `Components/Schedule/DayView.vue`
- `Components/Schedule/EventModal.vue`
- `Components/Schedule/EventDetailModal.vue`
- `app/Http/Controllers/Schedule/ScheduleEventController.php`
- `app/Http/Controllers/Events/ClientEventController.php`（旧編集用に残す）
- `app/Http/Controllers/Events/InternalEventController.php`（旧編集用に残す）

---

## 注意点・リスク管理

1. **さくら本番の過去イベント**: `is_company_event=NULL` の既存イベントは絶対に変更しない
2. **ScheduleCalendar のリファクタ**: CalendarShell 切り出し時に Schedule ページが壊れないよう動作確認必須
3. **UTC/JST**: イベント表示時刻のズレに注意（CONSOLIDATED_CLAUDE.md の UTC/JST ルール厳守）
4. **CalendarController の `Inertia::render('Calendar')`**: 実際に `Pages/Calendar.vue` と `Pages/Calendar/Index.vue` どちらが使われているか Phase 3 着手前に確認
5. **`npm run build` は必ず Phase 毎の最後に実行**
