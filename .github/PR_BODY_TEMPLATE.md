PR タイトル: chore: conservative CORS config + session hardening + redis guidance

変更の要約

- `config/cors.php` を追加し、開発時の Vite ポートを限定的に許可する保守的なCORS設定を導入。
- `.env.example` に本番向けサンプルキーを追加:
  - `CORS_ALLOWED_ORIGINS`
  - `SESSION_SECURE_COOKIE=true`
  - `SESSION_SAME_SITE=lax`
  - Redis 切替ヒント `SESSION_DRIVER=redis`
- `config/session.php` を env 依存に変更して `SESSION_DRIVER` で切替できるようにした。
- `scripts/check_redis.php` を追加（Redis への接続確認用スクリプト）。
- `README.md` に CORS とセッションハードニングの上書き手順を追記。
- 関連ドキュメントを `z_instructions/` に追加。

検証手順

1. ローカルで新ブランチ `cors-session-hardening-1` をチェックアウト済み。
2. Docker 環境でキャッシュをクリア:

    php artisan config:clear
    php artisan route:clear
    php artisan cache:clear
    php artisan view:clear

3. デフォルトの開発設定でブラウザからログインし、`/api/user` が 200 を返すことを確認。
4. 本番用の `.env` に `CORS_ALLOWED_ORIGINS` を設定し、`SESSION_SECURE_COOKIE=true` を有効にしてから再度動作確認。
5. Redis を使う場合:
   - `.env` に `SESSION_DRIVER=redis` と `REDIS_*` を設定
   - `php scripts/check_redis.php` を実行して接続を確認

注意点

- `config/cors.php` はデフォルトで保守的ですが、本番では確実にフロントエンドのホストに限定してください。
- セッションストアを Redis に切り替える際は、Redis の可用性と監視を準備してください。

追加メモ

- 私が PR を作成することも可能です。PR の本文はこのファイルの内容をそのまま使えます。
