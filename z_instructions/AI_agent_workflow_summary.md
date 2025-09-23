# AIエージェント作業ワークフロー（要約）

このファイルは `z_instructions` 内の指示を簡潔にまとめ、今後の作業で常に参照するためのテンプレートです。必ず `first_prompt.md` を最初に読み、以降のすべての作業はこのワークフローに従ってください。

## 必須事項

- 作業開始時：必ず `z_instructions/first_prompt.md` を読み、その後 `z_instructions` ディレクトリ内のすべてのファイル内容を把握する。
- レイアウト/UI は `layout_guideline_for_ai_agent.md` を最優先で適用する。
- 権限/階層設計は `site_structure_and_roles_for_ai_agent.md` に従う（Admin/Leader/Coordinator/User/Guest）。

## 環境（重要）

- 実行環境は Docker。ホスト直下で `php artisan` を実行すると MySQL の DNS 解決エラー（getaddrinfo for mysql failed など）が発生することがある。
- MySQL を利用している（ユーザ環境は MySQL）。SQLite を前提にしないでください。
- `php artisan` や `php artisan tinker` は必ずコンテナ内で実行する（例：`docker exec -it sunbwork-laravel bash -lc "php artisan ..."` または `docker compose exec laravel bash -lc "php artisan ..."`）。

## よく使うコマンド例（コンテナ内実行を前提）

- マイグレーション & Seeder（AiPresetsSeeder の例）:
  docker compose exec sunbwork-laravel bash -lc "php artisan migrate --force && php artisan db:seed --class=AiPresetsSeeder"

- tinker:
  docker compose exec sunbwork-laravel bash -lc "php artisan tinker"

- キャッシュクリア（必要に応じて）:
  docker compose exec sunbwork-laravel bash -lc "php artisan cache:clear && php artisan config:clear && php artisan route:clear"

- npm（フロント）:
  npm run dev # 開発
  npm run build # 本番ビルド
  （必要に応じてコンテナ内で実行される場合はコンテナ名に合わせる）

## ファイル権限

- 生成先ディレクトリ（例：`storage`）にはWebユーザ権限を付与：
  sudo chown -R www-data:www-data storage bootstrap/cache

## テストに関する注意

- `phpunit.xml` や `tests` は CI / ローカルで sqlite を使う設定になっている場合がある。ローカル開発ではプロジェクトが MySQL を使うため、テストや artisan コマンドの実行時に DB 設定に注意すること。

## トラブルシュートの簡単メモ

- DB 接続エラー（getaddrinfo for mysql failed）: ホストから直接 artisan を実行せず、コンテナ内でコマンドを実行する。
- 500 エラーやマイグレーション失敗: コンテナの MySQL サービスが `healthy` かを `docker compose ps` / `docker compose logs mysql` で確認。
- ファイルアップロードや書き込みエラー: `storage` の権限を確認。

## 開発時のルール（要点）

- 新しいページ/コンポーネントを作る際は AppLayout.vue の構造（main 内の py-12 / max-w-7xl 等）を守る。
- レイアウトの閉じ忘れのチェックは必須（`</div>` が `<AppLayout>` 直前にあるか）。
- エラーハンドリングは try-catch とログ（storage/logs/laravel.log）を丁寧に残す。

---

この要約は常に参照し、作業中に新たな注意点が見つかったら `z_instructions` に追記してください。
