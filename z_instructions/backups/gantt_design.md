# プロジェクトスケジュール（ガント）機能設計

作成日: 2025-08-25
目的: ProjectSchedules 機能を `ProjectSchedules` ディレクトリに実装するにあたり、採用ライブラリ候補、システム構成、DB モデル、API、フロントのコンポーネント構成、リアルタイム協調編集設計、導入手順、テスト案を整理する。

要件ハイライト

- 数か月に及ぶスケジュール管理（ズーム: 日/週/月/四半期）。
- 階層化（親/子タスク）とフェーズ管理。
- 進捗（%）、ステータス（未着手/実行中/ブロック/完了/未遂など）、依存（先行/後続）。
- タスクに対する複数担当者（ProjectMembers と連携）。
- タスク毎にコメント／メモを残す（履歴）、および添付（将来）。
- 複数ユーザーによる共同編集（リアルタイムでの反映、差分/ロック・競合解決）。
- 権限制御（Coordinator 系の権限やプロジェクトごとの閲覧権限）。
- 大量データに対する表示最適化（部分レンダリング、タイムレンジロード）。

推奨ライブラリ候補（短評）

1. Syncfusion Vue Gantt
    - 長所: 機能豊富（依存関係、リソース割当、編集、セルテンプレート、スケール切替、CSV/Excel 出力など）、Vue 3 サポート、商用向け（無料の Community ライセンスがある場合あり）。
    - 短所: 商用ライセンス。企業での利用検討が必要。
    - 導入: `@syncfusion/ej2-vue-gantt`

2. dhtmlxGantt
    - 長所: 歴史あるガントライブラリ、機能充実（クリティカルパス、依存、ドラッグ編集、カスタムテンプレート）、Vue 統合可能。
    - 短所: 商用/ライセンスの注意（GPL/商用ライセンス）。
    - 導入: `dhtmlx-gantt`（JS をラップして Vue コンポーネントで使用）

3. frappe-gantt
    - 長所: 軽量で導入が簡単、OSS（MIT）、シンプルな UI と編集機能。
    - 短所: 機能が限定的（複雑な依存・リソース・大規模データには向かない）。

4. Bryntum Gantt / DayPilot / AnyChart
    - 長所: 企業向けで高機能、サポート付き。
    - 短所: 高コスト。

選定方針

- 要件（長期スケジュール、依存、複数担当、コメント、共同編集）を満たすには、機能豊富なライブラリが望ましい。短期 PoC なら `frappe-gantt` を使い、将来的に `Syncfusion` か `dhtmlx` に差し替えを検討する。ここでは以下を推奨する：
    - 本番/企業利用: `Syncfusion`（機能・安定性・Vue 3 サポート）または `dhtmlxGantt`。
    - まずは PoC/開発: `frappe-gantt` で UX/データモデルを確認。

アーキテクチャ概要

- バックエンド：Laravel (Controllers: Coordinator/ProjectSchedulesController、Models: ProjectSchedule, ProjectScheduleAssignment, ProjectScheduleDependency, ProjectScheduleComment, ProjectScheduleActivity)
- フロント：Vue 3 + Inertia（Pages: Coordinator/ProjectSchedules/{Index,Create,Show,Edit}、Components: GanttWrapper.vue, GanttToolbar.vue, GanttTaskEditor.vue, CommentsPanel.vue）
- リアルタイム: Laravel Broadcasting + Laravel WebSockets（自己ホスト）または Pusher
- 認可: Laravel Policies (ProjectSchedulePolicy)、Gate によるプロジェクトスコープ

DB スキーマ（概念）

- project_schedules (タスク単位、ガントの基本)
    - id
    - project_job_id (FK)
    - parent_id (nullable) — 階層構造
    - name (string)
    - description (text, nullable)
    - start_date (date/datetime)
    - end_date (date/datetime)
    - progress (integer) // 0-100
    - status (string) // enum: planned, in_progress, blocked, completed, aborted
    - order (integer) // 親内ソート
    - metadata (json nullable) // 将来的な拡張
    - created_by (user_id)
    - updated_by (user_id)
    - timestamps

