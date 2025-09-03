# ジョブ割り当てとユーザーカレンダー仕様（要約）

作成日: 2025-09-03
目的: 次の AI エージェントに読ませるための、プロジェクト内ジョブ割り当てフローとユーザーのカレンダー実装に関する仕様・設計・運用ノート。

---

## 1. 概要

このドキュメントは、Coordinator（管理者/発注者）がプロジェクトジョブをユーザーに割り当て → ユーザーがカレンダー上で予定をセット → 割り当て完了の通知（内部メッセージ）を行う一連のフローを詳述する。

目標:

- 割り当て（ProjectJobAssignment）→ カレンダー連携 → 割当者（assigner）への通知を安定して行う。
- フロントとバックの責務を明確にし、Inertia を使ったスムーズな UX を実現する。

## 2. 主要ファイル構成（抜粋）

- app/Models/
    - ProjectJobAssignment.php — ジョブ割り当て（assigned jobs）モデル
    - Event.php — ユーザーのカレンダー予定モデル（start, end, title, description）
    - Message.php / MessageRecipient.php — アプリ内メッセージ
    - Attachment.php — 添付ファイル（イベント添付等）

- app/Http/Controllers/
    - CalendarController.php — カレンダー用データ提供（Inertia render）
    - EventController.php — 予定の CRUD（store で assignment を scheduled にし、通知を作る）
    - MessageController.php — 内部メッセージの管理
    - User/AssignedProjectController.php — 割り当て一覧／詳細表示（AssignedJobs 画面の元）

- resources/js/Pages/
    - Calendar.vue — カレンダーページラッパー（Inertia ページ）
    - Events/Create.vue — 予定作成 UI（割り当てから来た場合は job を prefill）
    - Events/Edit.vue — イベント編集（Create と UI を合わせる）
    - Events/Show.vue — イベント詳細（編集・削除ボタン）
    - User/AssignedJobs/Index.vue — 割り当て一覧（カレンダーへ戻るリンク、予定セットボタン）
    - Messages/\* — メッセージ表示／送信 UI

- resources/js/Components/
    - Calendar.vue — FullCalendar のラッパーコンポーネント（diaries/events/jobs をマージ）

- routes/web.php — ルート定義（`calendar.index`, `events.*`, `user.assigned-jobs.*`, `messages.store` 等）

## 3. データモデルと重要カラム

- ProjectJobAssignment
    - id, user_id, project_job_id, title, detail, accepted, assigned, scheduled (bool), scheduled_at (datetime), created_by
    - リレーション: projectJob, user

- Event
    - id, user_id, title, description, start (datetime), end (datetime), date (virtual accessor), attachments

- Message / MessageRecipient
    - Message: id, from_user_id, subject, body, status, sent_at
    - MessageRecipient: id, message_id, user_id, type (to/cc)
    - Broadcast: MessageCreated イベントで private channel `messages.{user_id}` に配信

## 4. Coordinator → ユーザー割り当てフロー

1. Coordinator がプロジェクトジョブを作成/選択し、`ProjectJobAssignment` を作成 or 更新して `assigned=true` / `accepted=true` 等を設定。
2. 割り当ては `AssignedJobs`（`User/AssignedJobs/Index.vue`）で一覧表示され、各行に「予定をセット」リンクがある。
3. 「予定をセット」を押すと `Events/Create` ページへ遷移（クエリ `?job={assignment_id}` を付与）。
    - `EventController::create` は `job` query param を読み、該当 `ProjectJobAssignment` を読み込んで `job` プロップ（jobData）を Inertia に渡す。

## 5. 予定セット（ユーザー側）フロー

1. `Events/Create.vue` は `props.job` を受け取り、タイトル/詳細等をプレフィルする（`job.title`, `job.details`, `assigned_user_name` 等）。
2. 画面は Quill 等のリッチエディタではなく、シンプルな `textarea` に統一している（UX の簡素化のため）。
3. バリデーション（フロント）:
    - 開始・終了は有効な日時（Date で判定）
    - 終了 > 開始
    - 最小長: 15 分
    - 同日日付の既存イベントと重複チェック（`/events?date=YYYY-MM-DD` を取得して比較）。重複時は確認ダイアログで続行可否。
4. 送信時に `job_id` をフォームに含める（props.job.id → form.job_id）。
5. サーバーへ POST: `EventController::store` を呼ぶ。

## 6. サーバー側処理（EventController::store の要旨）

- バリデーションを行い、`Event` を作成（start, end を結合して保存）。
- `job_id` がある場合:
    - `ProjectJobAssignment::find($jobId)` を取得。
    - トランザクション内で `scheduled_at`（あれば）を設定し、`scheduled` フラグを true にする（あれば）。
    - 割当者（assigner）を決定:
        - `project_job_assignments.created_by` があれば優先
        - それ以外は `assignment->projectJob->user_id` を fallback
    - assigner が存在するなら、内部 `Message` を作成:
        - subject: `ジョブ割り当て終了`
        - body: プロジェクトジョブID / 予定をセットしたユーザーID / イベント名 / 開始 / 終了 / 詳細（各行）
        - MessageRecipient を作成（to: assigner）
        - event(new MessageCreated($message)) を fire してリアルタイム通知（private channel `messages.{user_id}`）
