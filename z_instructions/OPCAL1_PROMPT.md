# OPCAL1_PROMPT.md — 新セッション開始用プロンプト

---

## 概要

このプロジェクトは Laravel 11 / Vue 3 / Inertia.js / Vite / Tailwind CSS の SPA 構成です。
オペレーターカレンダー機能（`/coordinator/operator-calendar`）を実装しています。

詳細仕様: `z_instructions/OPCAL_PLAN1.md`
進捗管理: `z_instructions/OPCAL_MANAGER1.md`

---

## オペレーターカレンダー 設計サマリー

### 目的
進行管理（Coordinator/Clerk/Admin/SuperAdmin）がオペレーターの空き状況を横断的に把握し、
仮の予約（空き押さえ）を入れられるようにする機能。**オペレーター本人の実際のカレンダー
（`events` テーブル・`user/calendar`）には一切反映されない、あくまで進行管理側の仮予約。**

### 参考実装
| 流用元 | 用途 |
|--------|------|
| `app/Http/Controllers/ProofCoordinator/CalendarController.php` / `resources/js/Pages/ProofCoordinator/Calendar.vue` | 横断タイムラインUI・ドラッグ選択・モーダル |
| `app/Http/Controllers/Prepress/BoardController.php` / `app/Models/PrepressColorAssignment.php` | 担当色システム（色キー↔ユーザー） |
| `app/Models/ProofTeamMember.php` | 共有メンバーリスト |

**ただし校正カレンダーと違い、`events` テーブルへの同期は一切行わない。**

### 主要DBテーブル（新規作成、すべて `events` から独立）
- `operator_calendar_members` — 共有メンバー一覧（+メンバーで追加/削除、全員共通の1リスト）
- `operator_calendar_color_assignments` — 予約者の色（製版ボードとは別テーブル、同11色パレット）
- `operator_reservations` — 予約本体（`operator_user_id`=対象/`reserved_by_user_id`=予約者/`created_by_user_id`=操作者/`job_name`/`memo`/`starts_at`/`ends_at`）

### UTC/JST 方針（重要）
`starts_at`/`ends_at` は **JST文字列そのまま格納**（通常イベントと同じ方式）。
校正カレンダー（`proof_schedules`）のUTC変換・`getRawOriginal()` 特殊処理は踏襲しない。
このテーブルは `events` と無関係なので、混在ルールの対象外として単純に扱える。

### コントローラー（新規）
```
app/Http/Controllers/Coordinator/OperatorCalendarController.php
  index, data, all                        表示・日付切替・案件一覧トグル用全件取得
  storeMember, destroyMember, reorderMembers   共有メンバー管理
  store, update, destroy                  予約CRUD
  updateColorAssignment                   色↔ユーザー割当
```
全メソッドで `assertAccess()`（Coordinator/Clerk/Admin/SuperAdmin のみ、**Leader除外**）を実施。
既存の `coordinator` ミドルウェアは Leader も許可するため、コントローラー内で追加チェックが必要。

### Vue ページ（新規）
```
resources/js/Pages/Coordinator/OperatorCalendar.vue
```
- 日表示タイムライン（メンバー行 × 8:00-19:00 時間軸、`%`絶対配置、自作ドラッグ実装）
- 「+メンバー」ボタン（全ユーザーから検索追加）
- 色設定パネル（製版ボードの担当色変更パネルと同UI、トグル開閉）
- ドラッグ選択 → 予約作成モーダル（予約者セレクター/案件名必須1行/メモ任意）
- 既存ブロッククリック → 編集・削除、ドラッグ移動・リサイズ対応
- 「案件一覧」トグルテーブル（デフォルトOFF、開始日・終了日・予約者・案件名、全件表示）

### メニュー統合
`resources/js/Components/Tabs/CoordinatorNavigationTabs.vue` に
「オペレーターカレンダー」タブを追加（`route('coordinator.operator_calendar.index')`）。

---

## 別件: メンバー予定表フィルタ修正

`app/Http/Controllers/Coordinator/ProjectJobMemberScheduleController.php` の
`getEventsForDate()` に `->whereNotNull('event_item_type_id')` を追加し、
マイジョブ等を除外して「会議/外出/打合せ等の絶対に作業できない予定」のみ表示する。
Phase 1 に含める小規模な既存修正。

---

## 現在の実装フェーズ

`OPCAL_MANAGER1.md` の進捗テーブルを参照して作業を続けてください。
Phase 1（本体 + メンバー予定表フィルタ）完了後、Phase 2（二重予約リクエスト機能、
`OPCAL_PLAN1.md` の「将来計画」セクション参照）の詳細設計を改めて提案すること。

---

## 重要ルール（CLAUDE.md より）

- Vue / JS ファイルを変更したら必ず最後に `npm run build` を実行
- Artisan は必ずコンテナ内: `docker compose exec laravel bash -lc "php artisan ..."`
- 新規ページ作成前に `z_instructions/CONSOLIDATED_01_layout_and_ui.md` を確認
- ナビゲーションは必ず `route()` を使う（パスハードコード禁止、`route('coordinator.operator_calendar.index')` のようにオブジェクト形式でパラメータ渡し）
- 日付・時刻の実装は `z_instructions/CONSOLIDATED_05_calendar_and_jobbox.md` の UTC/JST混在ルールを確認
- 完了後: ChangelogSeeder への追記、CONSOLIDATED_05/09 の更新、本ファイル群を `z_instructions/archived/` へ移動