- project_schedule_assignments (pivot)
    - id
    - project_schedule_id
    - user_id
    - role (string nullable) // optional
    - created_at

- project_schedule_dependencies
    - id
    - project_schedule_id
    - depends_on_schedule_id
    - type (string) // finish_to_start, start_to_start 等

- project_schedule_comments
    - id
    - project_schedule_id
    - user_id
    - body (text)
    - metadata (json nullable)
    - created_at, updated_at

- project_schedule_activity_logs
    - id
    - project_schedule_id
    - user_id
    - action (string) // created/updated/deleted/commented/assignedなど
    - payload (json nullable)
    - created_at

Eloquent モデル（概略）

- ProjectSchedule
    - relationships: projectJob(), parent(), children(), assignments(), dependencies(), comments(), activityLogs()

API とルーティング（例）

routes/web.php (Coordinator ルートグループ内)

- GET /coordinator/project_schedules -> index (Inertia)（プロジェクトのガントビュー/一覧）
- GET /coordinator/project_schedules/create -> create (Inertia)
- POST /coordinator/project_schedules -> store
- GET /coordinator/project_schedules/{id} -> show (Inertia)（単一タスク詳細 / またはガント全体表示の props）
- GET /coordinator/project_schedules/{id}/edit -> edit (Inertia)
- PUT /coordinator/project_schedules/{id} -> update
- DELETE /coordinator/project_schedules/{id} -> destroy
- POST /coordinator/project_schedules/{id}/reorder -> reorder (順序/親変更)
- POST /coordinator/project_schedules/bulk_update -> bulkUpdate (ドラッグで日付/進捗を更新)
- POST /coordinator/project_schedules/{id}/comments -> comments.store
- DELETE /coordinator/project_schedules/comments/{comment} -> comments.destroy

フロント側コンポーネント設計

- Pages/Coordinator/ProjectSchedules/Index.vue
    - ページ全体（ガント + サイドパネル）
    - props: projectJob, schedules (array), members (project team members), permissions

- Components/GanttWrapper.vue
    - props: tasks, options
    - emits: update-task (payload: {id, start, end, progress}), select-task, open-task-editor
    - 内部でライブラリインスタンスを初期化（Syncfusion/dhtmlx/frappe のいずれか）
    - 範囲ロード（visible date range が変われば `range-change` イベントを emit）

- Components/GanttToolbar.vue
    - ズーム、フィルタ（担当者）、インポート/エクスポートボタン

- Components/GanttTaskEditor.vue
    - タスクの編集モーダル（名前/期間/担当/進捗/説明/依存）

- Components/CommentsPanel.vue
    - タスクに紐づくコメントを表示・投稿

フロー（ユーザー操作）

- ガント画面を開く -> サーバから tasks を取得して GanttWrapper に渡す
- タスクをドラッグして日付変更 -> Gantt emits update-task -> フロントで optimistic 更新、API に PATCH を投げる
- API 更新成功 -> サーバはイベントを Broadcast (`private-project.{id}` チャネル) -> 他クライアントは受信して UI を更新
- タスクの「編集」ボタン -> TaskEditor を開いて詳細編集 -> 保存で PATCH + broadcast + activity log
- コメント投稿 -> comments.store -> broadcast で他クライアントにリアルタイム表示

リアルタイム設計（同時編集）

- ブロードキャストチャネル: private-project.{project_job_id}
- イベント例: TaskUpdated, TaskCreated, TaskDeleted, CommentCreated, AssignmentsUpdated
- 競合回避:
    - 軽量ロック: タスク画面を開いたら `editing_locks` テーブルに user_id と期限を保存し broadcast（任意）
    - 楽観的更新: タイムスタンプで上書きか検証して競合時はサーバの最新版を返す
    - UI 表示: 他ユーザーが編集中のタスクは小さなバッジで表示

バッチ更新とパフォーマンス

- 大規模データ（数百タスク）:
    - クライアント側で描画負荷を下げるため仮想化（ライブラリに依存）
    - サーバは `visible_range`（start,end）を受け取り該当するタスクのみ返却
    - 連続ドラッグの際はデバウンスしてまとめて `bulk_update` を呼ぶ

セキュリティと権限

