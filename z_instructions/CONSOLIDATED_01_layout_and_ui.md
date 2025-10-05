# レイアウトと UI（統合ドキュメント）

概要

このドキュメントは `z_instructions/backups` にあったレイアウト／UI 関連の指示書群を統合したものです。新規ページを作る際はまず本ファイルと `first_prompt.md` を読み、以下のルールを必ず守ってください。

最優先ルール（必ず守る）

- すべての Inertia ページは AppLayout を使うこと。ページ構造は必ず次の階層を守る：
    - <div class="py-12"> → <div class="max-w-7xl mx-auto sm:px-6 lg:px-8"> → <div class="rounded bg-white p-6 shadow"> …</div></div></div>
    - 上記の 2 つの外側 div は常に閉じてから </AppLayout> とすること。
- Ziggy の route() を使う場合はパラメータ名をオブジェクトで渡す（例: route('coordinator.project_jobs.show', { projectJob: job.id })）。
- ナビ/タブ/ボタンの色やバッジは既存のガイドラインに従う（例：管理者＝赤、リーダー＝オレンジ、進行管理＝緑、一般＝青）。

実装と注意点

- テーブルは `min-w-full divide-y divide-gray-200` を基準に作る。ヘッダは `bg-gray-50`、行は `hover:bg-gray-50`。
- カードは `rounded bg-white p-6 shadow` を再利用する。
- レスポンシブは Tailwind の sm/md/lg を使って対応する。
- ファイル名の大文字小文字は一貫させる（差分のみのケース違いは TypeScript/jsconfig 警告の原因になる）。

参照元（元バックアップファイル）

- layout_guideline_for_ai_agent.md
- layout_and_ui_unification_spec_for_ai_agent.md
- (関連: jobbox_changes_summary.md の一部 UI 記述)

備考（DB/環境関係）

- ドキュメント内の UI 実装例は MySQL を前提としている。ローカルで `php artisan` を実行する場合はコンテナ内実行を推奨（例: docker compose exec laravel bash -lc "php artisan ..."）。

次のアクション

- 新規ページ作成時にこのファイルを必ず参照。必要ならこのファイルに具体的な実装コード片（小断片）を追記します。
