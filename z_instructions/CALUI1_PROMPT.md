# CALUI1_PROMPT.md — 新セッション開始用プロンプト

## このプロンプトの使い方

新しいセッションでこの作業を再開するときに、このファイルの内容をそのまま貼り付けて開始する。

---

## プロンプト本文

`/calendar`（ユーザー個人カレンダー）のUIをFullCalendarからScheduleページ（`/schedule`）と同じカスタムカレンダーに置き換える作業を行っています。

**設計ドキュメント:**
- `z_instructions/CALUI_PLAN1.md` — 詳細仕様・フェーズ別タスク・変更ファイル一覧
- `z_instructions/CALUI_MANAGER1.md` — 進捗管理・作業ログ

**現在のフェーズ:** CALUI_MANAGER1.md の進捗テーブルを確認して、次のタスクから再開してください。

**重要な背景:**
1. Calendar と Schedule は同じ `events` テーブルを使用している
2. Calendar で作成した「案件打合せ・外出」「社内予定」は `is_company_event=NULL` で保存されており、Schedule に表示されない（既存バグ）
3. 今後の新規作成は EventModal → ScheduleEventController 経由に統一して `is_company_event=true` で保存する
4. 過去の既存イベント（`is_company_event=NULL`）は絶対に変更しない（本番データ保護）
5. さくら本番に数か月分のイベントデータがあるため、データを消したり不具合が起きないよう最大限注意する

**共有コンポーネント設計:**
- `CalendarShell.vue` — MiniCalendar + ナビゲーション + メインスロット（新規）
- `useCalendarCore.js` — 共通ナビコンポーザブル（新規）
- `UserCalendar.vue` — 新 Calendar コンポーネント（新規）
- MonthView / WeekView / DayView / MiniCalendar / EventModal — そのまま再利用

**Calendarのイベント取得API（2本並列）:**
- API-1: `GET /schedule/events/range` — 会社イベント（Schedule と共有）
- API-2: `GET /calendar/events/range` — ジョブ・日報等Calendar固有（新規エンドポイント）

作業前に必ず CALUI_PLAN1.md と CALUI_MANAGER1.md を読んでから着手してください。

---

## キーファイル一覧

| ファイル | 役割 |
|---|---|
| `resources/js/Pages/Calendar/Index.vue` | Calendar ページ（Inertia） |
| `resources/js/Pages/Schedule/Index.vue` | Schedule ページ（参考） |
| `resources/js/Components/Schedule/ScheduleCalendar.vue` | Schedule カレンダーロジック（リファクタ対象） |
| `resources/js/Components/Schedule/CalendarShell.vue` | 新規: 共通シェル |
| `resources/js/Components/Schedule/useCalendarCore.js` | 新規: 共通コンポーザブル |
| `resources/js/Components/Calendar/UserCalendar.vue` | 新規: Calendar カレンダーロジック |
| `resources/js/Components/Schedule/EventModal.vue` | 予定作成モーダル（再利用） |
| `resources/js/Components/Schedule/MiniCalendar.vue` | ミニカレンダー（再利用） |
| `app/Http/Controllers/CalendarController.php` | Calendar ページコントローラー |
| `app/Http/Controllers/CalendarEventsController.php` | 新規: Calendar固有イベントAPI |
| `app/Http/Controllers/Schedule/ScheduleEventController.php` | Schedule イベントAPI（再利用） |
| `routes/web.php` | ルート定義 |
