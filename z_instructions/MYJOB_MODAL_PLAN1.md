# マイジョブ通常新規登録モーダル・計画 1

更新: 2026-10-14

## 決定と対象

- 利用者は通常マイジョブ新規登録の別ページ遷移を「気軽に入れられる」モーダルへ変更すると決定した。最初の「別ページのまま」という回答は直後に撤回された。
- 初期表示は案件、ジョブ名、作業日、開始・終了時刻、任意の数量。概要、ファイル、作業種別・工程・サイズ・難易度などは「詳細を開く」に置く。担当者は本人、状態は進行中を初期値にする。
- 本番では情報出版の Super Admin 試用者だけに新 UI を出す。`PersonalWorkSchedule::available()` と同じサーバ判定を使う。他の利用者は現行ページと保存経路を使う。一般公開は別判断。
- 今回は通常の自己登録を対象とする。依頼ジョブからマイジョブへの転換、進行表・管理表からの登録、既存ジョブの編集、過去データ流用は既存画面・関係キーを保つ。依頼ジョブ自体の項目整理は次工程。
- 大まかな案件と ProjectJob の紐づけは後工程。今回のマイジョブは既存 `project_job_assignments` を保存するため、選択できる案件は現行の ProjectJob と「その他」に限る。

## 現行経路と保護点

`MyJobBox/Index.vue` の作成ボタンは `user.project_jobs.assignments.create` に移動し、通常のパラメータなしなら `events.create_job` へリダイレクトする。カレンダーのマイジョブ入口も `events.create_job` へ移動する。通常画面は `Events/Create_Job.vue`、入力部品は `AssignmentForm.vue`。`MyJobBox/Create_user.vue` は依頼転換等の別経路。保存先 `User/ProjectJobAssignmentController::store()` は `project_job_assignments` と予定 `events` を作り、時刻があれば関連付ける。依頼転換の `supersedes_assignment_id`、続きの `source_assignment_id`、進行表セルのキーを今回の短縮導線で偽造・欠落させない。

## 実装方針

1. `EventController::createJob()` の既存の案件候補・「その他」・状態・マスタ取得を使い、試用対象者のモーダル要求だけ JSON で返す。通常ページは従来の Inertia 応答。モーダル取得要求をサーバで権限判定する。
2. 独立した `MyJobCreateModal.vue` を追加する。既存の大きな `AssignmentForm.vue` は複雑な経路のため維持し、通常の短い新規登録だけ専用フォームにする。詳細欄を開いて概要、ファイル解析による情報、作業種別・工程・サイズ・難易度を使えるようにする。数量は常時表示、単位はページ/ファイル。日付・開始/終了は必須で、逆転や同時刻を保存させない。選択・保存エラーをモーダル内に表示し、閉じる・Esc・外側クリックを用意する。
3. 既存 `user.project_jobs.assignments.store` を使う。モーダル試用リクエストに限り JSON の成功応答を返し、保存後はモーダルを閉じてマイジョブ一覧またはカレンダーを再取得する。通常フォームのリダイレクトは維持する。割当と予定の保存を再実装しない。
4. `MyJobBox/Index.vue`、実際の個人カレンダー `UserCalendar.vue`、必要な旧カレンダー入口では、サーバから渡された試用権限が true の場合だけモーダルを開く。false は従来リンク/遷移。日付・選択時刻がある入口では初期値へ渡す。

## 影響候補

- `app/Http/Controllers/EventController.php`: モーダル用候補 JSON、サーバ権限
- `app/Http/Controllers/User/MyProjectJobController.php`: 一覧への試用判定
- `app/Http/Controllers/User/ProjectJobAssignmentController.php`: モーダル保存成功時の JSON 応答
- `resources/js/Components/MyJobCreateModal.vue`: 新しい短い入力
- `resources/js/Pages/MyJobBox/Index.vue`: 作成入口と一覧再取得
- `resources/js/Components/Calendar/UserCalendar.vue`: 時刻選択からの作成と予定再取得
- 必要時に `resources/js/Components/Calendar.vue` と表示元の権限 prop: 旧入口を同じ判定で揃える
- `tests/` の権限・候補・保存の重要経路テスト、変更履歴、統合資料

## 検証と公開ゲート

- 非試用ユーザーの現行入口・通常フォーム応答・保存後リダイレクトが維持されること。
- 試用対象者のみ候補 JSON と新モーダルを使えること。URL 直打ちでも権限を迂回できないこと。
- 案件選択、数量未入力/入力、時刻事前値、詳細入力、ファイル情報、バリデーション失敗、保存後の一覧/予定再取得を確認する。二重送信を防ぐ。
- 依頼からの転換、過去データ流用、進行表・管理表リンク、既存編集のコード経路を維持する。
- Vue/JS 変更後に Vite ビルド。PHP 構文・対象テストは実行可能な環境で確認。本番さくらへの SSH は `AGENTS.md` に従い正確なコマンド提示と利用者の事前確認後だけ。Super Admin 試用の画面確認と一般公開判断を混同しない。