- 添付ファイルが送信されていれば Attachment を保存する処理もあるが、Create/Edit の UI は添付を使わない方向に揃えられている。
- 最終的に Inertia リダイレクトでカレンダーへ戻す（`redirect()->route('calendar.index')`）か、JSON を返す場合があるが、Inertia リクエストならリダイレクトを返すこと。

## 7. メッセージの整形と表示

- メッセージ本文はサーバーで改行を含む plain テキストとして作成される。
- 表示側 (`resources/js/Pages/Messages/Show.vue`) では DOMPurify 等でサニタイズしつつ、既知のラベル（`プロジェクトジョブID:`, `予定をセットしたユーザーID:` 等）ごとに改行を入れる処理がある。
- 開発環境では `MAIL_MAILER=log` になっていることがあるため、外部メールは送られずログに記録される点に注意。

## 8. フロント実装の重要ポイント

- `Events/Create.vue` / `Events/Edit.vue`
    - editor を Quill から textarea に統一（添付なし）。
    - useForm を利用し、`job_id` を含める。
    - 重複チェック（同日） → confirm
    - 最小時間チェック（15 分）
    - 送信中はボタンにスピナーを表示し、二重送信を防ぐ。
    - 成功後: 割り当て元から来た場合は `user.assigned-jobs.index` へ戻す（フルナビゲーションでサーバー再フェッチ）。通常は `calendar.index` へ戻る。

- `Calendar.vue`（コンポーネント）
    - `props.diaries`, `props.events`, `props.jobs` をマージして FullCalendar に渡す。
    - diaries は allDay のオレンジ、events は時間帯表示の青、jobs は allDay の薄青で表示。
    - `initialView` はイベントが全て allDay の場合 `dayGridMonth`、そうでなければ `timeGridWeek` を使用。
    - FullCalendar のイベント操作（resize, click）から API 呼び出しを行い、成功時にアラートやリロードを行う。

## 9. 削除フロー（イベント削除）

- `Events/Show.vue` に「削除」ボタンを追加。
- クリックで `confirm('この予定を削除しますか？')` を表示し、OK なら `router.delete(route('events.destroy', { event: id }))` を呼ぶ。
- `EventController::destroy` は Inertia リクエスト（`X-Inertia` ヘッダ）を受けた場合は `redirect()->route('calendar.index')` を返す。これによりクライアントはカレンダーへ戻る。

## 10. デバッグ／運用ノート

- ログは問題追跡に重要。`EventController::store` や `CalendarController::index` に一時ログを入れて状態を確認した。
- 一部環境で DB CLI（artisan tinker）が接続できない場合があったため、laravel.log を主な検証手段として使用した。
- 開発時は `MAIL_MAILER=log` のため、Mail::raw では送信されずログに書かれる点に注意。

## 11. 例外・エッジケース

- assignment が既に `scheduled` の場合は重複して Event を作らない（サーバーガード）。
- job_id が未指定で Event が作られた場合でも通常の Event 作成フローに従う。
- assigner を決定できない場合はメッセージ送信をスキップし、ログに警告を残す。

## 12. 推奨テストケース（自動化が望ましい）

- Coordinator が割り当て → ユーザーが Create で予定を登録 → `ProjectJobAssignment.scheduled` が true になる。
- 作成後に `messages` テーブルへ `ジョブ割り当て終了` メッセージが追加され、MessageRecipient が作成される。
- 重複時間の重複警告が出ること（フロント確認）およびサーバー側で重複を許可するか否かの振る舞い。
- Event の削除後に Inertia がカレンダーへ戻る（`X-Inertia` ヘッダがある場合）。

## 13. TODO / クリーンアップ

- 一時デバッグルート（`/debug/events/send-test-completion`）は検証が終わったら削除すること。
- `Log::debug` / `console.debug` の不要箇所は抑制またはログレベルを下げて本番残存を避ける。
- メッセージ/ジョブ割り当てのユニットテストと E2E を追加する。
- UI 文言を UX チームと合わせる（例: "セット済" 表示のタイミングや色）。

## 14. 次の AI エージェントへの注意点

- カレンダー表示の不具合調査では、サーバー側 props（`CalendarController::index` が返す `events/diaries/jobs`）とクライアント側で `Calendar.vue` が合成する `events` 配列の双方を確認すること。
- 環境によっては DB 接続やメール送信が制限されているので、laravel.log を必ずチェックすること。
- 既存の `messages` フローに変更を加える場合、MessageCreated の broadcast と private channel 名 (`messages.{user_id}`) を壊さないよう注意する。

---

以上。このファイルを次のエージェントに渡して、さらに仕様の補完やテストケースの追加を依頼してください。
