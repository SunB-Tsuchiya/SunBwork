# セキュリティとセッション（統合ドキュメント）

概要

このファイルは `z_instructions/backups` 内のセキュリティ、CORS、セッション、Redis 関連のメモを統合したものです。SPA（Inertia/Vite）と Laravel Sanctum を安全に運用するための最小限の手順と注意点をまとめます。

重要要点

- 本番は MySQL を使用。ローカルで `php artisan` を直接実行すると DNS 解決エラーが出ることがあるため、必ずコンテナ内で実行する（docker compose exec laravel bash -lc "php artisan ..."）。
- セッションを Redis に移行する場合は：
    - .env に REDIS\_\* と SESSION_DRIVER=redis を設定
    - config/session.php の driver を env に依存させる（'driver' => env('SESSION_DRIVER','file')）
    - キャッシュ／設定クリアを忘れずに (php artisan config:clear && php artisan cache:clear)
- SPA + Sanctum で 401 が出る場合の対策：API ルートを web ミドルウェア経由に変更して StartSession を通す（例: ->middleware(['web','auth:sanctum'])）。
- CORS: config/cors.php と .env の CORS_ALLOWED_ORIGINS を本番ドメインで限定する。

ファイルアップロードとサニタイズ

- AI/チャット用ファイルは metadata を sanitize してから保存する（外部 URL を無条件に保存しない）。
- HTML は HTMLPurifier などでサニタイズしてから保存・表示する。App\Services\HtmlSanitizer を経由すること。

参照元（元バックアップファイル）

- CORS_HARDENING_PROPOSAL.md
- REDIS_SESSION_MIGRATION.md
- SESSION_COOKIE_401_SUMMARY.md
- SECURITY_SANITIZATION_GUIDELINES.md
- SECURITY_CHANGES_LOG.md

運用チェックリスト

1. .env.example に CORS_ALLOWED_ORIGINS, SESSION_SECURE_COOKIE, SESSION_SAME_SITE を記載する
2. セッションを Redis に切り替える場合は config を変更し、check_redis スクリプトで接続確認
3. セキュリティ関連の重大変更はログ（storage/logs/laravel.log）で必ず確認する

次のアクション

- 必要なら具体的な config 差分や .env 例を追加します。
