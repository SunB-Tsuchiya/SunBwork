# AI / 開発者向け引継ぎドキュメント（AI_HANDOVER.md）

最終更新: 2025-08-20

このファイルは、チャット機能・AIプリセット・エクスポート機能の実装履歴、仕様、運用手順、注意点を AI や新しい開発担当者へ引き継ぐための詳細ドキュメントです。

## 目的
- AI チャット機能（/bot/chat）の安定稼働と保守。
- 管理画面で編集可能な AI プリセット（instructions / system prompts）の提供と DB 管理。
- チャット会話を txt/md/doc でエクスポート／ダウンロードする機能。
- チャットの表示を Markdown（見出し/太字/コードブロック）でリッチ表示すること。

---

## 実装サマリ（要点）
- バックエンド: Laravel（Controllers、Eloquent、Migration、Seeder、Storage、Routes）
- フロントエンド: Vue 3 + Inertia、markdown-it を用いて assistant メッセージを Markdown レンダリング
- DB: `ai_presets` テーブル（type=model|instruction|system, data JSON）、`ai_conversations` / `ai_messages` テーブルで会話履歴を保存
- 出力: `storage/app/exports` にファイルを生成。エクスポートは txt / md / doc（HTML を .doc として保存）をサポート

---

## 重要なファイルと役割
- `app/Http/Controllers/Bot/BotController.php` — /bot/chat の実装（ファイル取り込み、OpenAI 呼び出し、会話保存）。
  - 修正済の注意点: `$totalCharsIncluded` を必ず初期化するよう修正。
- `app/Http/Controllers/Bot/BotExportController.php` — 会話エクスポート（POST /bot/export、GET /bot/export/download/{filename}）。
- `routes/web.php` — エクスポート用ルート追加。
- `resources/js/Pages/Bot/ChatBot.vue` — チャット UI / エクスポート UI / markdown-it を用いたレンダリング。
- `app/Models/AiPreset.php`、`database/migrations/*create_ai_presets_table.php`、`database/seeders/AiPresetsSeeder.php` — プリセットの DB 化と初期データシード。
- `resources/js/Pages/Admin/AiSettingsEdit.vue` — 管理画面でプリセット選択・適用（ドロップダウン、onchange/onblur 自動適用）。

---

## 動作確認／運用手順
1) Docker 環境起動（プロジェクトルートで）

```bash
docker compose up -d
```

2) Laravel コンテナ内でマイグレーション・シード（`AiPresetsSeeder`）を実行

```bash
# コンテナ内で実行
docker compose exec laravel bash -lc "php artisan migrate --force && php artisan db:seed --class=AiPresetsSeeder"
```

3) キャッシュクリア（必要なら）

```bash
docker compose exec laravel bash -lc "php artisan cache:clear"
```

4) チャット動作確認
- ブラウザでチャットページを開き、メッセージ送信。エラーが出る場合は Network の POST `/bot/chat` のレスポンス、及び `storage/logs/laravel.log` を確認。

5) エクスポート確認
- チャット UI のエクスポートから md/txt/doc を選んでエクスポート。生成ファイルは `storage/app/exports` に保存される。ダウンロード URL は `GET /bot/export/download/{filename}`。

---

## 仕様詳細
- ファイル取り込み（ユーザ添付）
  - `BotController` はアップロードファイルの `path` と `preview` を受け取る想定。テキスト系（txt, md, csv, json, html, xml, log）であれば最初の一部（`$maxPerFile`=8000 文字）を読み込み、全体で `$maxTotalChars`=24000 文字の上限を守る。
  - 取り込み失敗や大きすぎる場合は model にファイルが存在する旨のみ通知するシステムメッセージを付与する。

- OpenAI 呼び出し
  - 環境変数 `VITE_OPENAI_API_KEY` または `OPENAI_API_KEY` を優先して使用。
  - 管理設定（`AiSetting`）で `model`, `max_tokens`, `model_options.temperature` を優先参照。
  - ハードキャップ `config('reverb.ai_max_tokens_hardcap', 2000)` を超えないよう制御。

