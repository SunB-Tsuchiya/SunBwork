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
- **UI / レイアウト**: 全ての Inertia ページは `AppLayout` を使用。`py-12 > max-w-7xl` は AppLayout 内部提供済み — ページ側で重複しない。戻るボタンは `#header` スロット内の `div.flex.items-center.gap-3` パターンを使う。
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

**2026-04-20 以降に追加された主要機能（詳細は CONSOLIDATED_09 参照）**

- **一括案件登録（BulkCreate, 2026-04-20）**: CSV から複数 ProjectJob を一括登録
- **製版ボード（Prepress Board, 2026-04-28）**: 案件の製版工程を Kanban 管理
- **ProgressSheet v2（2026-04-27）**: JobLink / User 型セルのロック連動
- **ゴーストユーザー（2026-05-13）**: `users.is_ghost` / `ghost_owner_id` — テスト用ユーザー
- **工程シート（WorkflowSheets, 2026-05-14）**: `workflow_sheets` / `workflow_sheet_rows` / `workflow_sheet_cells`
- **イルカボード（在籍ボード, 2026-05-15）**: `user_presence_statuses` テーブル、在籍状況管理
- **スクリプトセクション（2026-05-16）**: `auth.canAccessScripts` で制御。`Components/Scripts/` に配置
- **クライアント ID（client_code, 2026-05-21）**: `clients.client_code` / `clients.is_registered` — CSV 突合キー
- **更新ログ（Changelog, 2026-05-23）**: `changelogs` テーブル。`changelogs.index` / `changelogs.show` ルート

**2026-05 追記: マイジョブ自動完了バッチ**

- `app/Console/Commands/AutoCompleteMyJobs.php` — 自己割当ジョブのうち日付超過（`scheduled_at` 優先、`desired_end_date` 次候補）かつ未完了のものを `completed = true` にするバッチ
- `app/Console/Kernel.php` — 毎日 `00:05` に `auto-complete:my-jobs` を実行するよう登録
- さくらサーバーでは `crontab -e` で `* * * * * cd ~/SunBWork && php artisan schedule:run >> /dev/null 2>&1` が必要（未登録の場合は手動追加）

---

この要約を `z_instructions/CONSOLIDATED_SUMMARY.md` として作成しました。次は詳細チェックリストの自動テスト化（スクリプト化）を行うか、要点を必要なチームメンバー向けに分割することをおすすめします。
