# メッセージとファイル（統合ドキュメント）

概要

メッセージ（内部通知）と添付ファイル周りの実装指針、アップロード／表示時のサニタイズと最小情報返却ルールをまとめます。

必須ルール

- ファイルメタは最小限で返す（original_name, mime, size, path, url）。外部 URL は allow-list ベースで検査する。
- ユーザーへ表示する本文は必ず sanitizer（HTMLPurifier / DOMPurify）で処理する。
- メッセージの添付は基本的にサーバ側で blob/object URL を作り、フロントでは object URL を使って表示する（直接 HTML に生の URL を差し込まない）。

サーバ側

- Bot/AiHistoryController 等で meta を sanitize する関数を導入する（sanitizeMeta / sanitizeFileMeta）
- MessageController では paginate + appends($request->query()) を使いページング時にクエリを保持する

参照元

- messages_spec_history.md
- ForQuillEditor..txt
- ai_session_context.md

運用チェック

- attachments を扱う際は Storage の権限（storage と bootstrap/cache）を確認する
- テストで外部 URL が除外されることを確認する（unit test / feature test）

次のアクション

- FileMetaSanitizer サービスのスケッチを追加可能です。
