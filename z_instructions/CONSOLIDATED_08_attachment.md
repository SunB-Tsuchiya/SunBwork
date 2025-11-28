# 添付ファイル取り扱いガイドライン (Attachment Guidelines)

このドキュメントは、当リポジトリで行った添付ファイル（attachments）に関する実装変更・運用ルールをまとめ、次に来る AI や開発者が参照できるように作成しています。

## 目的

- 添付ファイルを一元的に `storage/app/public/attachments` に保存する。公開は直接 `/storage` を使わず、アプリのストリーミングエンドポイント経由で行う。
- サムネイル生成を `AttachmentService` に集約し、bot と chat、diary、message で共通処理とする。
- フロントエンドはストレージ直リンクではなくストリーミング API を使う（認証・署名・アクセス制御の一貫化）。

## 重要ファイルと役割

- `app/Services/AttachmentService.php`
    - ファイル保存（`storeUploadedFile` / `storeLocalFile`）、サムネイル作成 (`createThumbnailFromDiskPath`)、レスポンスメタ整形 (`formatResponseMeta`) を担当。
    - ストレージ先は public ディスクの `attachments/` 以下。

- `app/Http/Controllers/AttachmentController.php`
    - `stream(Request $request)` で `?path=` または `?id=` を受け取り、ストレージファイルを配信する。
    - DB フォールバック（basename マッチ含む）や添付オブジェクトの紐付けを検査する。
    - 署名付き URL 対応: コントローラ内で `URL::hasValidSignature($request)` をチェックし、有効な署名であれば read-only のアクセスを許可する。未認証ユーザー（`$user === null`）に対しては署名が無い限りアクセスを拒否する。

- ルート（`routes/web.php`）
    - `GET /attachments/signed` → `AttachmentController::stream`（一時的に `signed` ミドルウェア運用は状況により変更）。
    - ページ固有ストリーム: `/chat/attachments`（ChatController）、`/bot/attachments`（BotFileController）など。これらは SPA ページから同一オリジンのセッション認証で使用される。

- フロントエンドヘルパー
    - `resources/js/Helpers/attachment.js`
        - 添付の URL を正規化し、ページコンテキストに応じてストリームベースを切り替える（チャットページは `/chat/attachments`、bot ページは `/bot/attachments`、その他は `/api/attachments/stream` など）。
        - クエリの二重エンコードやパスの誤処理を避けるロジックを入れる。

## URL / 署名ポリシー

- 日報などの「閲覧のみで複数人に公開する」用途は `URL::temporarySignedRoute('attachments.signed', $expires, ['path' => $path])` で署名付き URL を発行する。
- 署名検証はミドルウェアでも可能だが、開発環境やプロキシの差で失敗することがあるため、コントローラ側で `URL::hasValidSignature($request)` を再確認する実装を推奨する。
- 署名付き URL を生成したら、フロントエンドはその URL をそのまま利用する（再エンコードやパラメータ編集を行わない）。

## ルーティング / ミドルウェアルール（重要）

- 原則: ストリーミング用のエンドポイント（ブラウザが第一者クッキーでアクセスする SPA 向けの `/chat/attachments` `/bot/attachments` など）は必ず `web` ミドルウェアで提供されるルート定義（`routes/web.php` または `routes/chat.php` 等）に置いてください。
- 理由: Laravel の `routes/api.php` は RouteServiceProvider 側で通常 `api` ミドルウェアグループ（stateless）や `prefix: api` が自動的に適用されます。`routes/api.php` 内で個別に `web` ミドルウェアを付けても、ファイルを読み込む際に `api` 側の設定が重畳されるケースがあり、結果としてセッションが開始されず `StartSession` が走らないため、ブラウザのセッション認証（`auth:sanctum` / laravel_session）を期待する SPA リクエストが 404 や 403 になる可能性があります。
- 具体的な失敗モード:
    - ブラウザから cookie ベースの認証でアクセスしてもセッションが開始されず、コントローラ側で認可チェックに失敗して abort(404/403) する。
    - 署名付き URL を期待しても、ホスト/スキームの不一致やプロキシ設定で `URL::hasValidSignature` が false を返すことがある（これ自体は別問題だが、api/web のミドルウェア設計が混在すると原因追跡が難しくなる）。
