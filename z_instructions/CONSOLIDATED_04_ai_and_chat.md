# AI / チャット機能（統合）

概要

Bot（/bot/chat）や AI プリセット、チャット履歴のエクスポートなど、AI／チャット周りの実装メモを一箇所にまとめたものです。AI 関連の実装はサニタイズとファイルメタの扱いが重要になります。

重要な実装方針

- OpenAI キーは環境変数で管理（VITE_OPENAI_API_KEY / OPENAI_API_KEY の優先）。AiSetting で model/options を指定できるようにする。
- チャットのエクスポートは storage/app/exports に出力する。ダウンロードは専用ルートで提供する。
- AI が生成する Markdown/HTML は必ずサニタイズする（DOMPurify / HTMLPurifier を使用）。外部 URL は検査して allow-list のみ許可。
- ファイルメタは最小情報で返す（original_name, mime, size, path, url）。公開情報以外は返さない。

サーバ側ポイント

- app/Http/Controllers/Bot/BotController.php（/bot/chat）: totalCharsIncluded の初期化漏れに注意
- BotExportController: エクスポート生成とダウンロードルートを持つ
- File/FileMetaSanitizer サービスを作成して、アップロード前に meta を整形／検査することを推奨

参照元

- AI_AGENT_JOB_ANALYSIS_SUMMARY.md
- AI_HANDOVER.md
- AI_agent_workflow_summary.md
- ai_session_context.md
- chat_guideline.md
- SECURITY_SANITIZATION_GUIDELINES.md

運用チェック

1. チャット動作確認はブラウザで行い、Network タブと storage/logs/laravel.log を監視する
2. エクスポート時は生成ファイルの所有権（storage）とダウンロード URL の安全性を保証する (chown -R www-data:www-data storage)

次のアクション

- FileMetaSanitizer / HtmlSanitizer の実装スニペットが欲しければ追加します。
