# 認証・Fortify・CORS（まとめ）

概要

Laravel Fortify / Jetstream を用いた認証実装と、SPA（Vite/Inertia）向け CORS 設定、認証周りでよく起きるトラブルとその対処を整理します。

必読ルール

- Register 画面／ユーザ登録フローを変更する場合は app/Providers/FortifyServiceProvider.php の registerView を確認すること。
- Ziggy を使う場合は route() の呼び出し方（パラメータ名）に注意する。間違うと runtime エラーになる。
- SPA（ブラウザ）から Sanctum を利用する場合は StartSession と CSRF フローが通ることが前提。API ルートの扱いを見直す必要がある。

よくある問題と修正

- 401 (Unauthenticated.)
    - 原因: API ミドルウェア経路で StartSession が通っていない
    - 修正: 重要エンドポイントを web ミドルウェア経由（->middleware(['web','auth:sanctum'])）に変更
- getaddrinfo for mysql failed
    - 原因: コンテナ外で artisan を実行したためホスト名解決に失敗
    - 修正: docker compose exec などでコンテナ内から実行する

参照元

- AUTH_SETUP.md
- SESSION_COOKIE_401_SUMMARY.md
- CORS_HARDENING_PROPOSAL.md

運用メモ

- registerView は companies/departments/assignments の eager load を行っていることがある。パフォーマンスに注意。
- .env.example に本番向けの CORS 値を残す（CORS_ALLOWED_ORIGINS）

次のアクション

- Fortify の登録フローや Register.vue を変更する場合は、このファイルを更新して差分手順を残してください。
