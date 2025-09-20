# セキュリティ：メッセージ / ファイルメタサニタイズガイド

このドキュメントは、チャット／Bot 機能に対して実装したセキュリティ変更（クライアント／サーバ両面のメッセージ・ファイルメタのサニタイズ）について、設計意図、実装方針、運用設定、手動および自動テスト手順をまとめたものです。

## 目的

- ユーザーがファイルをアップロードした直後に生の JSON や外部 URL がそのまま表示される UX 問題を解消する。
- 悪意ある HTML/JavaScript 実行（XSS）や外部悪性ホストへの参照を防ぎ、サーバ側に危険な meta を保存しない。
- 変更を自動テストで回帰検証できるようにする。

---

## 実装要約（既存の変更）

- フロントエンド
    - `resources/js/Pages/Bot/ChatBot.vue` / `resources/js/Pages/Chat/ChatRoom.vue` に `normalizeMessage()` を導入。
        - アップロード時（楽観表示）、サーバからの履歴読み込み、リアルタイム受信の各経路で生 JSON/URL を解析して UI 表示用に正規化。
    - Markdown レンダリング後に DOMParser を使ってアンカーを検査する `sanitizeUrl()` を導入。
        - `javascript:` 等の危険スキームを排除。
        - 安全な外部リンクには `rel="noopener noreferrer"` と `target="_blank"` を付与。
    - beforeunload のセーブ処理に axios フォールバックを追加（sendBeacon の失敗への対策）。

- サーバー（Laravel）
    - `app/Http/Controllers/Bot/AiHistoryController.php` に `sanitizeMeta()` / `sanitizeFileMeta()` を追加。
        - 保存前にメタを検査し、安全な場合のみ `meta` カラムへ保存（それ以外は null）。
    - `app/Http/Controllers/Bot/BotFileController.php` のアップロードレスポンスを限定されたフィールドのみ返すよう変更。
    - `app/Http/Controllers/Chat/ChatController.php` でも返却メタをサニタイズ。
    - 許可ロジック（現行実装）:
        - 内部ストレージパス（`bot/`, `chat/`, `attachments/`）は許可 → `Storage::url()` を付与して返却
        - URL の場合は `config('app.url')` のホストと一致するもののみ許可
        - `original_name`, `mime`, `size` は最大長で切り詰めて保存

- DB / マイグレーション
    - テスト用にマイグレーションを修正して `ai_conversations.system_prompt` と `ai_messages.meta` を追加（テスト実行での不整合を解消）。

- テスト
    - `tests/Feature/AiHistorySanitizeTest.php` を追加: 外部 URL は削除され、内部 path は保持されることを検証。

---

## 設計方針

1. 防御は多層化（defense-in-depth）
    - フロントでの即時 UX 改善と表層的なリンクサニタイズ。
    - サーバでの最終的な信頼境界としての厳格なホワイトリスト検証（保存・返却時）。

2. 最小情報公開
    - API で返すファイル情報は必要最小限に限定（`original_name`, `mime`, `size`, `path`, `url`）し、不明/危険な情報は返さない。

3. 運用での許可セットは設定化可能にする（将来的対応）
    - 許可ホスト／許可プレフィックスを `config/` で管理することで、環境ごとの例外対応を可能にする。

---

## 推奨設定（次のステップ）

- `config/ai_files.php` のような設定ファイルを作り、次を入れる：
    - `allowed_prefixes` => ['bot/', 'chat/', 'attachments/']
    - `allowed_hosts` => [parse_url(config('app.url'), PHP_URL_HOST)]
    - `allow_external` => false（運用で切り替え可能）

この設定化によって sanitize のロジックをコントローラから切り離し、テストと運用を容易にします。

---

## 手動テスト手順（開発者がローカルで確認する短手順）

前提: Laravel の依存がインストールされており、テスト DB は `phpunit.xml` で sqlite in-memory 設定済み。フロントは `npm` を使ってビルド可能。

1. サーバサイドの振る舞い確認（API レベル）

- テストユーザーを作成してログイン（テスト用ユーザーファクトリを使うのが早い）
- 外部 URL を含む payload を `/bot/history` に POST して、`/bot/history/{id}/json` を取得する
    - 期待: `messages[0].meta` が null
- 内部 path（例: `bot/sample.txt`）を含む payload を POST する前に、`Storage::disk('public')->put('bot/sample.txt', 'hello')` でダミーファイルを配置
    - 期待: `messages[0].meta.file.path === 'bot/sample.txt'`

実行例（Tinker / PHPUnit ではない手動 curl）:

```bash
# 例: testrunner で CSRF と認証が必要なら TestCase を使った自動テストを推奨
# Laravel 環境内での実行例（artisan tinkerで簡易確認）
php artisan tinker
# -> run HTTP 内部呼び出しや Storage::disk('public')->put を実行
```

2. フロントエンドの振る舞い確認

- 開発サーバ起動

```bash
npm install
npm run dev
```

