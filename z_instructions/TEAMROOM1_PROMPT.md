# TEAMROOM1_PROMPT.md — 新セッション開始用プロンプト

---

## 概要

このプロジェクトは Laravel 11 / Vue 3 / Inertia.js / Vite / Tailwind CSS の SPA 構成です。
チームルーム機能（`/team-rooms`）を実装しています。

詳細仕様: `z_instructions/TEAMROOM_PLAN1.md`
進捗管理: `z_instructions/TEAMROOM_MANAGER1.md`

---

## チームルーム機能 設計サマリー

### 対象データ
- `team_type = 'unit'` のチーム（`Leader/Teams` で管理）
- アクセス制御: `team_user` ピボットのメンバーのみ（ロール問わず）
- SuperAdmin は全チームルームにアクセス可

### タブ構成
| タブ | 内容 |
|------|------|
| overview | 概要・メンバー（ProjectJob の overview タブ流用）|
| schedule | チーム専用イベント（ProjectCalendar 流用）|
| board | カスタマイズ可能な Kanban ボード |
| minutes | 会議記録（Quill + コメント + 添付）|

### 主要DB テーブル（新規作成）
- `team_events` — チームイベント（スケジュール用）
- `team_boards` — ボード本体（1チームに1つ）
- `team_board_columns` — カスタマイズ可能なカラム（デフォルト3列）
- `team_board_cards` — ボードカード（SoftDeletes + 添付）
- `team_meeting_minutes` — 会議記録（Quill HTML + 添付）
- `team_meeting_attendees` — 参加者
- `team_meeting_comments` — コメント（全メンバーが投稿可）

### コントローラー（全て新規）
```
app/Http/Controllers/TeamRoom/
  TeamRoomController.php          index, show
  TeamEventController.php         index, store, update, destroy (JSON API)
  TeamBoardController.php         store, updateColumns
  TeamBoardCardController.php     store, update, destroy
  TeamMeetingMinuteController.php index, create, store, show, edit, update, destroy
  TeamMeetingCommentController.php store, destroy
```

### Vue ページ（全て新規）
```
resources/js/Pages/TeamRoom/
  Index.vue                所属チームルーム一覧
  Show.vue                 タブ付きメイン画面
  Minutes/Create.vue       会議記録作成
  Minutes/Show.vue         会議記録詳細
  Minutes/Edit.vue         会議記録編集

resources/js/Components/TeamRoom/
  TeamOverview.vue         概要・メンバー
  TeamSchedule.vue         スケジュール（ProjectCalendar 流用）
  TeamBoard.vue            Kanban + 一覧
  TeamBoardCard.vue        カード
  TeamBoardEditMode.vue    カラム管理
  TeamMinutesList.vue      会議記録一覧
  MeetingCommentSection.vue コメント
```

### 権限ルール（重要）
- 会議記録の編集・削除: 作成者 (`user_id`) OR チームの `leader_id`
- コメントの削除: 自分のコメントのみ
- ボードのカラム管理（編集モード）: チームメンバー全員が可能

### ボードカラム削除ルール
- カードが存在する場合は警告ダイアログを表示
- 強制削除するとカード（ソフトデリート）も一緒に消える

### 添付ファイル
- 既存の `attachmentables` ポリモーフィックテーブル流用
- `TeamBoardCard` と `TeamMeetingMinute` が対象
- OCR 不要

### 流用元コード
| 流用元 | 用途 |
|--------|------|
| `resources/js/Pages/Coordinator/ProjectJobs/Show.vue` | タブ構造・overview タブ |
| `resources/js/Components/ProjectCalendar.vue` | スケジュールタブ |
| `resources/js/Pages/Prepress/Board.vue` | Kanban ボード UI |
| `resources/js/Pages/Diaries/Create.vue` | Quill エディタ（会議記録） |
| DiaryComment パターン | コメント機能 |

---

## 現在の実装フェーズ

TEAMROOM_MANAGER1.md の進捗テーブルを参照して作業を続けてください。

---

## レイアウトルール（CONSOLIDATED_01 準拠）

- すべてのページは `AppLayout` を使用（`py-12` / `max-w-7xl` の二重ラップ禁止）
- `#header` スロットに `← 一覧に戻る` ボタン + `<h2>` を横並び（`flex items-center gap-3`）
- `<main>` タグ禁止、`ToastUnified` 重複禁止
- `route()` は必ずオブジェクト形式: `route('team-rooms.show', { team: team.id })`
- タブは `#tabs` スロットに配置（`Show.vue` の ProjectJob 流用）

---

## 重要ルール（CLAUDE.md より）

- Vue / JS ファイルを変更したら必ず最後に `npm run build` を実行
- `npm run build` はプロジェクトルート (`/home/tchirosb/SunBWork`) で実行
- Artisan は必ずコンテナ内: `docker compose exec laravel bash -lc "php artisan ..."`
- 新規ページ作成前に `z_instructions/CONSOLIDATED_01_layout_and_ui.md` を確認
- ナビゲーションは必ず `route()` を使う（パスハードコード禁止）