- Policy: ProjectSchedulePolicy を実装し、プロジェクト所有者/Coordinator のみ作成・編集・削除可能とする
- Broadcasting: `private-project.{id}` チャネルは Policy で認可（ユーザーがそのプロジェクトに属しているか）
- バリデーション: 日付整合性（start <= end, 正しい依存関係）をバックエンドで強制

マイグレーション（雛形）

- CreateProjectSchedulesTable
- CreateProjectScheduleAssignmentsTable
- CreateProjectScheduleDependenciesTable
- CreateProjectScheduleCommentsTable
- CreateProjectScheduleActivityLogsTable

テスト計画

- 単体テスト: モデル・リレーション・ステータス遷移
- Feature テスト（HTTP）: create/update/delete, bulk update, コメント CRUD
- 統合/ブラウザテスト: 主要な UX（ドラッグで日付変更 → API 更新）

導入手順（高レベル）

1. PoC: `resources/js/Pages/Coordinator/ProjectSchedules/GanttPoC.vue` を作り、`frappe-gantt` で基本編集（作成/移動/削除）を実装。
    - npm install --save frappe-gantt
2. DB: マイグレーションを作成して `project_schedules` などのテーブルを導入。
3. バックエンド: `Coordinator/ProjectSchedulesController` を作成し、CRUD API を実装。
4. フロント: `GanttWrapper` コンポーネントでライブラリをラップ。
5. リアルタイム: Laravel WebSockets を導入して broadcast をセットアップ（Pusher 互換）。
6. テスト: モデル・Feature テストを追加。
7. 本番導入: 必要に応じて `Syncfusion/dhtmlx` などの商用ライブラリへ移行。

インストール例（PoC / frappe-gantt）

```bash
# Node
npm install --save frappe-gantt
# Laravel broadcasting（任意）
composer require beyondcode/laravel-websockets
```

実装上の注意点 / エッジケース

- タスクの依存関係ループをサーバ側で検出・拒否する。
- タスクの期間更新で子/親タスクの整合性をどう保つかルールを作る（親は子の最小開始/最大終了で自動延長等）。
- 時間帯（タイムゾーン）取り扱い: DB は UTC、表示はユーザー TZ で変換。
- 大量ログ/コメントの保存: 古いコメントのアーカイブ方針。

ドキュメント/引き継ぎ用メモ

- このファイルは `z_instructions/gantt_design.md` に保存済み。
- 次の作業ブロック（優先順）:
    1. PoC（frappe-gantt）で UI と DB モデルの連携を確認
    2. データモデルを確定してマイグレーション作成
    3. GanttWrapper によるライブラリ抽象化（将来ライブラリ差し替え可能にする）
    4. Broadcasting の PoC（WebSockets）で共同編集確認
    5. 商用ライブラリ導入の必要性判断（Syncfusion/dhtmlx/Bryntum）

参考リンク（調査時点）

- frappe-gantt: https://github.com/frappe/gantt
- dhtmlxGantt: https://dhtmlx.com/docs/products/dhtmlxGantt/
- Syncfusion Vue Gantt: https://www.syncfusion.com/vue-components/vue-gantt-chart
- Laravel WebSockets: https://beyondco.de/docs/laravel-websockets

追記（2025-08-26）

- 実装決定: PoC は当初 `frappe-gantt` を想定していたが、プロジェクト要件（FullCalendar の既存利用、柔軟なビュー切替、カレンダー操作との統合）により、UI 側は FullCalendar を採用する方針に変更した。
    - 理由: FullCalendar はカレンダー表示とドラッグ操作、リソースビュー・タイムグリッドなどの柔軟なビューを持ち、既存のカレンダー UI と親和性が高い。
    - 移行メモ: 既存 PoC 実装（`resources/js/Components/Calendar.vue`）は FullCalendar ベースの実装に合わせている。必要に応じて Gantt 表示が必須なら、FullCalendar と Gantt 表示の併用（または Syncfusion/dhtmlx の Gantt 導入）を検討する。

---

必要なら次のアクションとして、PoC 用の Inertia ページテンプレートと最小マイグレーション（ProjectSchedules テーブルのみ）を自動で作成します。どちらを先に進めますか？
