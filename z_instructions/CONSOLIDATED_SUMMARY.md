# z_instructions - 要点サマリ

このファイルは `z_instructions` 内の各種ドキュメントを読んで抽出した要点と、実務で必ず守るべきチェックリストをまとめたものです。

**目的**: 開発者／AI がプロジェクト内で安全に添付・認証・UI を扱うための短いリファレンスを提供する。

**注意**: `backups/` 配下のファイルは読み飛ばす運用を徹底してください。

**主要要点（抜粋）**

- **添付（attachments）**: すべて `Storage::disk('public')` の `attachments/` に保存。配信はストリーミングエンドポイント経由（例: `/chat/attachments`, `/bot/attachments`, `/attachments/signed`）で行い、直接 `/storage` へのリンクは避ける。
- **署名付き URL**: `URL::temporarySignedRoute('attachments.signed', $expires, ['path' => $path])` を利用。コントローラ内で `URL::hasValidSignature($request)` を再確認し、未認証ユーザは署名なしでアクセス不可。
- **ルーティング / ミドルウェア**: SPA 向けのストリームエンドポイントは必ず `web` ミドルウェアで提供する（`routes/web.php` や `routes/chat.php`）。`routes/api.php` に置くと `StartSession` が通らず認証が失敗するケースあり。
- **ファイル命名**: ストレージ上のファイル名は `<uuid>_<original_name>`。UUID は `Str::uuid()`、original 名は危険文字を置換して保存。
- **サムネイル**: `attachments/thumbs/` に保存。サムネイル生成は `AttachmentService::createThumbnailFromDiskPath` に集約。
- **セキュリティ / セッション**: SPA + Sanctum の場合、StartSession と CSRF フローが前提。コンテナ内で `php artisan` を実行する運用を徹底。
- **サニタイズ**: HTML/Markdown/ファイルメタは `HTMLPurifier` / `DOMPurify` / FileMetaSanitizer で必ず sanitize する。外部 URL は allow-list 検査。
- **UI / レイアウト**: 全ての Inertia ページは `AppLayout` を使用し、既定の外側 div 構造（`py-12` 等）を守る。
- **AI / チャット**: AI キーは環境変数で管理。AI が生成するコンテンツは必ずサニタイズして保存・表示する。
- **カレンダー/JobBox**: FullCalendar には plain オブジェクトを渡す（`structuredClone` など）。日付はサーバ UTC / フロント JST の変換に注意。

**実務チェックリスト（開発前 / デプロイ前）**

- **添付関連**:
    - `storage/app/public/attachments` にファイルが保存されているか確認。`ls -l` を活用。
    - 署名 URL を発行したら、`curl -i '<署名 URL>' -H 'Accept: image/*'` で確認。
    - `storage/logs/laravel.log` を tail して `StartSession`／`URL::hasValidSignature` のログを確認。
- **ルーティング/ミドルウェア**:
    - SPA 用ストリームは `web` グループに配置されているか確認（`routes/web.php` または `routes/chat.php`）。
    - `routes/api.php` に残す必要がある場合は設計を明示（token 認証等）。
- **セッション/環境**:
    - `.env.example` に `CORS_ALLOWED_ORIGINS`, `SESSION_SECURE_COOKIE`, `SESSION_SAME_SITE` を明記。
    - コンテナ内で `php artisan config:clear && php artisan cache:clear` を実行して差分を反映。
- **ファイル名/保存規則**:
    - 保存時は `<uuid>_<safe_original_name>` の生成ロジックを `AttachmentService` にまとめる。
- **サニタイズ / 公開ポリシー**:
    - HTML/Markdown は必ず sanitizer を通す。
    - ファイルメタは公開に必要な最小情報のみ返す（`original_name`, `mime`, `size`, `path`, `url`）。

**短い運用手順（疑問発生時）**

- 署名検証が通らない: `APP_URL` と `TrustProxies`、`AppServiceProvider::boot` の `URL::forceRootUrl` を確認し、コンテナ再起動とキャッシュクリアを実施。
- SPA で 401 が出る: 該当エンドポイントが `web` ミドルウェア経由であるか確認し、必要に応じてルートを移動。

**ファイル**: 要点の詳細は個別ドキュメントを参照してください（例: `CONSOLIDATED_08_attachment.md`, `CONSOLIDATED_02_security_and_sessions.md` 等）。

---

この要約を `z_instructions/CONSOLIDATED_SUMMARY.md` として作成しました。次は詳細チェックリストの自動テスト化（スクリプト化）を行うか、要点を必要なチームメンバー向けに分割することをおすすめします。