- エクスポート形式
  - txt: プレーンテキスト（ユーザー/AIのロールと内容をシンプルに出力）。
  - md: Markdown（見出し、太字、コードブロックが保持される）。
  - doc: HTML を `.doc` 拡張子で保存（Microsoft Word で開ける簡易対応）。

---

## 変更履歴（作業ログ・主要変更）
- config/ai_presets.php に `instructions_presets` と `system_prompts` を追加（各5個）
- 管理画面: `AiSettingsEdit.vue` にプリセット適用 UI を追加
- DB: `ai_presets` テーブル、`AiPreset` モデル、`AiPresetsSeeder` を追加。シードは config から初期値を読み込む。
- エクスポート: `BotExportController` とルート追加、`ChatBot.vue` にエクスポート UI を実装
- 表示: `ChatBot.vue` に `markdown-it` を追加し assistant メッセージを Markdown レンダリング
- バグ修正: `BotController` の `$totalCharsIncluded` 未定義を初期化して解消

---

## 注意事項・運用上の留意点
- DB ホスト名の解決
  - 開発環境では `mysql` が Docker Compose 内のサービス名として期待される。ホスト（ローカル端末）から直接 `php artisan` 等を実行すると DNS 解決に失敗することがある（例: getaddrinfo for mysql failed）。その場合は Laravel コンテナ内でコマンドを実行すること。

- ファイル書き込み権限
  - `storage/app/exports` にファイルを書き出すため、Web ユーザが書き込みできることを確認すること（`chown -R www-data:www-data storage` など）。

- セキュリティ
  - エクスポートファイルへのアクセス制御はルートのミドルウェアで保護する必要がある（現在は認証ミドルウェアはあるが、より厳密なユーザ紐付け/許可が推奨）。
  - Markdown のレンダリングは `markdown-it` を html 出力無効化（サニタイズ）で使用することを意図している。ユーザ生成 HTML はそのまま出力しない。

- ロギング
  - `storage/logs/laravel.log` はトラブルシュートの第一情報源。OpenAI 呼び出しやファイル読み込み失敗、DB 接続失敗はここに残る。

---

## トラブルシュート（よくある問題と対処）
- 500 エラー: DB 接続失敗
  - コンテナが起動しているか、MySQL が `healthy` か確認。
  - 必要なら `docker compose logs mysql` を確認。
  - Laravel 側で `php artisan migrate` を実行する場合はコンテナ内で実行すること。

- エラー: Undefined variable $totalCharsIncluded
  - 修正済み: `app/Http/Controllers/Bot/BotController.php` にて初期化を追加。

- エラー: PUT が 405 を返す（管理画面保存失敗）
  - フロントで PUT が 405 の場合、POST + `_method=PUT` のフォールバックを実装している。サーバ側のルーティングも確認。

- フロントビルドが失敗する（npm run dev / build）
  - Node / npm のバージョン整合性を確認。Vite の設定（glob の deprecation warnings）が出ることがあるが軽微。

---

## 開発／改善の提案（今後のタスク候補）
- 管理画面: プリセットの詳細編集 UI（JSON の視覚編集、バージョン管理）
- エクスポート: PDF 出力、ZIP（添付ファイル込み）対応
- テスト: Pest を用いた API とシードの自動化テストを追加
- セキュリティ: エクスポート用ファイルの署名付き URL と有効期限を導入
- ロギング: OpenAI 呼び出しのトレースID・レスポンスタイムの収集

---

## 連絡先と参考情報
- 現在のブランチ: `chat01`
- 主要ファイルの所在は上記「重要なファイルと役割」を参照
- 追加質問や修正要望は、このリポジトリの issue または直接 PR で送ってください。

---

このドキュメントは必要に応じて更新してください。追加で含めたい手順やログの抜粋があれば指示してください。