- 対処:
    - 既に `routes/api.php` に SPA 向けストリームや `/attachments/stream` を定義している場合は、`routes/web.php` 側に移動してください（あるいは `routes/chat.php` のような web に含まれるファイルへ移す）。
    - どうしても `api.php` に残す必要がある特殊ケースでは、RouteServiceProvider の設定を確認し、`api` 側の自動ミドルウェア付与を外すか、API 用のトークン認証に切り替える（cookie/session に依存しない）など明確に設計してください。
    - 移行後は `storage/logs/laravel.log` を tail して、アクセス時に `StartSession` ミドルウェアが走っているか、`URL::hasValidSignature` のログが出ているかを確認してください。

このルールは過去の会話と設計合意に基づきます: 「API ミドルウェアを通さず、SPA は web ミドルウェアを使う」。これを守らないとミドルウェアの重複で 404 や認証失敗が発生します。

## ファイル命名規則 (Stored filename convention)

- 本プロジェクトでは、ストレージ上の保存ファイル名は一貫して次の形式を採用します:
    - <uuid>\_<original_name>
    - 例: 元ファイル名が「サンプル画像 - コピー.png」の場合 ->
      `e7b8f3a2-..._サンプル画像 - コピー.png`
- ポイント:
    - UUID 部分は Laravel の `Str::uuid()` を使用して生成します。
    - 区切りはアンダースコア `_` を使用します。
    - original*name は多バイト文字を保存可能なまま残しますが、`..`, `/`, `\` といったパス操作に使える文字は `*` に置換して安全化します。
    - このルールは chat, bot, diary, messages の全アップロード経路で統一されます。
    - 実装上は `AttachmentService::storeUploadedFile` / `AttachmentService::storeLocalFile` を利用するか、同じ生成ロジックを呼ぶことを推奨します。

## 認可ルール（要約）

- メッセージに紐付く添付ファイルは、送信者または受信者のみ閲覧可能（ただし署名付き URL は例外として read-only で許可）。
- アップローダーの `user_id` が記録されている場合、原則そのユーザーまたは admin のみが削除可能。

## 実装上の注意点 / ベストプラクティス

- 可能なら ID ベース (`?id=123`) のストリーミングを優先する（パスのエンコード問題を回避）。
- サムネイルは `attachments/thumbs/` 下に保存し、`AttachmentService` が生成・公開する。
- フロントエンドのビルド（Vite）を変更したら必ず `npm run build` を実行して public/build を更新する。
- 本番では `signed` ミドルウェアを有効にする方向が望ましいが、APP_URL や TrustProxies の設定を合わせておくこと。

## テスト / デバッグ手順

1. ファイルが `storage/app/public/attachments` に保存されていることを確認する。例: `ls -l storage/app/public/attachments`。
2. 署名 URL を生成して直接 curl で叩く:
    ```bash
    curl -i '<署名 URL>' -H 'Accept: image/*'
    ```
3. コントローラがログを出すようになっているので、`storage/logs/laravel.log` を tail して挙動を確認する。
4. 署名の不一致が疑われる場合、`APP_URL` とプロキシ設定（`App\Providers\AppServiceProvider::boot` で `URL::forceRootUrl` を設定）を確認し、コンテナ再起動・キャッシュクリアを行う。

## 例（短い流れ）

- ファイルアップロード → `AttachmentService::storeUploadedFile()` 実行 → DB に `Attachment` レコードが作られる → 日報表示側で `URL::temporarySignedRoute('attachments.signed', now()->addMinutes(15), ['path' => $path])` を生成 → エンドユーザーは署名 URL 経由で `GET /attachments/signed` にアクセス → `AttachmentController::stream` が `URL::hasValidSignature` を確認して配信。

## 将来の改善案

- 全ストリーミングを ID ベースに移行してパスエンコーディングの問題を根絶する。
- サムネイル生成を非同期ジョブにしてアップロードのレスポンスを高速化する。
- 監査ログを充実させて、誰がいつどのファイルを生成／ダウンロードしたかを追跡可能にする。

---

ファイル: `z_instructions/attachment_guidelines.md` に保存済み。
このドキュメントを次の AI に渡して、添付周りの自動修正や追加作業の指示出しに利用してください。
