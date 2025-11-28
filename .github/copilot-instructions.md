# Copilot / AI 向けプロジェクト指示書

このファイルはこのリポジトリ（SunBWork）に対して、次に来る AI エージェントや Copilot に渡すべき包括的な指示をまとめたものです。
AI が機能追加・修正・デバッグを自動化する際に必要なコンテキスト、ルール、セキュリティポリシー、運用手順を日本語で記載します。

目次

- プロジェクト概要
- リポジトリ構成と重要ファイル
- 開発環境の起動とコマンド（ローカル）
- コーディング規約とレイアウト方針
- 認証・セッション・CORS の扱い
- 添付ファイル（attachments）に関する詳細ルール
- セキュリティ要件（シークレット・サニタイズ・アップロード）
- 署名付き URL、APP_URL、TrustProxies の注意点
- テスト・CI・品質ゲート
- ロギング・デバッグ方法
- ブランチ/コミット/PR の運用ルール
- デプロイと運用時チェックリスト
- 最後に（AI が守るべき行動規範）

---

## プロジェクト概要

- フレームワーク: Laravel (PHP) + Inertia + Vue 3 + Vite
- ストレージ: Laravel Storage public disk -> `storage/app/public` が `/storage` に公開される
- 認証: Laravel Sanctum + Jetstream/Fortify
- 主な機能: チャット（メッセージ、添付）、Bot（AIチャット）、日報（Diary）、ファイルアップロード、ジョブ/カレンダー

目的: AI がリポジトリを読み、ローカルでビルド・テスト・修正を行い、安全に PR を作成できること。

---

## リポジトリ構成と重要ファイル

- `app/Services/AttachmentService.php` : 添付ファイル保存、サムネイル生成、メタ整形。
- `app/Http/Controllers/AttachmentController.php` : 添付配信（stream）。署名付きURLの受け口。
- `routes/web.php`, `routes/api.php`, `routes/chat.php` : ルート定義。ページ固有ストリームあり（/chat/attachments, /bot/attachments, /attachments/signed 等）。
- `resources/js/Helpers/attachment.js` : フロントヘルパー（URL 正規化／ストリームベース選択）。
- `z_instructions/` : プロジェクトの運用・設計ドキュメント群（CONSOLIDATED_01..07 等）。
- `z_instructions/attachment_guidelines.md` : 添付ファイルのガイドライン（この `.md` に統合済み）。

---

## 開発環境の起動と主要コマンド

- 前提: Docker / docker compose を利用
- よく使うコマンド:
    - コンテナ内で artisan 実行
        - `docker compose exec laravel bash -lc "php artisan migrate --seed"`
    - キャッシュ / config クリア
        - `docker compose exec laravel php artisan config:clear`
        - `docker compose exec laravel php artisan cache:clear`
    - コンテナ再起動
        - `docker compose restart laravel`
    - フロントビルド
        - `npm install`（初回）
        - `npm run build`
    - ログ確認
        - `docker compose exec laravel tail -f storage/logs/laravel.log`

必ずコンテナ内で `php artisan` を実行すること（ホスト環境で直接実行すると DB などの接続が失敗することがある）。

---

## コーディング規約とレイアウト方針

- Vue / Inertia ページは `AppLayout` を使用。共通の外側ラッパー構造を守る（`<div class="py-12">` など）。
- Ziggy の `route()` を使用する場合はパラメータをオブジェクトで渡す。
- Tailwind CSS のユーティリティ基準に従う（テーブル・カードのクラス等は `CONSOLIDATED_01_layout_and_ui.md` を参照）。
- ファイル名の大文字小文字は一貫させる（Unix 系で差異が致命的になる）。

フロントエンドの変更を行ったら必ず `npm run build` を実行し、`public/build` をコミット（本番向けビルドの場合）するか CI でビルドを通す。

---

## 認証・セッション・CORS の扱い

（詳細は `z_instructions/CONSOLIDATED_02_security_and_sessions.md` / `CONSOLIDATED_03_auth_and_cors.md` を参照）

要点:

- SPA + Sanctum の場合、`web` ミドルウェア経路を通して `StartSession` を適用すること。401 対策として重要。
- セッション移行時は `SESSION_DRIVER=redis` を .env に設定し、`config/session.php` がそれに従うこと。
- CORS は `config/cors.php` と `.env` の `CORS_ALLOWED_ORIGINS` を本番ドメインに限定する。

---

## 添付ファイル（attachments）に関する詳細ルール

（`z_instructions/attachment_guidelines.md` の内容を要約）

1. 保存先
    - すべて `Storage::disk('public')` の `attachments/` 下に保存する（`storage/app/public/attachments`）。
2. サムネイル
    - `AttachmentService::createThumbnailFromDiskPath` が生成し、`attachments/thumbs/` に保存する。
3. ストリーミング
    - フロントはストレージ直リンクを使わず、ストリーミングエンドポイント経由で表示する。
    - ページ固有エンドポイント: `/chat/attachments`（チャット用、web+auth:sanctum）、`/bot/attachments`（bot）、`/attachments/signed`（署名用）