- ブラウザでチャット画面を開いてファイルアップロード
    - 期待: 楽観表示時に raw JSON や外部 URL がそのまま表示されず、UI 上はファイルカード／リンク表示になる
- AI が返す Markdown に外部リンクや悪意あるスキームを貼っても、UI 上で `javascript:` が実行されない（リンクは無効化）

---

## 自動テスト（既存テスト・追加テストの実行方法）

### 既存テスト実行

- PHPUnit / Pest を使う（プロジェクトルートで実行）

```bash
# composer がインストール済みであること
./vendor/bin/pest    # または ./vendor/bin/phpunit
```

### 新規テストの場所

- `tests/Feature/AiHistorySanitizeTest.php` にサニタイズの期待動作が実装されています。テストはデータベースをリフレッシュして実行されるよう `RefreshDatabase` trait を使っています。

### ローカルでの実行上の注意

- `phpunit.xml` を編集している場合は `DB_CONNECTION=sqlite` と `DB_DATABASE=:memory:` になっていることを確認してください（CI 環境によっては分ける）。
- マイグレーションで使用するカラム（例: `ai_messages.meta`）が最新であることを確認してください。もしマイグレーションが変更されたら、テスト用に migration ファイルが同期されていることを確認すること。

---

## CI への組み込み（簡単な提案）

- CI ジョブに `composer install`, `npm ci`, `npm run build` を含める。
- `./vendor/bin/pest --coverage` を実行してカバレッジを取得（必要な場合）。

---

## トラブルシューティング（よくある問題と対処）

- テストが MySQL 接続エラーを出す: `phpunit.xml` の env が MySQL を指していないか確認。ローカルで MySQL を使う場合はホストが解決可能かを確認するか、テスト用に sqlite を使ってください。
- テストが `table has no column` エラーを出す: マイグレーションとモデルで必要なカラムが揃っているか確認し、マイグレーションファイルのタイムスタンプ順やファイル内容を見直してください。
- フロントが生の JSON を表示する場合: すべての push/append/pushReplacement の経路に `normalizeMessage` が入っているか確認してください（楽観表示・履歴ロード・Echo ハンドラ）。

---

## 変更ログ（短）

- 2025-08-21: 初版作成 — フロント正規化、Markdown サニタイズ、サーバ側 sanitize、テスト追加、マイグレーション調整を反映。

---

## 追加作業候補（優先度順）

1. `config/ai_files.php` を追加して許可リストを設定化する（推奨）。
2. sanitize ロジックをサービスクラス（例: `App\Services\FileMetaSanitizer`）に切り出してユニットテストを追加する。
3. フロントの正規化ロジックを共有ユーティリティとして抽出して Vue コンポーネント間で再利用する。

---

## 追記: サーバ側 HTML サニタイズの中央化 (2025-08-31)

対応済み事項:

- `config/htmlpurifier.php` を追加し、HTMLPurifier のホワイトリスト設定を集中管理するようにしました。
- `App\Services\HtmlSanitizer` サービスを導入し、コントローラや他のサービスから容易に再利用できるようにしました。

実装方針（今回の変更でのルール）:

- コントローラやサービス内で直接 HTMLPurifier の設定を作らず、常に `App\Services\HtmlSanitizer` を経由して HTML を浄化してください。
- 許可タグ/属性の変更は `config/htmlpurifier.php` の `settings` キーを編集して行い、アプリ再デプロイ時に環境ごとの調整を行ってください。

既存コードの改修手順（開発者向け）:

1. 既に HTML を受け取り保存している箇所を検索してください（例: `MessageController`, `Bot/AiHistoryController` など）。
2. 直接 `HTMLPurifier` を使用している場合は、`App\Services\HtmlSanitizer` へ差し替えます。

- 例: `$body = (new \HTMLPurifier($config))->purify($input);` -> `$body = app(\App\Services\HtmlSanitizer::class)->purify($input);`

3. 必要に応じて `config/htmlpurifier.php` の `HTML.Allowed` を調整し、ユニットテストを追加して期待動作を防ぐ回帰テストを作成してください。

新規ユニットテスト案:

- `tests/Unit/HtmlSanitizerTest.php` を作成し、XSS ベクトルとなる幾つかの入力（`<script>`, `onerror` 属性, `javascript:` スキームを含むリンクなど）がフィルタリングされることを検証してください。

運用上の注記:

- `Cache.SerializerPath` に指定したディレクトリ（デフォルト: `storage/framework/htmlpurifier`）に PHP プロセスが書き込み可能であることを確認してください。CI 環境ではこのディレクトリをキャッシュしないでください（ビルドで再生成して問題ありません）。

次の推奨作業:

1. `App\Services\FileMetaSanitizer` を作成し、既存のファイルメタサニタイズロジックを移行してください（`config/ai_files.php` を参照する）。
2. 既存のコントローラを走査し、サニタイズパターンが統一されているか確認する自動コードオーディットスクリプト（簡易 grep ベース）を作成してください。

---

必要であれば私がこのファイルを PR の説明に流用して PR 作成まで代行します。どの追加作業を優先しますか？