4. 署名付き URL
    - `URL::temporarySignedRoute('attachments.signed', $expires, ['path' => $path])` で生成。
    - コントローラ内で `URL::hasValidSignature($request)` を確認して read-only を許可する実装を推奨（ミドルウェアの前提が崩れる開発環境があるため）。
5. 認可
    - メッセージに紐付く添付は送信者・受信者のみ閲覧可能（署名付き URL は例外として read-only 許可）。
    - アップローダーの `user_id` がある場合はその本人または admin のみ削除可能。
6. フロントヘルパー
    - `resources/js/Helpers/attachment.js` を参照し、クエリの二重エンコードを避ける。ストリームベースはページごとに選択する。
7. テスト
    - `curl` で署名 URL を叩き、200 が返ることを確認する。`laravel.log` を tail して挙動を確認。

---

## セキュリティ要件

- シークレット（API keys, DB passwords）は決してコミットしない。`.env.example` に必要なキー名だけを残す。
- アップロードされたファイルは MIME 検査、拡張子チェック、ファイル名サニタイズを行う（`AttachmentService` に依存）。
- HTML コンテンツはサニタイズ（HTMLPurifier / DOMPurify）を必須にする。JS 実行可能なコンテンツは排除する。
- CORS と SameSite / Secure セッティングは `config/cors.php` と `.env` で管理。開発時も最小限の差分に留める。
- 大きな変更（セキュリティ周り）を行う場合は必ずコードレビューを要求し、運用担当と合意すること。

---

## 署名付き URL、APP_URL、TrustProxies の注意点

- 署名の生成/検証はホスト／スキームに依存するため、`APP_URL` が正しく設定されていることを確認する。
- Docker / リバースプロキシ環境では `AppServiceProvider::boot()` で `URL::forceRootUrl(config('app.url'))` と必要時に `URL::forceScheme('https')` を呼ぶことで署名の不一致を防げる。
- `TrustProxies` ミドルウェアが正しく設定されていること（信頼するプロキシの IP / ヘッダ）を確認する。

---

## テスト・CI・品質ゲート

- 最低限の自動チェック:
    - `composer install` が通る
    - `npm ci && npm run build` が通る（フロントテストは必要に応じて）
    - `php artisan test`（Unit/Feature テスト）
    - PHPStan / Psalm（プロジェクトに導入されている場合）
- PR の要件:
    - 単体テスト or 新規テストを追加（影響する箇所）
    - 依存関係の変更は `composer.lock` / `package-lock.json` を更新
    - セキュリティに関わる変更はマージ前に担当者の承認を得る

---

## ロギング・デバッグ方法

- `storage/logs/laravel.log` を参照。添付に関する流れは AttachmentController と AttachmentService のログに出すこと。署名検証時は `URL::hasValidSignature` の結果をログに残すと良い。
- 署名トラブル時は `z_instructions/attachment_guidelines.md` に書いた `/attachments/signed/compare` のような一時デバッグを作って差分を確認する。

---

## ブランチ / コミット / PR の運用ルール

- ブランチ命名: `feature/<short-desc>` / `fix/<short-desc>` / `chore/<short-desc>` / `hotfix/<short-desc>`
- コミットメッセージ: 短い概要行（50 文字以内） + 空行 + 詳細（必要なら 72 文字折り返し）
- PR 作成時: 変更点、背景、動作確認手順、影響範囲、必要なマイグレーション / 環境変数を明記する。
- マージ: 1 人以上のレビュアー + CI グリーン

---

## デプロイと運用時チェックリスト

- デプロイ前:
    - `.env` の `APP_URL`、`CORS_ALLOWED_ORIGINS`、セッション設定を確認
    - `php artisan config:cache` を実行
    - DB マイグレーションを実行（`php artisan migrate --force`）
- デプロイ後:
    - 主要ルートのステータス（/、/chat、/diaries）を素早く確認
    - `storage/logs/laravel.log` を監視し、エラーが出ていないかチェック

---

## 最後に（AI が守るべき行動規範）

1. 絶対にシークレットや個人情報をコミットしないこと。
2. セキュリティ関連の修正は小さく分け、必ずテストを付けること。
3. 本番に影響する変更は必ず人の承認（レビュワー）を要求すること。
4. 変更を行う前に `z_instructions/` 内の該当ドキュメントを読み、既存設計を尊重すること。

---

参照: `z_instructions/CONSOLIDATED_01_layout_and_ui.md`, `CONSOLIDATED_02_security_and_sessions.md`, `CONSOLIDATED_03_auth_and_cors.md`, `CONSOLIDATED_04_ai_and_chat.md`, `CONSOLIDATED_05_calendar_and_jobbox.md`, `CONSOLIDATED_06_messages_and_files.md`, `CONSOLIDATED_07_workload_and_handover.md`, `z_instructions/attachment_guidelines.md`

この `.github/copilot-instructions.md` を新しい AI エージェントの第一参照ドキュメントとして利用してください。必要なら英語版、短縮版（要点のみ）、あるいは PR テンプレート等も作成します。
